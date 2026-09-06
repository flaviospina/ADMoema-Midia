'use strict';
// Cria o banco e as tabelas sem iniciar o servidor: `npm run db:init`
const { abrir, DB_PATH } = require('./database');
abrir();
console.log(`Banco de dados pronto em: ${DB_PATH}`);
