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

## Instalação no HostGator (sem terminal)

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
