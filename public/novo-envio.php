<?php
// public/novo-envio.php — escolhe a data, sugere o nome da pasta e pede confirmação
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireLogin();
$today = date('Y-m-d');

render_header('Enviar fotos', ['active' => 'novo']);
?>
<div class="page-head">
    <div>
        <h1>Enviar fotos</h1>
        <p class="muted">Primeiro, vamos definir a pasta do evento.</p>
    </div>
</div>

<section class="panel" id="folder-wizard" data-today="<?= e($today) ?>">
    <div class="step">
        <span class="step-num">1</span>
        <label class="field field-inline">
            <span>Data do culto ou evento</span>
            <input type="date" id="event-date" value="<?= e($today) ?>" max="<?= e(date('Y-m-d', strtotime('+1 year'))) ?>" required>
        </label>
    </div>

    <div class="step" id="step-name" hidden>
        <span class="step-num">2</span>
        <div class="step-body">
            <p class="step-title" id="weekday-label"></p>

            <!-- Pastas que já existem nessa data -->
            <div id="existing-box" hidden>
                <p class="muted small">Já existe pasta nesta data. Você pode enviar para ela:</p>
                <ul class="existing-list" id="existing-list"></ul>
                <p class="muted small">Ou crie uma pasta nova:</p>
            </div>

            <!-- Opções de nome (sábado tem mais de uma) -->
            <fieldset id="options-box" class="options" hidden>
                <legend class="sr-only">Nome sugerido</legend>
                <div id="options-list"></div>
            </fieldset>

            <!-- Confirmação do nome sugerido -->
            <div id="confirm-box" class="confirm" hidden>
                <p>Posso criar a pasta com este nome?</p>
                <p class="folder-preview" id="folder-preview"></p>
                <div class="actions">
                    <button type="button" class="btn btn-primary" id="btn-accept">Sim, criar esta pasta</button>
                    <button type="button" class="btn btn-ghost" id="btn-custom">Prefiro escrever outro nome</button>
                </div>
            </div>

            <!-- Nome digitado pelo usuário -->
            <form id="custom-box" class="custom" hidden novalidate>
                <label class="field">
                    <span id="custom-label">Nome da pasta</span>
                    <input type="text" id="custom-name" maxlength="80" placeholder="Ex.: Santa Ceia, Batismo, Congresso de Senhoras">
                </label>
                <p class="muted small">A pasta ficará assim: <strong id="custom-preview">—</strong></p>
                <div class="actions">
                    <button type="submit" class="btn btn-primary" id="btn-create-custom">Criar pasta com este nome</button>
                    <button type="button" class="btn btn-ghost" id="btn-back" hidden>Voltar para a sugestão</button>
                </div>
            </form>

            <div id="wizard-error" class="alert alert-erro" role="alert" hidden></div>
        </div>
    </div>
</section>
<?php render_footer(['app.js', 'novo-envio.js']);
