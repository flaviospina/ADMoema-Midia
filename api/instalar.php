<?php
/**
 * Instalação / verificação do banco de dados.
 * Abra no navegador: seusite.com.br/midia/api/instalar.php
 * Cria o banco e as tabelas (se ainda não existirem) e mostra o que está certo ou errado.
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$itens = [];
$ok = function (string $t, string $d = '') use (&$itens) { $itens[] = ['ok', $t, $d]; };
$erro = function (string $t, string $d = '') use (&$itens) { $itens[] = ['erro', $t, $d]; };
$aviso = function (string $t, string $d = '') use (&$itens) { $itens[] = ['aviso', $t, $d]; };

// 1) PHP e extensões
version_compare(PHP_VERSION, '7.4.0', '>=') ? $ok('PHP ' . PHP_VERSION) : $erro('PHP ' . PHP_VERSION . ' é antigo', 'Selecione PHP 8.x em cPanel → Select PHP Version.');
$extensao = BANCO_TIPO === 'mysql' ? 'pdo_mysql' : 'pdo_sqlite';
extension_loaded($extensao) ? $ok("Extensão $extensao disponível") : $erro("Extensão $extensao ausente", 'Ative em cPanel → Select PHP Version → Extensions.');

// 2) pasta dados (SQLite)
$pasta = dirname(__DIR__) . '/dados';
if (BANCO_TIPO !== 'mysql') {
    if (!is_dir($pasta)) @mkdir($pasta, 0755, true);
    is_dir($pasta) ? $ok('Pasta dados/ existe') : $erro('Pasta dados/ não existe', 'Crie a pasta "dados" ao lado de index.php.');
    is_writable($pasta) ? $ok('Pasta dados/ tem permissão de escrita') : $erro('Pasta dados/ sem permissão de escrita', 'No Gerenciador de Arquivos, botão direito em "dados" → Change Permissions → 755 (ou 775).');
    file_exists($pasta . '/.htaccess') ? $ok('Pasta dados/ protegida (.htaccess)') : $aviso('Sem .htaccess em dados/', 'Será criado automaticamente na primeira gravação.');
}

// 3) banco e tabelas
$tabelas = [];
try {
    require_once __DIR__ . '/db.php';
    restore_exception_handler();
    $pdo = db();
    $tabelas = BANCO_TIPO === 'mysql'
        ? array_map(fn($r) => array_values($r)[0], $pdo->query('SHOW TABLES')->fetchAll())
        : array_column($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(), 'name');
    $ok('Conexão com o banco (' . (BANCO_TIPO === 'mysql' ? 'MySQL ' . MYSQL_BANCO : 'SQLite dados/admoema-midia.sqlite') . ')');
    foreach (['respostas', 'acoes'] as $t) {
        in_array($t, $tabelas, true) ? $ok("Tabela \"$t\" criada") : $erro("Tabela \"$t\" não encontrada");
    }
    $colunas = BANCO_TIPO === 'mysql'
        ? array_column($pdo->query('SHOW COLUMNS FROM respostas')->fetchAll(), 'Field')
        : array_column($pdo->query('PRAGMA table_info(respostas)')->fetchAll(), 'name');
    in_array('edicao_video', $colunas, true) ? $ok('Coluna "edicao_video" presente (ponto de partida · Pr. Elias)') : $erro('Coluna "edicao_video" ausente');
    $nR = (int) $pdo->query('SELECT COUNT(*) FROM respostas')->fetchColumn();
    $nA = (int) $pdo->query('SELECT COUNT(*) FROM acoes')->fetchColumn();
    $ok("Registros: $nR resposta(s) e $nA ação(ões)");
} catch (Throwable $e) {
    $erro('Falha ao criar/abrir o banco', $e->getMessage());
}

// 4) senha do painel
(SENHA_ADMIN === '' || SENHA_ADMIN === 'TROQUE-ESTA-SENHA') ? $erro('SENHA_ADMIN não definida', 'Edite api/config.php.') : (SENHA_ADMIN === 'Midia@ADMoema2026' ? $aviso('Senha do painel ainda é a padrão', 'Recomendado trocar em api/config.php.') : $ok('Senha do painel definida'));

$temErro = (bool) array_filter($itens, fn($i) => $i[0] === 'erro');
$cor = ['ok' => '#2fbf71', 'erro' => '#e5484d', 'aviso' => '#d4a72c'];
$icone = ['ok' => '✓', 'erro' => '✕', 'aviso' => '!'];
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Instalação · Mídia ADMoema</title>
<style>
body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#faf7f0;color:#1f2937;padding:2rem 1rem}
.caixa{max-width:720px;margin:0 auto;background:#fff;border:1px solid #e6dfcd;border-radius:16px;padding:1.5rem 1.75rem}
h1{margin:0 0 .25rem;font-size:1.3rem;color:#0b1f3a}.sub{margin:0 0 1.25rem;color:#64748b}
ul{list-style:none;padding:0;margin:0}li{display:flex;gap:.8rem;padding:.7rem 0;border-bottom:1px solid #f3eee2}
.i{flex:0 0 26px;height:26px;border-radius:50%;color:#fff;font-weight:800;display:grid;place-items:center;font-size:.9rem}
small{display:block;color:#64748b;margin-top:.15rem}
.resumo{margin-top:1.25rem;padding:1rem;border-radius:12px;font-weight:700}
a{color:#0b1f3a}
</style></head><body><div class="caixa">
<h1>Instalação do banco de dados</h1>
<p class="sub">Ministério de Mídia ADMoema · <?= $h(date('d/m/Y H:i')) ?></p>
<ul>
<?php foreach ($itens as [$tipo, $titulo, $det]): ?>
<li><span class="i" style="background:<?= $cor[$tipo] ?>"><?= $icone[$tipo] ?></span><div><?= $h($titulo) ?><?= $det ? '<small>' . $h($det) . '</small>' : '' ?></div></li>
<?php endforeach; ?>
</ul>
<div class="resumo" style="background:<?= $temErro ? '#fde8e8;color:#9b1c1c' : '#e6f7ee;color:#166534' ?>">
<?= $temErro ? 'Há itens para corrigir acima. Depois de ajustar, recarregue esta página.' : 'Tudo pronto. O banco está criado e o site já grava as respostas.' ?>
</div>
<p style="margin-top:1rem"><a href="../">← Voltar ao site</a> · <a href="../admin.html">Abrir o painel</a></p>
</div></body></html>
