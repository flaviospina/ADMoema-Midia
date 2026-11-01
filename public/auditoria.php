<?php
// public/auditoria.php — registro de ações e tentativas de acesso (somente administradores)
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

Auth::requireAdmin();

$labels = [
    'login' => 'Entrou', 'logout' => 'Saiu', 'login_falhou' => 'Senha errada', 'conta_bloqueada' => 'Conta bloqueada',
    'login_ip_bloqueado' => 'IP bloqueado', 'login_robo' => 'Robô bloqueado', '2fa_falhou' => 'Código 2FA errado',
    '2fa_ativado' => '2FA ativado', '2fa_desativado' => '2FA desativado', '2fa_resetado_admin' => '2FA resetado',
    '2fa_codigo_recuperacao_usado' => 'Usou código de recuperação', '2fa_novos_codigos' => 'Novos códigos 2FA',
    'senha_trocada' => 'Trocou a senha', 'troca_senha_falhou' => 'Senha atual errada', 'senha_redefinida_admin' => 'Senha provisória gerada',
    'usuario_criado' => 'Usuário criado', 'usuario_ativado' => 'Usuário reativado', 'usuario_desativado' => 'Usuário desativado',
    'usuario_papel' => 'Perfil alterado', 'usuario_desbloqueado' => 'Usuário desbloqueado', 'sessoes_encerradas' => 'Encerrou sessões',
    'pasta_criada' => 'Pasta criada', 'foto_enviada' => 'Foto enviada', 'foto_excluida' => 'Foto excluída',
    'foto_baixada' => 'Foto baixada', 'pasta_baixada_zip' => 'Pasta baixada (zip)', 'upload_rejeitado' => 'Arquivo rejeitado',
    'acesso_negado' => 'Acesso negado', 'csrf_invalido' => 'Formulário inválido', 'instalacao' => 'Instalação',
];
$security = ['login_falhou', 'conta_bloqueada', 'login_ip_bloqueado', 'login_robo', '2fa_falhou', 'acesso_negado', 'csrf_invalido', 'upload_rejeitado', 'troca_senha_falhou'];

$filter = ($_GET['f'] ?? '') === 'seguranca' ? 'seguranca' : 'todos';
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 50;
$where = '';
$params = [];
if ($filter === 'seguranca') {
    $where = 'WHERE a.action IN (' . implode(',', array_fill(0, count($security), '?')) . ')';
    $params = $security;
}
$total = (int) Database::value("SELECT COUNT(*) FROM audit_log a $where", $params);
$rows = Database::all(
    "SELECT a.*, u.name AS user_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id $where
      ORDER BY a.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
    $params
);
$pages = max(1, (int) ceil($total / $perPage));

render_header('Registro', ['active' => 'auditoria']);
?>
<div class="page-head">
    <div>
        <h1>Registro de atividades</h1>
        <p class="muted">Tudo o que acontece no sistema, inclusive tentativas de invasão.</p>
    </div>
</div>
<nav class="chips">
    <a href="auditoria.php" class="chip <?= $filter === 'todos' ? 'is-active' : '' ?>">Tudo</a>
    <a href="auditoria.php?f=seguranca" class="chip <?= $filter === 'seguranca' ? 'is-active' : '' ?>">Somente alertas de segurança</a>
</nav>
<section class="panel">
    <div class="table-wrap">
    <table class="table table-compact">
        <thead><tr><th>Quando</th><th>Quem</th><th>Ação</th><th>Detalhes</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr class="<?= in_array($r['action'], $security, true) ? 'is-alert' : '' ?>">
                <td class="nowrap small"><?= e(date('d/m/Y H:i:s', strtotime($r['created_at']))) ?></td>
                <td><?= e($r['user_name'] ?? '—') ?></td>
                <td><?= e($labels[$r['action']] ?? $r['action']) ?></td>
                <td class="small muted"><?= e($r['details'] ?? '') ?></td>
                <td class="small nowrap"><?= e($r['ip']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="muted center">Nada registrado.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="pager">
            <?php if ($page > 1): ?><a href="?f=<?= e($filter) ?>&amp;p=<?= $page - 1 ?>">← Anteriores</a><?php endif; ?>
            <span class="muted small">Página <?= $page ?> de <?= $pages ?></span>
            <?php if ($page < $pages): ?><a href="?f=<?= e($filter) ?>&amp;p=<?= $page + 1 ?>">Mais antigos →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<?php render_footer(['app.js']);
