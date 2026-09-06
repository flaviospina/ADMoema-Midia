<?php
/**
 * Registro de ações enviadas pelo navegador
 * (visita, seção vista, clique em CTA, formulário iniciado).
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

exigir_metodo('POST');
$b = corpo_json();

$tipo = texto($b['tipo'] ?? null, 40);
if (!in_array($tipo, TIPOS_ACAO, true) || in_array($tipo, ['formulario_enviado', 'admin_acesso'], true)) {
    responder(400, ['ok' => false, 'erro' => 'Tipo de ação inválido.']);
}

$detalhe = $b['detalhe'] ?? null;
if ($detalhe !== null && (!is_array($detalhe) || strlen(json_encode($detalhe)) > 2000)) {
    $detalhe = null;
}
$sessao = texto($b['sessao'] ?? null, 64) ?: null;

$id = registrar_acao($tipo, $detalhe, null, $sessao);
responder(201, ['ok' => true, 'id' => $id]);
