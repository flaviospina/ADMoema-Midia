'use strict';
// Exporta respostas e ações para CSV em data/exports/: `npm run db:export`
const fs = require('fs');
const path = require('path');
const { listarRespostas, listarAcoes } = require('./database');

function csv(rows, colunas) {
  const esc = (v) => {
    if (v == null) return '';
    const s = typeof v === 'string' ? v : JSON.stringify(v);
    return /[",\n;]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  return [colunas.join(';'), ...rows.map((r) => colunas.map((c) => esc(r[c])).join(';'))].join('\n');
}

const dir = path.join(__dirname, '..', 'data', 'exports');
fs.mkdirSync(dir, { recursive: true });
const carimbo = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');

const respostas = listarRespostas({ limite: 1000000 });
const acoes = listarAcoes({ limite: 1000000 });

const fR = path.join(dir, `respostas-${carimbo}.csv`);
const fA = path.join(dir, `acoes-${carimbo}.csv`);
fs.writeFileSync(fR, '﻿' + csv(respostas, ['id', 'nome', 'contato', 'frentes', 'sabe_fazer', 'quer_aprender', 'projeto_ajudar', 'criado_em']));
fs.writeFileSync(fA, '﻿' + csv(acoes, ['id', 'tipo', 'detalhe', 'resposta_id', 'sessao', 'ip', 'user_agent', 'criado_em']));
console.log(`Exportado: ${fR} (${respostas.length} respostas)`);
console.log(`Exportado: ${fA} (${acoes.length} ações)`);
