# CLAUDE.md — Mídia ADMoema

Sistema do Ministério de Multimídia da AD Ministério do Belém – Setor 124 Moema.
Responsável: Flávio Roberto Spina (IT Thrive). Branch atual: `feature/envio-fotos`
(módulo de envio de fotos, separado por enquanto; depois será integrado ao sistema único da igreja).

## Regras de trabalho
1. Trabalhe em fases e peça aprovação antes de seguir para a próxima.
2. Produção, não protótipo: validação, erros amigáveis em pt-BR, auditoria e segurança desde o início.
3. Publicação sem terminal: HostGator compartilhado via cPanel (Gerenciador de Arquivos + phpMyAdmin).
   Nada de Composer/npm/SSH em produção. Sem CDN (a CSP só aceita arquivos do próprio site).
4. UI e comentários em pt-BR; identificadores em inglês (snake_case no PHP/SQL, camelCase no JS).
5. Arquivos completos ao entregar alterações.

## Stack e restrições
- PHP 8.2 puro, PDO, MySQL 5.7+/MariaDB (sem CTE, window functions ou CHECK).
- JS vanilla, CSS próprio em `public/assets/css/app.css`. Nada de script ou style inline (CSP).
- HTTP externo só com cURL (`allow_url_fopen` pode estar desligado).
- Uploads em partes (`UPLOAD_CHUNK_MB`, padrão 2 MB).

## Arquitetura
- `public/*.php` = páginas; `public/api/*.php` = endpoints JSON. Toda página começa com
  `require dirname(__DIR__) . '/app/bootstrap.php';` e chama `Auth::requireLogin()` ou `Auth::requireAdmin()`.
- POST sempre com `require_post()` (verifica CSRF). Em fetch, o token vai no cabeçalho `X-CSRF-Token`
  (lido da meta `csrf-token`; use `App.post()` de `app.js`).
- Saída de dado do usuário sempre com `e()`.
- Ações relevantes registradas com `Logger::audit()`.
- Arquivos só são servidos por `api/foto.php` (com login). Nunca link direto para `storage/`.
- Regras de nome das pastas: `app/EventNaming.php` (`SCHEDULE`). Sempre confirmar o nome com o usuário.

## Testes locais
Há MariaDB + PHP embutido: `php -S 127.0.0.1:8080 -t public` com `.env` em `APP_ENV=local`.
Teste o `.htaccess` também no Apache, com o projeto em subpasta (como em `public_html/midia`).

## Próximos módulos previstos (não implementados)
Membros da mídia, escala de eventos, pedidos e aprovação de artes, repositório geral de arquivos,
direito de imagem, integração n8n/WhatsApp.
