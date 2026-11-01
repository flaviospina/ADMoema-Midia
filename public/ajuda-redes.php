<?php
// public/ajuda-redes.php — passo a passo para cada pessoa conectar as próprias redes sociais
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

Auth::requireUploader();
render_header('Ajuda: redes sociais', ['active' => 'conta']);
?>
<div class="page-head">
    <div>
        <p class="crumb"><a href="conta.php#redes">Minha conta</a> / Ajuda</p>
        <h1>Como conectar o Facebook e o Instagram</h1>
        <p class="muted">Feito uma vez por pessoa, leva uns 10 a 15 minutos. Use o computador.</p>
    </div>
</div>

<section class="panel help">
    <h2>Antes de começar</h2>
    <ul class="help-steps">
        <li>Por que precisa disso? A Meta (dona do Facebook e do Instagram) só deixa um sistema publicar com uma <strong>chave de acesso (token)</strong> que você mesmo gera, com a sua conta.</li>
        <li><strong>Facebook:</strong> funciona com <strong>Páginas</strong> (ex.: página do ministério), não com perfil pessoal. Você precisa ser administrador da Página.</li>
        <li><strong>Instagram:</strong> a conta precisa ser <strong>profissional</strong>. No app: Configurações → Tipo de conta e ferramentas → Mudar para conta profissional (é grátis).</li>
        <li>Você vai criar um “app” no site de desenvolvedores da Meta. É só um cadastro; não precisa programar nem pagar, e não precisa de aprovação da Meta para usar na sua própria conta.</li>
        <li>O token funciona como uma senha: <strong>não mande para ninguém</strong>. Cole apenas em Minha conta → Redes sociais. Ele fica criptografado e só você o usa.</li>
    </ul>
</section>

<section class="panel help">
    <h2>1. Criar o app (uma vez só, serve para as duas redes)</h2>
    <ol class="help-steps">
        <li>Acesse <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener noreferrer">developers.facebook.com/apps</a> e entre com seu Facebook. Se for o primeiro acesso, aceite os termos de desenvolvedor.</li>
        <li>Clique em <strong>Criar app</strong>. Nome: por exemplo “Mídia ADMoema – (seu nome)”.</li>
        <li>Nos casos de uso, marque os que for usar:
            <ul>
                <li><strong>Gerenciar tudo na sua Página</strong> (para o Facebook);</li>
                <li><strong>Gerenciar mensagens e conteúdo no Instagram</strong> (para o Instagram).</li>
            </ul>
        </li>
        <li>Conclua a criação. O app fica em “modo de desenvolvimento”, que é suficiente para publicar nas contas em que você é administrador.</li>
    </ol>
</section>

<section class="panel help">
    <h2>2. Instagram (o mais simples)</h2>
    <ol class="help-steps">
        <li>No painel do app, abra o caso de uso do Instagram → <strong>Configuração da API com login do Instagram</strong>.</li>
        <li>Na etapa <strong>Gerar tokens de acesso</strong>, clique em <strong>Adicionar conta</strong> e entre com a conta do Instagram que vai publicar. Autorize as permissões, principalmente <em>instagram_business_content_publish</em>.</li>
        <li>Clique em <strong>Gerar token</strong> ao lado da conta e copie o token (ele começa com <code>IGAA</code>).</li>
        <li>No sistema: Minha conta → Redes sociais → Instagram → cole o token → <strong>Conectar</strong>. O campo “ID da conta” pode ficar vazio.</li>
    </ol>
    <p class="muted small">Esse token vale 60 dias, mas o sistema renova sozinho a cada publicação (desde que você publique pelo menos uma vez a cada ~50 dias). Se expirar, gere outro do mesmo jeito.</p>
</section>

<section class="panel help">
    <h2>3. Página do Facebook (token que não expira)</h2>
    <ol class="help-steps">
        <li>Abra o <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener noreferrer">Explorador da Graph API</a>. Em “Aplicativo da Meta”, escolha o app que você criou.</li>
        <li>Em “Usuário ou Página”, escolha <strong>Obter token de acesso do usuário</strong> e marque as permissões
            <code>pages_show_list</code>, <code>pages_read_engagement</code> e <code>pages_manage_posts</code>. Clique em <strong>Generate Access Token</strong> e autorize, escolhendo a Página.</li>
        <li>Copie o token gerado e cole no <a href="https://developers.facebook.com/tools/debug/accesstoken/" target="_blank" rel="noopener noreferrer">Depurador de token</a>. Clique em <strong>Depurar</strong> e, embaixo, em <strong>Estender token de acesso</strong>. Copie o token estendido.</li>
        <li>Volte ao Explorador, cole o token estendido no campo “Token de acesso”, digite <code>me/accounts</code> no campo de consulta e clique em <strong>Enviar</strong>.</li>
        <li>Na resposta, encontre a sua Página e copie o valor de <code>"access_token"</code> dela (é longo e começa com <code>EAA</code>). Esse é o <strong>token da Página</strong> e não expira.</li>
        <li>No sistema: Minha conta → Redes sociais → Facebook → cole → <strong>Conectar</strong>. O sistema mostra o nome da Página se tudo estiver certo.</li>
    </ol>
</section>

<section class="panel help">
    <h2>Bom saber</h2>
    <ul class="help-steps">
        <li><strong>Formato:</strong> o sistema envia a foto em JPG, sem dados de localização. No Instagram, fotos muito altas ou muito largas são recortadas ao centro para caber entre 4:5 e 1,91:1. Para escolher o enquadramento, use “Melhorar foto → Recortar → Instagram 4:5” antes.</li>
        <li><strong>Limite da Meta:</strong> até 100 publicações por dia no Instagram por API.</li>
        <li><strong>Sem configurar nada:</strong> no celular, o botão <strong>Compartilhar</strong> do visualizador abre o menu do aparelho (WhatsApp, Instagram, Stories etc.) com a foto pronta.</li>
        <li><strong>Erro de token expirado ou sem permissão:</strong> gere um novo token seguindo os passos e use “Trocar o token” em Minha conta.</li>
        <li><strong>Saiu do ministério?</strong> Clique em “Desconectar”: o token é apagado do sistema. Para revogar também na Meta: Facebook → Configurações → Apps e sites.</li>
    </ul>
</section>
<?php render_footer(['app.js']);
