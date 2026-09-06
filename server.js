'use strict';

const path = require('path');
const crypto = require('crypto');
const express = require('express');
const db = require('./db/database');

const PORT = Number(process.env.PORT) || 3000;
const ADMIN_TOKEN = process.env.ADMIN_TOKEN || '';

const FRENTES = [
  'Áudio e sonorização',
  'Projeção',
  'Transmissão ao vivo',
  'Câmeras e direção',
  'Fotografia',
  'Produção de vídeos',
  'Design e identidade visual',
  'Redes sociais',
  'Sistemas e tecnologia',
];

const app = express();
app.disable('x-powered-by');
app.set('trust proxy', true);
app.use(express.json({ limit: '64kb' }));

// Arquivos estáticos da landing page
app.use(express.static(path.join(__dirname, 'public'), { extensions: ['html'], maxAge: '1h' }));

// ---------- utilitários ----------
function contexto(req) {
  return {
    sessao: texto(req.body && req.body.sessao, 64) || null,
    ip: req.ip || null,
    userAgent: texto(req.get('user-agent'), 512) || null,
  };
}

function texto(v, max) {
  if (typeof v !== 'string') return '';
  return v.replace(/\s+/g, ' ').trim().slice(0, max);
}

function textoLongo(v, max) {
  if (typeof v !== 'string') return '';
  return v.replace(/\r\n?/g, '\n').trim().slice(0, max);
}

function autenticarAdmin(req, res, next) {
  const enviado = req.get('x-admin-token') || req.query.token || '';
  if (!ADMIN_TOKEN) {
    return res.status(503).json({ ok: false, erro: 'ADMIN_TOKEN não configurado no servidor (.env).' });
  }
  const a = Buffer.from(String(enviado));
  const b = Buffer.from(ADMIN_TOKEN);
  const igual = a.length === b.length && crypto.timingSafeEqual(a, b);
  if (!igual) return res.status(401).json({ ok: false, erro: 'Token inválido.' });
  next();
}

// ---------- API pública ----------
app.get('/api/health', (_req, res) => res.json({ ok: true, agora: new Date().toISOString() }));

app.get('/api/frentes', (_req, res) => res.json({ ok: true, frentes: FRENTES }));

// Registro de ações vindas do navegador (visita, seção vista, clique em CTA, formulário iniciado)
app.post('/api/acoes', (req, res) => {
  const tipo = texto(req.body && req.body.tipo, 40);
  if (!db.TIPOS_ACAO.has(tipo) || tipo === 'formulario_enviado' || tipo === 'admin_acesso') {
    return res.status(400).json({ ok: false, erro: 'Tipo de ação inválido.' });
  }
  let detalhe = req.body && req.body.detalhe;
  if (detalhe != null && (typeof detalhe !== 'object' || JSON.stringify(detalhe).length > 2000)) detalhe = null;
  const id = db.registrarAcao({ tipo, detalhe, ...contexto(req) });
  res.status(201).json({ ok: true, id });
});

// Envio do formulário das três perguntas
app.post('/api/respostas', (req, res) => {
  const b = req.body || {};
  const nome = texto(b.nome, 120);
  const contato = texto(b.contato, 160);
  const sabeFazer = textoLongo(b.sabeFazer, 2000);
  const querAprender = textoLongo(b.querAprender, 2000);
  const projetoAjudar = textoLongo(b.projetoAjudar, 2000);
  const frentes = Array.isArray(b.frentes) ? b.frentes.filter((f) => FRENTES.includes(f)) : [];

  const erros = {};
  if (nome.length < 2) erros.nome = 'Informe seu nome.';
  if (sabeFazer.length < 3) erros.sabeFazer = 'Conte o que você já sabe fazer.';
  if (querAprender.length < 3) erros.querAprender = 'Conte o que gostaria de aprender.';
  if (projetoAjudar.length < 3) erros.projetoAjudar = 'Conte qual projeto gostaria de ajudar.';

  const ctx = contexto(req);
  if (Object.keys(erros).length) {
    db.registrarAcao({ tipo: 'formulario_erro', detalhe: { campos: Object.keys(erros) }, ...ctx });
    return res.status(422).json({ ok: false, erros });
  }

  const conn = db.abrir();
  const salvar = conn.transaction(() => {
    const respostaId = db.salvarResposta({ nome, contato, frentes, sabeFazer, querAprender, projetoAjudar });
    db.registrarAcao({
      tipo: 'formulario_enviado',
      detalhe: { frentes, tamanhos: [sabeFazer.length, querAprender.length, projetoAjudar.length] },
      respostaId,
      ...ctx,
    });
    return respostaId;
  });
  const id = salvar();
  res.status(201).json({ ok: true, id, mensagem: `Obrigado, ${nome.split(' ')[0]}! Sua resposta foi registrada.` });
});

// ---------- API administrativa (protegida por token) ----------
app.get('/api/admin/respostas', autenticarAdmin, (req, res) => {
  db.registrarAcao({ tipo: 'admin_acesso', detalhe: { recurso: 'respostas' }, ...contexto(req) });
  res.json({ ok: true, respostas: db.listarRespostas({ limite: 5000 }) });
});

app.get('/api/admin/acoes', autenticarAdmin, (req, res) => {
  const tipo = texto(req.query.tipo, 40) || null;
  res.json({ ok: true, acoes: db.listarAcoes({ limite: 1000, tipo }) });
});

app.get('/api/admin/resumo', autenticarAdmin, (_req, res) => {
  res.json({ ok: true, resumo: db.resumo() });
});

app.get('/admin', (_req, res) => res.sendFile(path.join(__dirname, 'public', 'admin.html')));

// 404 em JSON para a API, HTML para o resto
app.use((req, res) => {
  if (req.path.startsWith('/api/')) return res.status(404).json({ ok: false, erro: 'Rota não encontrada.' });
  res.status(404).sendFile(path.join(__dirname, 'public', 'index.html'));
});

app.use((err, _req, res, _next) => {
  console.error(err);
  res.status(err.type === 'entity.parse.failed' ? 400 : 500).json({ ok: false, erro: 'Erro interno.' });
});

if (require.main === module) {
  db.abrir();
  app.listen(PORT, () => {
    console.log(`Ministério de Mídia ADMoema → http://localhost:${PORT}`);
    console.log(`Banco de dados: ${db.DB_PATH}`);
    if (!ADMIN_TOKEN) console.log('Aviso: defina ADMIN_TOKEN para habilitar o painel /admin.');
  });
}

module.exports = app;
