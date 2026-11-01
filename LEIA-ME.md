# Mídia ADMoema — Envio de Fotos

Sistema do Ministério de Multimídia da AD Ministério do Belém (Setor 124 Moema) para
enviar e organizar as fotos dos cultos e eventos. Este é o primeiro módulo do sistema
único da igreja e está, por enquanto, na branch `feature/envio-fotos`.

## O que faz

- **Login seguro** com verificação em duas etapas (2FA), obrigatória para administradores.
- **Pasta automática por evento**: o usuário escolhe a data e o sistema sugere o nome
  pelo dia da semana. Antes de criar, sempre pergunta se pode usar o nome sugerido ou
  se a pessoa prefere escrever outro.

  | Dia | Nome sugerido |
  |---|---|
  | Domingo | Culto com a Família |
  | Terça-feira | Culto de Ensino |
  | Quarta-feira | Círculo de Oração |
  | Quinta-feira | Culto Público |
  | Sábado | Culto dos Jovens · Culto dos Adolescentes · Culto dos Jovens e Adolescentes (escolha) |
  | Segunda e sexta | Sem culto fixo: o usuário escreve o nome |

  No servidor, a pasta fica assim: `storage/fotos/2026/2026-11-01_culto-com-a-familia/`.
  Se já existir pasta na data, o sistema mostra e oferece enviar para ela.
- **Envio de várias fotos de uma vez** pelo celular ou computador, em partes de 2 MB
  (passa pelo limite do HostGator). Se a internet cair, continua de onde parou.
- **Cadastro de cada foto** no banco: quem enviou, quando, tamanho, dimensões, data em
  que foi tirada (EXIF) e impressão digital SHA-256 (impede fotos repetidas na pasta).
- Galeria com miniaturas, download individual e **download da pasta inteira em .zip**.
- Gestão de usuários e registro de atividades (inclusive tentativas de invasão).

### Novidades da versão 1.1

- **Área de visualização para os membros da igreja**: novo perfil *Membro da igreja (visualiza)*.
  Entra com login, vê os eventos e as fotos em tela cheia (setas, deslizar no celular), baixa
  e melhora as fotos. Não envia, não exclui e não publica. Recebe sempre a versão sem dados
  de localização (GPS/EXIF), nunca o arquivo original.
- **Melhorar foto** (para todos): editor com um botão por ferramenta: *Automático*, *Brilho*
  (exposição e brilho), *Contraste* (e efeito fosco), *Cor* (saturação e vibração),
  *Temperatura* (e matiz), *Luz e sombra* (clarear sombras, recuperar partes estouradas),
  *Nitidez*, *Vinheta*, *Estilos* (12 prontos, incluindo “Igreja (pouca luz)”, Retrato, Cinema,
  P&B, Vintage), *Recortar* (1:1, Instagram 4:5, Stories 9:16, 16:9, 3:2, com arraste para enquadrar)
  e *Girar/Espelhar*. Tem “segure para ver o antes”, desfazer e voltar ao original.
  O processamento é feito no próprio navegador (não pesa no servidor). A equipe da mídia salva
  a versão melhorada na pasta (a original é mantida, com selo “Editada”); membros baixam.
- **Publicar nas redes sociais**: cada pessoa da equipe conecta a **própria** Página do Facebook
  e/ou conta profissional do Instagram em *Minha conta → Redes sociais* (o administrador não
  precisa configurar nada e não vê os tokens). Depois, no visualizador: *Publicar nas redes*,
  escolhe as contas, escreve a legenda (com assinatura padrão) e publica. Mostra o link da
  publicação e marca a foto como “Publicada”. Passo a passo para gerar os tokens: página
  *Ajuda* dentro do sistema (`ajuda-redes.php`).
- **Compartilhar pelo celular** (sem configurar nada): abre o menu do aparelho (WhatsApp,
  Instagram, Stories…) com a foto pronta.

## Segurança aplicada

| Ameaça | Proteção |
|---|---|
| Senha roubada | 2FA por aplicativo (Google/Microsoft Authenticator), códigos de recuperação de uso único, código não pode ser reutilizado |
| Força bruta | Bloqueio progressivo da conta (5 erros → 5 min, 10, 20… até 24 h) e bloqueio do IP (10 erros em 15 min) |
| Senhas fracas | Mínimo de 10 caracteres com 3 tipos, lista de senhas comuns bloqueada (inclui "jesus123", "senha123"…), opção de checar vazamentos (Have I Been Pwned) |
| Vazamento do banco | Senhas com Argon2id; segredo do 2FA criptografado com libsodium (APP_KEY) |
| Descobrir e-mails cadastrados | Mesma mensagem e mesmo tempo de resposta para e-mail inexistente e senha errada |
| Sequestro de sessão | Cookie `__Host-` HttpOnly + Secure + SameSite=Strict, ID trocado no login e a cada 15 min, expira com 30 min parado ou 12 h no total, preso ao navegador |
| Sessão de aparelho perdido | "Encerrar sessões em outros aparelhos"; trocar senha ou desativar usuário derruba todas as sessões na hora |
| CSRF | Token em todo formulário e chamada; logout só por POST |
| XSS | Toda saída escapada + Content-Security-Policy sem scripts inline ou externos |
| Clickjacking | `frame-ancestors 'none'` e `X-Frame-Options: DENY` |
| Upload malicioso | Extensão + tipo real (finfo) + leitura da imagem; arquivo renomeado com nome aleatório; pastas sem execução; fotos só saem por `api/foto.php` com login |
| Acesso direto aos arquivos | `.htaccess` bloqueia `app/`, `storage/`, `sql/`, `.env`, `.git` (testado no Apache) |
| SQL injection | PDO com prepared statements em todas as consultas |
| Robôs no login | Campo-armadilha invisível |
| Tokens das redes sociais | Criptografados com libsodium (APP_KEY); nunca voltam para a tela; cada token só é usado pela própria pessoa; “Desconectar” apaga |
| Foto pública para o Instagram | O Instagram precisa baixar a foto: o sistema cria uma cópia JPG temporária com nome aleatório de 160 bits, sem metadados, apagada logo após publicar (e em no máximo 1 hora) |
| Membro da igreja | Bloqueado no servidor para envio, criação de pasta, exclusão, .zip e publicação (não só escondido na tela) |

## Instalação no HostGator (sem terminal)

### Já tinha a versão 1.0 instalada?
Importe `sql/atualizacao-1.1.sql` no phpMyAdmin (uma vez), envie os arquivos novos por cima e
acrescente no `.env`: `META_GRAPH_VERSION=v26.0`. Instalação nova: siga os passos abaixo.

### 1. Banco de dados
1. cPanel → **Bancos de dados MySQL** → crie o banco (ex.: `admoema_midia`).
2. Crie um usuário com senha forte e **adicione ao banco** com *Todos os privilégios*.
3. cPanel → **phpMyAdmin** → selecione o banco → aba **Importar** → envie `sql/schema.sql`.

### 2. Arquivos
1. Baixe o projeto do GitHub (botão **Code → Download ZIP**, escolhendo a branch `feature/envio-fotos`).
2. cPanel → **Gerenciador de Arquivos** → entre em `public_html` → crie a pasta `midia`.
3. Envie o ZIP para dentro de `public_html/midia` e use **Extrair**. A pasta deve ficar com
   `app/`, `public/`, `storage/`, `sql/`, `.htaccess` diretamente dentro de `midia/`
   (se o ZIP criar uma subpasta, mova o conteúdo um nível acima).
4. Ative "Mostrar arquivos ocultos" (Configurações, no canto superior direito) para ver o `.htaccess`.

### 3. Configuração
1. Copie `.env.example` para `.env` (botão direito → Copiar) e edite:
   - `APP_URL=https://admoema.com.br/midia`
   - `DB_NAME`, `DB_USER`, `DB_PASS` (o HostGator coloca o prefixo da conta, ex.: `usuario_midia`)
   - `INSTALL_TOKEN=` uma frase longa só sua
   - deixe `APP_KEY` vazio por enquanto
2. cPanel → **Selecionar versão do PHP** → PHP **8.2**, com as extensões `pdo_mysql`,
   `sodium`, `fileinfo`, `gd`, `mbstring`, `zip` e `exif` marcadas.
3. Confirme que o domínio tem SSL ativo (cPanel → **SSL/TLS Status**).

### 4. Instalar
1. Acesse `https://admoema.com.br/midia/instalar.php`.
2. Se aparecer uma `APP_KEY` gerada, copie para o `.env` e recarregue. **Guarde essa chave**:
   sem ela, o 2FA de todos precisa ser refeito.
3. Corrija o que estiver em vermelho, preencha o token, seus dados e crie o administrador.
4. Entre, leia o QR Code com o aplicativo autenticador e **guarde os códigos de recuperação**.
5. A instalação trava sozinha. Por garantia, apague o `instalar.php` do servidor.

### 5. Recomendado: fotos fora do public_html
Crie `/home/SEU_USUARIO/midia_storage` (fora do `public_html`), mova para lá o conteúdo da
pasta `storage/` e preencha `STORAGE_PATH=/home/SEU_USUARIO/midia_storage` no `.env`.
Assim, mesmo um erro de configuração do Apache não expõe as fotos.

## Uso no dia a dia

- **Administrador**: em *Usuários*, cadastra nome e e-mail; o sistema gera uma senha
  provisória para enviar à pessoa no WhatsApp privado. No primeiro acesso ela cria a senha dela.
- **Esqueceu a senha / perdeu o celular**: o administrador usa *Nova senha* ou *Resetar 2FA*.
- **Excluir foto**: quem enviou ou o administrador. A exclusão esconde a foto, mas o arquivo
  continua no servidor (dá para recuperar pelo banco, se necessário).
- **Registro**: mostra logins, envios, exclusões e alertas de segurança.

## Estrutura

```
app/        Classes PHP (Auth, Session, Totp, FolderService, PhotoService…) — bloqueada na web
public/     Páginas e API (único conteúdo acessível pelo navegador)
storage/    fotos/, thumbs/, tmp/, logs/ — bloqueada na web
sql/        schema.sql
```

Stack: PHP 8.2 puro + MySQL (PDO), JS e CSS próprios (sem CDN), compatível com MySQL 5.7.
