<?php
/**
 * Configuração do Ministério de Mídia ADMoema
 *
 * Este é o ÚNICO arquivo que você precisa ajustar.
 * Edite pelo cPanel → Gerenciador de Arquivos → botão "Edit".
 */

// -------------------------------------------------------------
// 1) SENHA DO PAINEL ADMINISTRATIVO (/admin.html)
//    Troque por uma senha sua. Ela é usada apenas para ver as
//    respostas e o registro de ações.
// -------------------------------------------------------------
const SENHA_ADMIN = 'Midia@ADMoema2026';

// -------------------------------------------------------------
// 2) BANCO DE DADOS
//    'sqlite' → não precisa configurar nada. O arquivo do banco é
//               criado automaticamente na pasta /dados (protegida).
//    'mysql'  → use se preferir um banco MySQL criado no cPanel
//               (cPanel → Bancos de Dados MySQL). Preencha abaixo.
// -------------------------------------------------------------
const BANCO_TIPO = 'sqlite';

// Usado apenas quando BANCO_TIPO = 'mysql'
const MYSQL_HOST    = 'localhost';
const MYSQL_BANCO   = 'usuario_admoema';   // ex.: cpaneluser_admoema
const MYSQL_USUARIO = 'usuario_admoema';   // ex.: cpaneluser_midia
const MYSQL_SENHA   = '';

// -------------------------------------------------------------
// 3) ENDEREÇO DO SITE (opcional)
//    Deixe vazio para detectar automaticamente. Preencha só se o
//    compartilhamento nas redes mostrar a imagem errada, ex.:
//    'https://www.seusite.com.br/midia'
// -------------------------------------------------------------
const URL_SITE = '';

// -------------------------------------------------------------
// 4) FUSO HORÁRIO usado nas datas dos registros
// -------------------------------------------------------------
const FUSO_HORARIO = 'America/Sao_Paulo';
