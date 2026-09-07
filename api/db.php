<?php
/**
 * Acesso ao banco de dados (SQLite por padrão, MySQL opcional).
 * Cria as tabelas automaticamente na primeira requisição.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

date_default_timezone_set(FUSO_HORARIO);

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

// Ponto de partida do projeto (pedido do Pr. Elias): edição de vídeo das transmissões
const EDICAO_VIDEO = [
    'ja_edito'       => 'Já sei editar vídeos',
    'quero_aprender' => 'Quero aprender a editar',
    'tenho_projeto'  => 'Tenho um projeto específico',
    'ainda_nao'      => 'Ainda não, quero ajudar em outra frente',
];

const TIPOS_ACAO = [
    'pagina_visitada',
    'secao_vista',
    'cta_clicado',
    'formulario_iniciado',
    'formulario_enviado',
    'formulario_erro',
    'admin_acesso',
];

function agora(): string
{
    return date('Y-m-d H:i:s');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $opcoes = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (BANCO_TIPO === 'mysql') {
        if (MYSQL_BANCO === 'SEUUSUARIO_admoema' || MYSQL_SENHA === 'COLOQUE-A-SENHA') {
            throw new RuntimeException('Preencha MYSQL_BANCO, MYSQL_USUARIO e MYSQL_SENHA em api/config.php com os dados criados no cPanel.');
        }
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', MYSQL_HOST, MYSQL_BANCO);
        try {
            $pdo = new PDO($dsn, MYSQL_USUARIO, MYSQL_SENHA, $opcoes);
        } catch (PDOException $e) {
            throw new RuntimeException(explicar_erro_mysql($e), 0, $e);
        }
        criar_tabelas_mysql($pdo);
        return $pdo;
    }

    $pasta = dirname(__DIR__) . '/dados';
    if (!is_dir($pasta)) {
        mkdir($pasta, 0755, true);
    }
    // Protege a pasta contra acesso direto pelo navegador
    $htaccess = $pasta . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n");
    }

    $pdo = new PDO('sqlite:' . $pasta . '/admoema-midia.sqlite', null, null, $opcoes);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    criar_tabelas_sqlite($pdo);
    return $pdo;
}

function explicar_erro_mysql(PDOException $e): string
{
    $m = $e->getMessage();
    if (stripos($m, 'Access denied') !== false)   return 'MySQL recusou usuário/senha. Confira MYSQL_USUARIO e MYSQL_SENHA em api/config.php e se o usuário foi adicionado ao banco no cPanel (Add User To Database, todos os privilégios). Detalhe: ' . $m;
    if (stripos($m, 'Unknown database') !== false) return 'O banco informado em MYSQL_BANCO não existe. Confira o nome exato em cPanel → Bancos de Dados MySQL. Detalhe: ' . $m;
    if (stripos($m, "Connection refused") !== false || stripos($m, 'No such file') !== false || stripos($m, 'getaddrinfo') !== false) return 'Não foi possível conectar em MYSQL_HOST (' . MYSQL_HOST . '). Na HostGator use "localhost". Detalhe: ' . $m;
    return 'Erro de conexão MySQL: ' . $m;
}

function criar_tabelas_sqlite(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS respostas (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            nome            TEXT    NOT NULL,
            contato         TEXT,
            frentes         TEXT    NOT NULL DEFAULT '[]',
            edicao_video    TEXT,
            sabe_fazer      TEXT    NOT NULL,
            quer_aprender   TEXT    NOT NULL,
            projeto_ajudar  TEXT    NOT NULL,
            criado_em       TEXT    NOT NULL
        )");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acoes (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo        TEXT    NOT NULL,
            detalhe     TEXT,
            resposta_id INTEGER REFERENCES respostas(id) ON DELETE SET NULL,
            sessao      TEXT,
            ip          TEXT,
            user_agent  TEXT,
            criado_em   TEXT    NOT NULL
        )");
    // bancos criados antes da coluna edicao_video
    $colunas = array_column($pdo->query('PRAGMA table_info(respostas)')->fetchAll(), 'name');
    if (!in_array('edicao_video', $colunas, true)) $pdo->exec('ALTER TABLE respostas ADD COLUMN edicao_video TEXT');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_acoes_tipo ON acoes (tipo)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_acoes_criado_em ON acoes (criado_em)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_respostas_criado_em ON respostas (criado_em)');
}

function criar_tabelas_mysql(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS respostas (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            nome            VARCHAR(120) NOT NULL,
            contato         VARCHAR(160) NULL,
            frentes         TEXT NOT NULL,
            edicao_video    VARCHAR(40) NULL,
            sabe_fazer      TEXT NOT NULL,
            quer_aprender   TEXT NOT NULL,
            projeto_ajudar  TEXT NOT NULL,
            criado_em       DATETIME NOT NULL,
            INDEX idx_respostas_criado_em (criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $tem = $pdo->query("SHOW COLUMNS FROM respostas LIKE 'edicao_video'")->fetch();
    if (!$tem) $pdo->exec('ALTER TABLE respostas ADD COLUMN edicao_video VARCHAR(40) NULL AFTER frentes');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acoes (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tipo        VARCHAR(40) NOT NULL,
            detalhe     TEXT NULL,
            resposta_id INT UNSIGNED NULL,
            sessao      VARCHAR(64) NULL,
            ip          VARCHAR(64) NULL,
            user_agent  VARCHAR(512) NULL,
            criado_em   DATETIME NOT NULL,
            INDEX idx_acoes_tipo (tipo),
            INDEX idx_acoes_criado_em (criado_em),
            CONSTRAINT fk_acoes_resposta FOREIGN KEY (resposta_id) REFERENCES respostas(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// ---------------------------------------------------------------------
// Operações
// ---------------------------------------------------------------------

function registrar_acao(string $tipo, $detalhe = null, ?int $respostaId = null, ?string $sessao = null): int
{
    $stmt = db()->prepare('INSERT INTO acoes (tipo, detalhe, resposta_id, sessao, ip, user_agent, criado_em)
                           VALUES (:tipo, :detalhe, :resposta_id, :sessao, :ip, :ua, :criado_em)');
    $stmt->execute([
        ':tipo'        => $tipo,
        ':detalhe'     => $detalhe === null ? null : json_encode($detalhe, JSON_UNESCAPED_UNICODE),
        ':resposta_id' => $respostaId,
        ':sessao'      => $sessao,
        ':ip'          => ip_cliente(),
        ':ua'          => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512) ?: null,
        ':criado_em'   => agora(),
    ]);
    return (int) db()->lastInsertId();
}

function salvar_resposta(array $d): int
{
    $stmt = db()->prepare('INSERT INTO respostas (nome, contato, frentes, edicao_video, sabe_fazer, quer_aprender, projeto_ajudar, criado_em)
                           VALUES (:nome, :contato, :frentes, :edicao, :sabe, :quer, :projeto, :criado_em)');
    $stmt->execute([
        ':nome'      => $d['nome'],
        ':contato'   => $d['contato'] !== '' ? $d['contato'] : null,
        ':frentes'   => json_encode($d['frentes'], JSON_UNESCAPED_UNICODE),
        ':edicao'    => $d['edicaoVideo'] ?: null,
        ':sabe'      => $d['sabeFazer'],
        ':quer'      => $d['querAprender'],
        ':projeto'   => $d['projetoAjudar'],
        ':criado_em' => agora(),
    ]);
    return (int) db()->lastInsertId();
}

function listar_respostas(int $limite = 5000): array
{
    $stmt = db()->prepare('SELECT * FROM respostas ORDER BY id DESC LIMIT :limite');
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    $linhas = $stmt->fetchAll();
    foreach ($linhas as &$l) {
        $l['id'] = (int) $l['id'];
        $l['frentes'] = json_decode($l['frentes'] ?: '[]', true) ?: [];
    }
    return $linhas;
}

function listar_acoes(int $limite = 1000, ?string $tipo = null): array
{
    if ($tipo) {
        $stmt = db()->prepare('SELECT * FROM acoes WHERE tipo = :tipo ORDER BY id DESC LIMIT :limite');
        $stmt->bindValue(':tipo', $tipo);
    } else {
        $stmt = db()->prepare('SELECT * FROM acoes ORDER BY id DESC LIMIT :limite');
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    $linhas = $stmt->fetchAll();
    foreach ($linhas as &$l) {
        $l['id'] = (int) $l['id'];
        $l['resposta_id'] = $l['resposta_id'] === null ? null : (int) $l['resposta_id'];
        $l['detalhe'] = $l['detalhe'] ? json_decode($l['detalhe'], true) : null;
    }
    return $linhas;
}

function resumo(): array
{
    $pdo = db();
    $total = (int) $pdo->query('SELECT COUNT(*) FROM respostas')->fetchColumn();
    $porTipo = $pdo->query('SELECT tipo, COUNT(*) AS n FROM acoes GROUP BY tipo ORDER BY n DESC')->fetchAll();
    foreach ($porTipo as &$p) { $p['n'] = (int) $p['n']; }
    $unicos = (int) $pdo->query("SELECT COUNT(DISTINCT sessao) FROM acoes WHERE tipo = 'pagina_visitada' AND sessao IS NOT NULL")->fetchColumn();
    $frentes = [];
    foreach ($pdo->query('SELECT frentes FROM respostas') as $r) {
        foreach (json_decode($r['frentes'] ?: '[]', true) ?: [] as $f) {
            $frentes[$f] = ($frentes[$f] ?? 0) + 1;
        }
    }
    arsort($frentes);
    $edicao = [];
    foreach ($pdo->query('SELECT edicao_video, COUNT(*) AS n FROM respostas GROUP BY edicao_video') as $r) {
        $edicao[$r['edicao_video'] ?: 'nao_informado'] = (int) $r['n'];
    }
    return [
        'totalRespostas'     => $total,
        'edicaoVideo'        => (object) $edicao,
        'edicaoVideoRotulos' => (object) EDICAO_VIDEO,
        'visitasUnicas'      => $unicos,
        'acoesPorTipo'       => $porTipo,
        'frentesMaisMarcadas' => (object) $frentes,
    ];
}

// ---------------------------------------------------------------------
// Utilidades HTTP
// ---------------------------------------------------------------------

function ip_cliente(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    return $ip ? mb_substr($ip, 0, 64) : null;
}

function corpo_json(): array
{
    $bruto = file_get_contents('php://input') ?: '';
    $dados = json_decode($bruto, true);
    return is_array($dados) ? $dados : [];
}

function responder(int $status, array $dados): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function texto($v, int $max): string
{
    if (!is_string($v)) return '';
    $v = preg_replace('/\s+/u', ' ', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
}

function texto_longo($v, int $max): string
{
    if (!is_string($v)) return '';
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    return mb_substr(trim($v), 0, $max);
}

function exigir_metodo(string $metodo): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $metodo) {
        responder(405, ['ok' => false, 'erro' => 'Método não permitido.']);
    }
}

function autenticar_admin(): void
{
    $enviada = $_SERVER['HTTP_X_SENHA_ADMIN'] ?? ($_GET['senha'] ?? '');
    if (SENHA_ADMIN === '' || SENHA_ADMIN === 'TROQUE-ESTA-SENHA') {
        responder(503, ['ok' => false, 'erro' => 'Defina SENHA_ADMIN em api/config.php.']);
    }
    if (!is_string($enviada) || !hash_equals(SENHA_ADMIN, $enviada)) {
        responder(401, ['ok' => false, 'erro' => 'Senha inválida.']);
    }
}

// Converte exceções em respostas JSON legíveis (sem vazar detalhes internos)
set_exception_handler(function (Throwable $e): void {
    error_log('[admoema-midia] ' . $e->getMessage());
    $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Erro interno no servidor. Abra api/instalar.php para diagnosticar.';
    responder(500, ['ok' => false, 'erro' => $msg]);
});
