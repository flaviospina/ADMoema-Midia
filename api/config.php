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
// 2) BANCO DE DADOS MySQL (phpMyAdmin do servidor)
//    Crie o banco e o usuário em cPanel → Bancos de Dados MySQL®
//    (veja o passo a passo no README) e preencha abaixo.
//    Na HostGator o nome do banco e do usuário começam com o seu
//    usuário do cPanel + "_", ex.: "flavio_admoema".
// -------------------------------------------------------------
const BANCO_TIPO = 'mysql';                 // 'mysql' (recomendado) ou 'sqlite' (arquivo em /dados, sem configurar nada)

const MYSQL_HOST    = 'localhost';          // na HostGator é "localhost"
const MYSQL_BANCO   = 'SEUUSUARIO_admoema'; // nome do banco criado no cPanel
const MYSQL_USUARIO = 'SEUUSUARIO_midia';   // usuário do banco criado no cPanel
const MYSQL_SENHA   = 'COLOQUE-A-SENHA';    // senha definida ao criar o usuário

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
