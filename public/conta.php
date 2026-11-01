<?php
// public/conta.php — trocar senha, 2FA, códigos de recuperação e redes sociais do próprio usuário
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireLogin(true);
$pending = Auth::pendingRequirement();
$require2fa = $user['role'] === 'admin' && Config::bool('REQUIRE_2FA_ADMIN', true);
$errors = [];
$showCodes = null;

/** Confirma a senha atual do usuário logado. */
$checkPassword = static fn(string $password): bool => password_verify($password, (string) $user['password_hash']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $action = (string) ($_POST['acao'] ?? '');

    if ($action === 'trocar_senha') {
        $current = (string) ($_POST['senha_atual'] ?? '');
        $new = (string) ($_POST['nova_senha'] ?? '');
        $confirm = (string) ($_POST['confirmar_senha'] ?? '');
        if (!$checkPassword($current)) {
            $errors[] = 'A senha atual está incorreta.';
            Logger::audit('troca_senha_falhou', 'user', (int) $user['id']);
        } elseif (!hash_equals($new, $confirm)) {
            $errors[] = 'A confirmação não é igual à nova senha.';
        } elseif (hash_equals($current, $new)) {
            $errors[] = 'A nova senha precisa ser diferente da atual.';
        } else {
            $errors = PasswordPolicy::validate($new, [$user['name'], $user['email']]);
        }
        if (!$errors) {
            Database::run(
                'UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW() WHERE id = ?',
                [Security::hashPassword($new), $user['id']]
            );
            // Encerra as sessões nos outros aparelhos e mantém esta
            Auth::revokeSessions((int) $user['id']);
            $_SESSION['session_version'] = (int) Database::value('SELECT session_version FROM users WHERE id = ?', [$user['id']]);
            Session::regenerate();
            Logger::audit('senha_trocada', 'user', (int) $user['id']);
            flash('sucesso', 'Senha alterada. As sessões abertas em outros aparelhos foram encerradas.');
            redirect('conta.php');
        }
    }

    if ($action === 'ativar_2fa') {
        $secret = $_SESSION['totp_setup'] ?? null;
        $step = $secret ? Totp::verify($secret, (string) ($_POST['codigo'] ?? '')) : null;
        if ($step === null) {
            $errors[] = 'Código inválido. Confira se o horário do celular está automático e digite o código que aparece agora.';
        } else {
            $codes = Totp::generateRecoveryCodes();
            Database::run(
                'UPDATE users SET totp_secret_enc = ?, totp_enabled = 1, totp_last_step = ?, recovery_codes = ? WHERE id = ?',
                [Security::encrypt($secret), $step, json_encode(array_map([Security::class, 'hashPassword'], $codes)), $user['id']]
            );
            unset($_SESSION['totp_setup']);
            Session::regenerate();
            Logger::audit('2fa_ativado', 'user', (int) $user['id']);
            $_SESSION['show_recovery'] = $codes;
            flash('sucesso', 'Verificação em duas etapas ativada.');
            redirect('conta.php');
        }
    }

    if ($action === 'desativar_2fa') {
        if ($require2fa) {
            $errors[] = 'Administradores não podem desativar a verificação em duas etapas.';
        } elseif (!$checkPassword((string) ($_POST['senha'] ?? ''))) {
            $errors[] = 'Senha incorreta.';
        } else {
            Database::run('UPDATE users SET totp_secret_enc = NULL, totp_enabled = 0, totp_last_step = NULL, recovery_codes = NULL WHERE id = ?', [$user['id']]);
            Logger::audit('2fa_desativado', 'user', (int) $user['id']);
            flash('info', 'Verificação em duas etapas desativada.');
            redirect('conta.php');
        }
    }

    if ($action === 'novos_codigos') {
        if (!$checkPassword((string) ($_POST['senha'] ?? ''))) {
            $errors[] = 'Senha incorreta.';
        } elseif ((int) $user['totp_enabled']) {
            $codes = Totp::generateRecoveryCodes();
            Database::run('UPDATE users SET recovery_codes = ? WHERE id = ?', [json_encode(array_map([Security::class, 'hashPassword'], $codes)), $user['id']]);
            Logger::audit('2fa_novos_codigos', 'user', (int) $user['id']);
            $_SESSION['show_recovery'] = $codes;
            redirect('conta.php');
        }
    }

    // ---------- Redes sociais (somente a própria conta; equipe da mídia) ----------
    if (str_starts_with($action, 'rede_') || $action === 'assinatura') {
        if (!Auth::canUpload($user) || $pending !== null) {
            $errors[] = 'Seu perfil não pode publicar nas redes sociais.';
        } elseif ($action === 'rede_facebook') {
            $r = SocialService::connectFacebook((int) $user['id'], (string) ($_POST['token'] ?? ''));
            $r['ok'] ? flash('sucesso', 'Facebook conectado: ' . $r['nome'] . '.') : ($errors[] = $r['erro']);
        } elseif ($action === 'rede_instagram') {
            $r = SocialService::connectInstagram((int) $user['id'], (string) ($_POST['token'] ?? ''), (string) ($_POST['ig_id'] ?? ''));
            $r['ok'] ? flash('sucesso', 'Instagram conectado: ' . $r['nome'] . '.') : ($errors[] = $r['erro']);
        } elseif ($action === 'rede_testar' || $action === 'rede_desconectar') {
            $network = (string) ($_POST['rede'] ?? '');
            $account = SocialService::accounts((int) $user['id'])[$network] ?? null;
            if (!$account) {
                $errors[] = 'Conta não encontrada.';
            } elseif ($action === 'rede_desconectar') {
                SocialService::disconnect((int) $user['id'], $network);
                flash('info', SocialService::NETWORKS[$network] . ' desconectado. O token foi apagado do sistema.');
            } else {
                $r = SocialService::test($account);
                $r['ok'] ? flash('sucesso', 'Conexão com ' . SocialService::NETWORKS[$network] . ' funcionando.') : ($errors[] = $r['erro']);
            }
        } elseif ($action === 'assinatura') {
            $signature = trim(str_replace("\r\n", "\n", (string) ($_POST['assinatura'] ?? '')));
            if (mb_strlen($signature) > 500) {
                $errors[] = 'A assinatura pode ter no máximo 500 caracteres.';
            } else {
                Database::run('UPDATE users SET social_signature = ? WHERE id = ?', [$signature !== '' ? $signature : null, $user['id']]);
                flash('sucesso', 'Assinatura das legendas salva.');
            }
        }
        if (!$errors) {
            redirect('conta.php#redes');
        }
    }

    if ($action === 'sair_todos') {
        Auth::revokeSessions((int) $user['id']);
        $_SESSION['session_version'] = (int) Database::value('SELECT session_version FROM users WHERE id = ?', [$user['id']]);
        Session::regenerate();
        Logger::audit('sessoes_encerradas', 'user', (int) $user['id']);
        flash('sucesso', 'Todas as outras sessões foram encerradas.');
        redirect('conta.php');
    }
}

// Códigos de recuperação: exibidos uma única vez
if (!empty($_SESSION['show_recovery'])) {
    $showCodes = $_SESSION['show_recovery'];
    unset($_SESSION['show_recovery']);
}

// Recarrega o usuário (pode ter mudado acima)
$user = Database::one('SELECT * FROM users WHERE id = ?', [$user['id']]);
$pending = Auth::pendingRequirement();
$totpUri = null;
$totpSecret = null;
if (!(int) $user['totp_enabled'] && (int) $user['must_change_password'] === 0) {
    $_SESSION['totp_setup'] ??= Totp::generateSecret();
    $totpSecret = $_SESSION['totp_setup'];
    $totpUri = Totp::uri($totpSecret, $user['email']);
}

render_header('Minha conta', ['active' => 'conta', 'user' => $user]);
?>
<div class="page-head">
    <div>
        <h1>Minha conta</h1>
        <p class="muted"><?= e($user['name']) ?> · <?= e($user['email']) ?> · <?= e(Auth::ROLES[$user['role']] ?? $user['role']) ?></p>
    </div>
</div>

<?php if ($pending === 'senha'): ?>
    <div class="alert alert-aviso">Antes de começar, crie uma senha pessoal. A senha provisória não vale mais depois disso.</div>
<?php elseif ($pending === '2fa'): ?>
    <div class="alert alert-aviso">Administradores precisam ativar a verificação em duas etapas para usar o sistema. Siga os passos abaixo.</div>
<?php endif; ?>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-erro" role="alert"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($showCodes): ?>
<section class="panel panel-highlight">
    <h2>Seus códigos de recuperação</h2>
    <p>Guarde estes códigos em local seguro (anote em papel ou salve num gerenciador de senhas). Cada um permite entrar <strong>uma vez</strong> se você estiver sem o celular. <strong>Eles não serão mostrados de novo.</strong></p>
    <ul class="codes">
        <?php foreach ($showCodes as $code): ?><li><code><?= e($code) ?></code></li><?php endforeach; ?>
    </ul>
    <button type="button" class="btn btn-ghost js-print">Imprimir</button>
</section>
<?php endif; ?>

<div class="grid-2">
<section class="panel">
    <h2>Trocar senha</h2>
    <form method="post" action="conta.php" autocomplete="off">
        <?= Csrf::field() ?>
        <input type="hidden" name="acao" value="trocar_senha">
        <input type="text" name="username" value="<?= e($user['email']) ?>" autocomplete="username" hidden>
        <label class="field"><span>Senha atual<?= $pending === 'senha' ? ' (a provisória)' : '' ?></span>
            <input type="password" name="senha_atual" required autocomplete="current-password"></label>
        <label class="field"><span>Nova senha</span>
            <input type="password" name="nova_senha" required minlength="<?= PasswordPolicy::MIN_LENGTH ?>" maxlength="128" autocomplete="new-password" class="js-strength"></label>
        <div class="strength" aria-live="polite"><span class="strength-bar"></span><span class="strength-text small muted"></span></div>
        <label class="field"><span>Repita a nova senha</span>
            <input type="password" name="confirmar_senha" required autocomplete="new-password"></label>
        <p class="muted small">Mínimo de <?= PasswordPolicy::MIN_LENGTH ?> caracteres, com 3 destes: minúscula, maiúscula, número, símbolo. Dica: use uma frase, como <em>Louvor-no-domingo-2026</em>.</p>
        <button type="submit" class="btn btn-primary">Salvar nova senha</button>
    </form>
</section>

<section class="panel">
    <h2>Verificação em duas etapas</h2>
    <?php if ((int) $user['totp_enabled']): ?>
        <p><span class="badge badge-ok">Ativada</span> Além da senha, o login pede o código do aplicativo autenticador.</p>
        <?php $remaining = count(json_decode((string) $user['recovery_codes'], true) ?: []); ?>
        <p class="muted small">Códigos de recuperação restantes: <strong><?= $remaining ?></strong></p>
        <form method="post" action="conta.php" class="inline-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="acao" value="novos_codigos">
            <label class="field"><span>Senha para gerar novos códigos</span>
                <input type="password" name="senha" required autocomplete="current-password"></label>
            <button type="submit" class="btn btn-ghost">Gerar novos códigos</button>
        </form>
        <?php if (!$require2fa): ?>
            <details class="small">
                <summary>Desativar</summary>
                <form method="post" action="conta.php" class="inline-form">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="acao" value="desativar_2fa">
                    <label class="field"><span>Senha</span><input type="password" name="senha" required autocomplete="current-password"></label>
                    <button type="submit" class="btn btn-danger">Desativar 2FA</button>
                </form>
            </details>
        <?php endif; ?>
    <?php elseif ($totpUri): ?>
        <ol class="steps-list">
            <li>Instale no celular o <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong> ou <strong>Authy</strong>.</li>
            <li>No aplicativo, toque em <em>Adicionar</em> e leia este QR Code:
                <div class="qr" id="qr" data-uri="<?= e($totpUri) ?>"></div>
                <details class="small"><summary>Não consigo ler o QR Code</summary>
                    <p>Digite esta chave no aplicativo: <code class="secret"><?= e(trim(chunk_split($totpSecret, 4, ' '))) ?></code></p>
                </details>
            </li>
            <li>Digite o código de 6 dígitos que apareceu:</li>
        </ol>
        <form method="post" action="conta.php" autocomplete="off" class="inline-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="acao" value="ativar_2fa">
            <label class="field"><span>Código</span>
                <input type="text" name="codigo" class="code-input" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="000000"></label>
            <button type="submit" class="btn btn-primary">Ativar</button>
        </form>
    <?php else: ?>
        <p class="muted">Troque a senha provisória primeiro. Depois disso, você poderá ativar a verificação em duas etapas aqui.</p>
    <?php endif; ?>
</section>
</div>

<?php if (Auth::canUpload($user) && $pending === null):
    $social = SocialService::accounts((int) $user['id']); ?>
<section class="panel" id="redes">
    <h2>Redes sociais</h2>
    <p class="muted">Conecte <strong>a sua</strong> Página do Facebook e/ou conta profissional do Instagram para publicar as fotos direto daqui.
        Só você usa essas conexões; nem o administrador vê os seus tokens (ficam criptografados).
        <a href="ajuda-redes.php">Como conseguir os tokens (passo a passo)</a></p>
    <div class="social-grid">
        <?php foreach (['facebook' => ['fb', 'f'], 'instagram' => ['ig', '◎']] as $net => [$cls, $letter]):
            $acc = $social[$net] ?? null; ?>
        <div class="social-card">
            <h3><span class="social-logo <?= $cls ?>" aria-hidden="true"><?= e($letter) ?></span><?= e(SocialService::NETWORKS[$net]) ?></h3>
            <?php if ($acc): ?>
                <div class="social-on">
                    Conectado como <strong><?= e($acc['display_name']) ?></strong><br>
                    <span class="muted small">Token <?= e($acc['token_hint']) ?> · atualizado em <?= e(date('d/m/Y', strtotime($acc['token_updated_at']))) ?>
                    <?= $acc['token_expires_at'] ? ' · expira em ' . e(date('d/m/Y', strtotime($acc['token_expires_at']))) : '' ?></span>
                    <?php if ($acc['last_error']): ?><div class="alert alert-erro small">Último erro: <?= e($acc['last_error']) ?></div><?php endif; ?>
                </div>
                <div class="inline-actions">
                    <?php foreach (['rede_testar' => 'Testar conexão', 'rede_desconectar' => 'Desconectar'] as $act => $label): ?>
                        <form method="post" action="conta.php"<?= $act === 'rede_desconectar' ? ' data-confirm="Desconectar e apagar o token do sistema?"' : '' ?>>
                            <?= Csrf::field() ?>
                            <input type="hidden" name="acao" value="<?= $act ?>">
                            <input type="hidden" name="rede" value="<?= $net ?>">
                            <button type="submit" class="btn btn-small <?= $act === 'rede_desconectar' ? 'btn-ghost' : 'btn-ghost' ?>"><?= e($label) ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
                <details class="small"><summary>Trocar o token</summary>
            <?php endif; ?>
            <form method="post" action="conta.php" autocomplete="off">
                <?= Csrf::field() ?>
                <input type="hidden" name="acao" value="rede_<?= $net ?>">
                <?php if ($net === 'facebook'): ?>
                    <label class="field"><span>Token de acesso da Página</span>
                        <input type="password" name="token" required autocomplete="off" spellcheck="false" placeholder="EAAG…"></label>
                    <p class="muted small">O sistema descobre sozinho qual é a Página. Use o token <em>da Página</em>, não o do seu perfil pessoal.</p>
                <?php else: ?>
                    <label class="field"><span>Token de acesso do Instagram</span>
                        <input type="password" name="token" required autocomplete="off" spellcheck="false" placeholder="IGAA… ou EAAG…"></label>
                    <label class="field"><span>ID da conta do Instagram <small class="muted">(só se o token começar com EAA)</small></span>
                        <input type="text" name="ig_id" inputmode="numeric" pattern="\d*" maxlength="40" autocomplete="off"></label>
                    <p class="muted small">A conta precisa ser <strong>profissional</strong> (Criador de conteúdo ou Empresa).</p>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary btn-small"><?= $acc ? 'Atualizar token' : 'Conectar' ?></button>
            </form>
            <?php if ($acc): ?></details><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <form method="post" action="conta.php" class="inline-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="acao" value="assinatura">
        <label class="field"><span>Assinatura padrão das legendas <small class="muted">(entra no fim de toda legenda; você pode apagar na hora)</small></span>
            <textarea name="assinatura" rows="2" maxlength="500" placeholder="#ADMoema #AssembleiaDeDeus #MinistérioDoBelém"><?= e($user['social_signature'] ?? '') ?></textarea></label>
        <button type="submit" class="btn btn-ghost btn-small">Salvar assinatura</button>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <h2>Sessões</h2>
    <p class="muted">Último acesso: <?= $user['last_login_at'] ? e(date('d/m/Y H:i', strtotime($user['last_login_at']))) : '—' ?>
        <?= $user['last_login_ip'] ? '· IP ' . e($user['last_login_ip']) : '' ?></p>
    <form method="post" action="conta.php">
        <?= Csrf::field() ?>
        <input type="hidden" name="acao" value="sair_todos">
        <button type="submit" class="btn btn-ghost">Encerrar sessões em outros aparelhos</button>
    </form>
</section>
<?php render_footer(['app.js', 'qrcode.js', 'conta.js']);
