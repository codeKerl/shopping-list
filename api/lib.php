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
    $pdo->exec('CREATE TABLE IF NOT EXISTS app_meta (id INTEGER PRIMARY KEY CHECK (id = 1), revision INTEGER NOT NULL DEFAULT 0, locale TEXT NOT NULL DEFAULT "de", active_list_id TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS categories (id TEXT PRIMARY KEY, name TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS units (id TEXT PRIMARY KEY, name TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS products (id TEXT PRIMARY KEY, name TEXT NOT NULL, category_id TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS stores (id TEXT PRIMARY KEY, name TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS store_category_order (store_id TEXT NOT NULL, category_id TEXT NOT NULL, position INTEGER NOT NULL, PRIMARY KEY (store_id, category_id))');
    $pdo->exec('CREATE TABLE IF NOT EXISTS lists (id TEXT PRIMARY KEY, created_at TEXT NOT NULL, store_id TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS list_items (id TEXT PRIMARY KEY, list_id TEXT NOT NULL, product_id TEXT NOT NULL, checked INTEGER NOT NULL DEFAULT 0, quantity INTEGER NOT NULL DEFAULT 1, note TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS product_seq (id INTEGER PRIMARY KEY AUTOINCREMENT)');

    $stmt = $pdo->query('SELECT COUNT(*) AS count FROM app_meta');
    $count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    if ($count === 0) {
        $pdo->prepare('INSERT INTO app_meta (id, revision, locale, active_list_id) VALUES (1, 0, "de", NULL)')->execute();
    }
}

function loadState(PDO $pdo): array
{
    $meta = $pdo->query('SELECT revision, locale, active_list_id FROM app_meta WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if (!$meta) {
        $meta = ['revision' => 0, 'locale' => 'de', 'active_list_id' => null];
    }
    $state = [
        'categories' => [],
        'units' => [],
        'products' => [],
        'stores' => [],
        'lists' => [],
        'activeListId' => $meta['active_list_id'] ?? null,
        'revision' => (string)$meta['revision'],
        'locale' => $meta['locale'] ?? 'de'
    ];

    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
    $state['categories'] = $categories ?: [];

    $units = $pdo->query('SELECT id, name FROM units ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
    $state['units'] = $units ?: [];

    $products = $pdo->query('SELECT id, name, category_id AS categoryId FROM products ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
    $state['products'] = $products ?: [];

    $stores = $pdo->query('SELECT id, name FROM stores ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
    $storesWithOrder = [];
    foreach ($stores as $store) {
        $stmt = $pdo->prepare('SELECT category_id FROM store_category_order WHERE store_id = :store_id ORDER BY position');
        $stmt->execute(['store_id' => $store['id']]);
        $order = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $store['categoryOrder'] = $order ?: [];
        $storesWithOrder[] = $store;
    }
    $state['stores'] = $storesWithOrder;

    $lists = $pdo->query('SELECT id, created_at, store_id FROM lists ORDER BY datetime(created_at) DESC')->fetchAll(PDO::FETCH_ASSOC);
    $listsById = [];
    foreach ($lists as $list) {
        $listsById[$list['id']] = [
            'id' => $list['id'],
            'createdAt' => $list['created_at'],
            'storeId' => $list['store_id'] ?: null,
            'items' => []
        ];
    }
    if (!empty($listsById)) {
        $stmt = $pdo->query('SELECT id, list_id, product_id, checked, quantity, note FROM list_items ORDER BY rowid');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!isset($listsById[$row['list_id']])) {
                continue;
            }
            $listsById[$row['list_id']]['items'][] = [
                'id' => $row['id'],
                'productId' => $row['product_id'],
                'checked' => (bool)$row['checked'],
                'quantity' => (int)$row['quantity'],
                'note' => $row['note']
            ];
        }
    }
    $state['lists'] = array_values($listsById);
    $state['syncQueueCount'] = null;

    return ['state' => $state, 'revision' => (int)$meta['revision']];
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
    $apiKey = getenv('SHOPPINGLIST_API_KEY');
    $basicUser = getenv('BASIC_AUTH_USER');
    $basicPass = getenv('BASIC_AUTH_PASS');

    if (!$apiKey && !$basicUser && !$basicPass) {
        return;
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($authHeader === '') {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    }
    if ($authHeader === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) === 'authorization') {
                $authHeader = (string)$value;
                break;
            }
        }
    }
    if ($apiKey && $authHeader === ('Bearer ' . $apiKey)) {
        return;
    }

    $user = $_SERVER['PHP_AUTH_USER'] ?? null;
    $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
    if ($basicUser && $basicPass && $user === $basicUser && $pass === $basicPass) {
        return;
    }

    if ($basicUser && $basicPass) {
        header('WWW-Authenticate: Basic realm=\"Sync\"');
    }
    jsonResponse(['error' => 'unauthorized'], 401);
}
