<?php
declare(strict_types=1);

function db(): PDO
{
    $dbPath = __DIR__ . '/data/app.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA foreign_keys = ON;');
    ensureSchema($pdo);
    return $pdo;
}

function ensureSchema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS app_state (id INTEGER PRIMARY KEY CHECK (id = 1), json TEXT NOT NULL, revision INTEGER NOT NULL DEFAULT 0)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS product_seq (id INTEGER PRIMARY KEY AUTOINCREMENT)');

    $stmt = $pdo->query('SELECT COUNT(*) AS count FROM app_state');
    $count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    if ($count === 0) {
        $empty = json_encode(emptyState());
        $pdo->prepare('INSERT INTO app_state (id, json, revision) VALUES (1, :json, 0)')->execute(['json' => $empty]);
    }
}

function emptyState(): array
{
    return [
        'categories' => [],
        'products' => [],
        'stores' => [],
        'lists' => [],
        'activeListId' => null,
        'revision' => null
    ];
}

function loadState(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT json, revision FROM app_state WHERE id = 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['state' => emptyState(), 'revision' => 0];
    }
    $state = json_decode($row['json'], true);
    if (!is_array($state)) {
        $state = emptyState();
    }
    $state['syncQueueCount'] = null;
    return ['state' => $state, 'revision' => (int)$row['revision']];
}

function saveState(PDO $pdo, array $state, int $revision): void
{
    unset($state['syncQueueCount']);
    $state['revision'] = (string)$revision;
    $json = json_encode($state, JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare('UPDATE app_state SET json = :json, revision = :revision WHERE id = 1');
    $stmt->execute(['json' => $json, 'revision' => $revision]);
}

function nextProductId(PDO $pdo): string
{
    $pdo->exec('INSERT INTO product_seq DEFAULT VALUES');
    return (string)$pdo->lastInsertId();
}

function normalizeName(string $name): string
{
    $name = trim($name);
    return strtolower($name);
}

function readJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireAuth(): void
{
    $apiKey = getenv('API_KEY');
    $basicUser = getenv('BASIC_AUTH_USER');
    $basicPass = getenv('BASIC_AUTH_PASS');

    if (!$apiKey && !$basicUser && !$basicPass) {
        return;
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($apiKey && $authHeader === ('Bearer ' . $apiKey)) {
        return;
    }

    $user = $_SERVER['PHP_AUTH_USER'] ?? null;
    $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
    if ($basicUser && $basicPass && $user === $basicUser && $pass === $basicPass) {
        return;
    }

    header('WWW-Authenticate: Basic realm=\"Sync\"');
    jsonResponse(['error' => 'unauthorized'], 401);
}
