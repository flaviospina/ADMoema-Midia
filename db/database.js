'use strict';

const path = require('path');
const fs = require('fs');
const Database = require('better-sqlite3');

const DB_PATH = process.env.DB_PATH || path.join(__dirname, '..', 'data', 'admoema-midia.db');

let db;

function abrir() {
  if (db) return db;
  fs.mkdirSync(path.dirname(DB_PATH), { recursive: true });
  db = new Database(DB_PATH);
  const schema = fs.readFileSync(path.join(__dirname, 'schema.sql'), 'utf8');
  db.exec(schema);
  return db;
}

const TIPOS_ACAO = new Set([
  'pagina_visitada',
  'secao_vista',
  'cta_clicado',
  'formulario_iniciado',
  'formulario_enviado',
  'formulario_erro',
  'admin_acesso',
]);

function registrarAcao({ tipo, detalhe = null, respostaId = null, sessao = null, ip = null, userAgent = null }) {
  const conn = abrir();
  const stmt = conn.prepare(`
    INSERT INTO acoes (tipo, detalhe, resposta_id, sessao, ip, user_agent)
    VALUES (@tipo, @detalhe, @respostaId, @sessao, @ip, @userAgent)
  `);
  const info = stmt.run({
    tipo,
    detalhe: detalhe == null ? null : JSON.stringify(detalhe),
    respostaId,
    sessao,
    ip,
    userAgent,
  });
  return info.lastInsertRowid;
}

function salvarResposta({ nome, contato, frentes, sabeFazer, querAprender, projetoAjudar }) {
  const conn = abrir();
  const stmt = conn.prepare(`
    INSERT INTO respostas (nome, contato, frentes, sabe_fazer, quer_aprender, projeto_ajudar)
    VALUES (@nome, @contato, @frentes, @sabeFazer, @querAprender, @projetoAjudar)
  `);
  const info = stmt.run({
    nome,
    contato: contato || null,
    frentes: JSON.stringify(frentes || []),
    sabeFazer,
    querAprender,
    projetoAjudar,
  });
  return info.lastInsertRowid;
}

function listarRespostas({ limite = 500 } = {}) {
  const conn = abrir();
  return conn
    .prepare(`SELECT * FROM respostas ORDER BY criado_em DESC, id DESC LIMIT ?`)
    .all(limite)
    .map((r) => ({ ...r, frentes: JSON.parse(r.frentes || '[]') }));
}

function listarAcoes({ limite = 500, tipo = null } = {}) {
  const conn = abrir();
  const rows = tipo
    ? conn.prepare(`SELECT * FROM acoes WHERE tipo = ? ORDER BY id DESC LIMIT ?`).all(tipo, limite)
    : conn.prepare(`SELECT * FROM acoes ORDER BY id DESC LIMIT ?`).all(limite);
  return rows.map((a) => ({ ...a, detalhe: a.detalhe ? JSON.parse(a.detalhe) : null }));
}

function resumo() {
  const conn = abrir();
  const totalRespostas = conn.prepare(`SELECT COUNT(*) AS n FROM respostas`).get().n;
  const porTipo = conn.prepare(`SELECT tipo, COUNT(*) AS n FROM acoes GROUP BY tipo ORDER BY n DESC`).all();
  const visitasUnicas = conn
    .prepare(`SELECT COUNT(DISTINCT sessao) AS n FROM acoes WHERE tipo = 'pagina_visitada' AND sessao IS NOT NULL`)
    .get().n;
  const frentes = {};
  for (const r of listarRespostas({ limite: 100000 })) {
    for (const f of r.frentes) frentes[f] = (frentes[f] || 0) + 1;
  }
  return { totalRespostas, visitasUnicas, acoesPorTipo: porTipo, frentesMaisMarcadas: frentes };
}

module.exports = {
  abrir,
  DB_PATH,
  TIPOS_ACAO,
  registrarAcao,
  salvarResposta,
  listarRespostas,
  listarAcoes,
  resumo,
};
