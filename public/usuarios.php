<?php
// public/usuarios.php — gestão de usuários (somente administradores)
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$admin = Auth::requireAdmin();
$errors = [];

$activeAdmins = static fn(): int => (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $action = (string) ($_POST['acao'] ?? '');

    if ($action === 'criar') {
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['nome'] ?? '')));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = array_key_exists($_POST['papel'] ?? '', Auth::ROLES) ? $_POST['papel'] : 'visualizador';
        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            $errors[] = 'Informe o nome completo (3 a 120 caracteres).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors[] = 'E-mail inválido.';
        } elseif (Database::value('SELECT id FROM users WHERE email = ?', [$email])) {
            $errors[] = 'Já existe um usuário com este e-mail.';
        }
        if (!$errors) {
            $temp = PasswordPolicy::temporary();
            Database::run(
                'INSERT INTO users (name, email, password_hash, role, must_change_password) VALUES (?,?,?,?,1)',
                [$name, $email, Security::hashPassword($temp), $role]
            );
            $newId = Database::lastId();
            Logger::audit('usuario_criado', 'user', $newId, ['email' => $email, 'papel' => $role]);
            $_SESSION['temp_password'] = ['nome' => $name, 'email' => $email, 'senha' => $temp];
            redirect('usuarios.php');
        }
    } else {
        $targetId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $target = $targetId ? Database::one('SELECT * FROM users WHERE id = ?', [$targetId]) : null;
        $isSelf = $target && (int) $target['id'] === (int) $admin['id'];
        $isLastAdmin = $target && $target['role'] === 'admin' && (int) $target['is_active'] && $activeAdmins() <= 1;

        if (!$target) {
            $errors[] = 'Usuário não encontrado.';
        } elseif ($action === 'alternar_ativo') {
            if ($isSelf || $isLastAdmin) {
                $errors[] = 'Você não pode desativar a própria conta nem o último administrador.';
            } else {
                $active = (int) $target['is_active'] ? 0 : 1;
                Database::run('UPDATE users SET is_active = ? WHERE id = ?', [$active, $target['id']]);
                Auth::revokeSessions((int) $target['id']);
                Logger::audit($active ? 'usuario_ativado' : 'usuario_desativado', 'user', (int) $target['id']);
                flash('sucesso', $target['name'] . ($active ? ' foi reativado.' : ' foi desativado e desconectado.'));
            }
        } elseif ($action === 'alterar_papel') {
            $role = array_key_exists($_POST['papel'] ?? '', Auth::ROLES) ? $_POST['papel'] : 'visualizador';
            if (($isSelf || $isLastAdmin) && $role !== 'admin') {
                $errors[] = 'Você não pode remover o próprio acesso de administrador nem o do último administrador.';
            } elseif ($role !== $target['role']) {
                Database::run('UPDATE users SET role = ? WHERE id = ?', [$role, $target['id']]);
                Auth::revokeSessions((int) $target['id']);
                Logger::audit('usuario_papel', 'user', (int) $target['id'], ['de' => $target['role'], 'para' => $role]);
                flash('sucesso', 'Perfil de ' . $target['name'] . ' alterado.');
            }
        } elseif ($action === 'redefinir_senha') {
            if ($isSelf) {
                $errors[] = 'Para trocar a sua senha, use "Minha conta".';
            } else {
                $temp = PasswordPolicy::temporary();
                Database::run(
                    'UPDATE users SET password_hash = ?, must_change_password = 1, failed_logins = 0, locked_until = NULL WHERE id = ?',
                    [Security::hashPassword($temp), $target['id']]
                );
                Auth::revokeSessions((int) $target['id']);
                Logger::audit('senha_redefinida_admin', 'user', (int) $target['id']);
                $_SESSION['temp_password'] = ['nome' => $target['name'], 'email' => $target['email'], 'senha' => $temp];
            }
        } elseif ($action === 'resetar_2fa') {
            if ($isSelf) {
                $errors[] = 'Peça a outro administrador para resetar o seu 2FA.';
            } else {
                Database::run('UPDATE users SET totp_secret_enc = NULL, totp_enabled = 0, totp_last_step = NULL, recovery_codes = NULL WHERE id = ?', [$target['id']]);
                Auth::revokeSessions((int) $target['id']);
                Logger::audit('2fa_resetado_admin', 'user', (int) $target['id']);
                flash('sucesso', '2FA de ' . $target['name'] . ' foi removido. Ele precisará configurar de novo.');
            }
        } elseif ($action === 'desbloquear') {
            Database::run('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?', [$target['id']]);
            Logger::audit('usuario_desbloqueado', 'user', (int) $target['id']);
            flash('sucesso', $target['name'] . ' foi desbloqueado.');
        }
        if (!$errors) {
            redirect('usuarios.php');
        }
    }
}

$tempPassword = $_SESSION['temp_password'] ?? null;
unset($_SESSION['temp_password']);

$users = Database::all('SELECT id, name, email, role, is_active, must_change_password, totp_enabled, locked_until, last_login_at FROM users ORDER BY is_active DESC, name');

render_header('Usuários', ['active' => 'usuarios']);
?>
<div class="page-head">
    <div>
        <h1>Usuários</h1>
        <p class="muted">Quem pode entrar no sistema. <strong>Membro da igreja</strong> só vê e baixa as fotos; <strong>Equipe da mídia</strong> também envia, edita e publica nas redes.</p>
    </div>
</div>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-erro" role="alert"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($tempPassword): ?>
<section class="panel panel-highlight">
    <h2>Senha provisória de <?= e($tempPassword['nome']) ?></h2>
    <p>Envie para a pessoa por um canal privado (WhatsApp direto, nunca em grupo). No primeiro acesso ela será obrigada a criar uma senha pessoal. <strong>Esta senha não será mostrada de novo.</strong></p>
    <p class="temp-pass"><span class="muted">E-mail:</span> <code><?= e($tempPassword['email']) ?></code><br>
       <span class="muted">Senha:</span> <code class="js-copy" title="Clique para copiar"><?= e($tempPassword['senha']) ?></code></p>
</section>
<?php endif; ?>

<section class="panel">
    <h2>Novo usuário</h2>
    <form method="post" action="usuarios.php" class="form-row" autocomplete="off">
        <?= Csrf::field() ?>
        <input type="hidden" name="acao" value="criar">
        <label class="field"><span>Nome completo</span><input type="text" name="nome" required maxlength="120"></label>
        <label class="field"><span>E-mail</span><input type="email" name="email" required maxlength="190"></label>
        <label class="field"><span>Perfil</span>
            <select name="papel">
                <option value="visualizador">Membro da igreja (só visualiza)</option>
                <option value="membro">Equipe da mídia (envia fotos)</option>
                <option value="admin">Administrador</option>
            </select></label>
        <button type="submit" class="btn btn-primary">Criar e gerar senha</button>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Nome</th><th>Perfil</th><th>Situação</th><th>Último acesso</th><th class="right">Ações</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u):
            $locked = $u['locked_until'] && strtotime($u['locked_until']) > time(); ?>
            <tr class="<?= (int) $u['is_active'] ? '' : 'is-muted' ?>">
                <td><strong><?= e($u['name']) ?></strong><br><span class="muted small"><?= e($u['email']) ?></span></td>
                <td>
                    <form method="post" action="usuarios.php" class="js-autosubmit">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="acao" value="alterar_papel">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <select name="papel" aria-label="Perfil de <?= e($u['name']) ?>">
                            <option value="visualizador" <?= $u['role'] === 'visualizador' ? 'selected' : '' ?>>Visualiza</option>
                            <option value="membro" <?= $u['role'] === 'membro' ? 'selected' : '' ?>>Equipe da mídia</option>
                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </form>
                </td>
                <td>
                    <?php if (!(int) $u['is_active']): ?><span class="badge">Desativado</span><?php endif; ?>
                    <?php if ($locked): ?><span class="badge badge-erro">Bloqueado</span><?php endif; ?>
                    <?php if ((int) $u['must_change_password']): ?><span class="badge badge-aviso">Senha provisória</span><?php endif; ?>
                    <?php if ((int) $u['totp_enabled']): ?><span class="badge badge-ok">2FA</span><?php endif; ?>
                </td>
                <td class="small"><?= $u['last_login_at'] ? e(date('d/m/Y H:i', strtotime($u['last_login_at']))) : '—' ?></td>
                <td class="right actions-cell">
                    <?php foreach (array_filter([
                        $locked ? ['desbloquear', 'Desbloquear', ''] : null,
                        ['redefinir_senha', 'Nova senha', 'Gerar uma senha provisória para ' . $u['name'] . '? A senha atual deixará de funcionar.'],
                        (int) $u['totp_enabled'] ? ['resetar_2fa', 'Resetar 2FA', 'Remover o 2FA de ' . $u['name'] . '? Use quando a pessoa perdeu o celular.'] : null,
                        ['alternar_ativo', (int) $u['is_active'] ? 'Desativar' : 'Reativar', (int) $u['is_active'] ? 'Desativar ' . $u['name'] . '? A pessoa será desconectada na hora.' : ''],
                    ]) as [$act, $label, $confirm]): ?>
                        <form method="post" action="usuarios.php" class="inline"<?= $confirm ? ' data-confirm="' . e($confirm) . '"' : '' ?>>
                            <?= Csrf::field() ?>
                            <input type="hidden" name="acao" value="<?= e($act) ?>">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="link-button small <?= $act === 'alternar_ativo' && (int) $u['is_active'] ? 'danger' : '' ?>"><?= e($label) ?></button>
                        </form>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
<?php render_footer(['app.js']);
