<?php
/**
 * API administrativa (protegida pela SENHA_ADMIN de config.php).
 *   admin.php?recurso=respostas
 *   admin.php?recurso=acoes[&tipo=...]
 *   admin.php?recurso=resumo
 *   admin.php?recurso=csv&senha=...   (download das respostas)
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

autenticar_admin();
$recurso = $_GET['recurso'] ?? 'resumo';

switch ($recurso) {
    case 'respostas':
        registrar_acao('admin_acesso', ['recurso' => 'respostas']);
        responder(200, ['ok' => true, 'respostas' => listar_respostas()]);

    case 'acoes':
        $tipo = texto($_GET['tipo'] ?? null, 40) ?: null;
        responder(200, ['ok' => true, 'acoes' => listar_acoes(1000, $tipo)]);

    case 'resumo':
        responder(200, ['ok' => true, 'resumo' => resumo()]);

    case 'csv':
        registrar_acao('admin_acesso', ['recurso' => 'csv']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="respostas-midia-admoema-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF"; // BOM para o Excel abrir com acentos
        $saida = fopen('php://output', 'w');
        fputcsv($saida, ['id', 'criado_em', 'nome', 'contato', 'frentes', 'sabe_fazer', 'quer_aprender', 'projeto_ajudar'], ';');
        foreach (array_reverse(listar_respostas(100000)) as $r) {
            fputcsv($saida, [$r['id'], $r['criado_em'], $r['nome'], $r['contato'], implode(', ', $r['frentes']), $r['sabe_fazer'], $r['quer_aprender'], $r['projeto_ajudar']], ';');
        }
        fclose($saida);
        exit;

    default:
        responder(404, ['ok' => false, 'erro' => 'Recurso não encontrado.']);
}
