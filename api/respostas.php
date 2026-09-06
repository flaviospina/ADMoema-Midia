<?php
/**
 * Envio do formulário das três perguntas.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

exigir_metodo('POST');
$b = corpo_json();

$dados = [
    'nome'          => texto($b['nome'] ?? null, 120),
    'contato'       => texto($b['contato'] ?? null, 160),
    'sabeFazer'     => texto_longo($b['sabeFazer'] ?? null, 2000),
    'querAprender'  => texto_longo($b['querAprender'] ?? null, 2000),
    'projetoAjudar' => texto_longo($b['projetoAjudar'] ?? null, 2000),
    'frentes'       => [],
];
if (isset($b['frentes']) && is_array($b['frentes'])) {
    $dados['frentes'] = array_values(array_filter($b['frentes'], fn($f) => is_string($f) && in_array($f, FRENTES, true)));
}
$sessao = texto($b['sessao'] ?? null, 64) ?: null;

$erros = [];
if (mb_strlen($dados['nome']) < 2)          $erros['nome'] = 'Informe seu nome.';
if (mb_strlen($dados['sabeFazer']) < 3)     $erros['sabeFazer'] = 'Conte o que você já sabe fazer.';
if (mb_strlen($dados['querAprender']) < 3)  $erros['querAprender'] = 'Conte o que gostaria de aprender.';
if (mb_strlen($dados['projetoAjudar']) < 3) $erros['projetoAjudar'] = 'Conte qual projeto gostaria de ajudar.';

if ($erros) {
    registrar_acao('formulario_erro', ['campos' => array_keys($erros)], null, $sessao);
    responder(422, ['ok' => false, 'erros' => $erros]);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $respostaId = salvar_resposta($dados);
    registrar_acao('formulario_enviado', [
        'frentes'  => $dados['frentes'],
        'tamanhos' => [mb_strlen($dados['sabeFazer']), mb_strlen($dados['querAprender']), mb_strlen($dados['projetoAjudar'])],
    ], $respostaId, $sessao);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

$primeiroNome = explode(' ', $dados['nome'])[0];
responder(201, ['ok' => true, 'id' => $respostaId, 'mensagem' => "Obrigado, {$primeiroNome}! Sua resposta foi registrada."]);
