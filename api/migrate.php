<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

requireAuth();

$pdo = db();

$exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='app_state'")->fetchColumn();
if (!$exists) {
    jsonResponse(['status' => 'no_legacy_table']);
}

$hasData = $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
if ((int)$hasData > 0) {
    jsonResponse(['status' => 'already_migrated']);
}

$legacy = $pdo->query('SELECT json, revision FROM app_state WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
if (!$legacy) {
    jsonResponse(['status' => 'no_legacy_data']);
}

$state = json_decode($legacy['json'], true);
if (!is_array($state)) {
    jsonResponse(['status' => 'invalid_legacy_json'], 500);
}

$pdo->beginTransaction();

$state['categories'] = $state['categories'] ?? [];
$state['units'] = $state['units'] ?? [];
$state['products'] = $state['products'] ?? [];
$state['stores'] = $state['stores'] ?? [];
$state['lists'] = $state['lists'] ?? [];
$state['locale'] = $state['locale'] ?? 'de';

$insertCategory = $pdo->prepare('INSERT INTO categories (id, name) VALUES (:id, :name)');
foreach ($state['categories'] as $category) {
    if (!isset($category['id'], $category['name'])) {
        continue;
    }
    $insertCategory->execute(['id' => (string)$category['id'], 'name' => (string)$category['name']]);
}

$insertUnit = $pdo->prepare('INSERT INTO units (id, name) VALUES (:id, :name)');
foreach ($state['units'] as $unit) {
    if (!isset($unit['id'], $unit['name'])) {
        continue;
    }
    $insertUnit->execute(['id' => (string)$unit['id'], 'name' => (string)$unit['name']]);
}

$insertProduct = $pdo->prepare('INSERT INTO products (id, name, category_id) VALUES (:id, :name, :category_id)');
$maxProductId = 0;
foreach ($state['products'] as $product) {
    if (!isset($product['id'], $product['name'])) {
        continue;
    }
    $id = (string)$product['id'];
    $insertProduct->execute([
        'id' => $id,
        'name' => (string)$product['name'],
        'category_id' => $product['categoryId'] ?? null
    ]);
    if (ctype_digit($id)) {
        $maxProductId = max($maxProductId, (int)$id);
    }
}

$insertStore = $pdo->prepare('INSERT INTO stores (id, name) VALUES (:id, :name)');
$insertStoreOrder = $pdo->prepare('INSERT INTO store_category_order (store_id, category_id, position) VALUES (:store_id, :category_id, :position)');
foreach ($state['stores'] as $store) {
    if (!isset($store['id'], $store['name'])) {
        continue;
    }
    $storeId = (string)$store['id'];
    $insertStore->execute(['id' => $storeId, 'name' => (string)$store['name']]);
    $position = 0;
    foreach (($store['categoryOrder'] ?? []) as $categoryId) {
        $insertStoreOrder->execute([
            'store_id' => $storeId,
            'category_id' => (string)$categoryId,
            'position' => $position
        ]);
        $position++;
    }
}

$insertList = $pdo->prepare('INSERT INTO lists (id, created_at, store_id) VALUES (:id, :created_at, :store_id)');
$insertItem = $pdo->prepare('INSERT INTO list_items (id, list_id, product_id, checked, quantity, note) VALUES (:id, :list_id, :product_id, :checked, :quantity, :note)');
foreach ($state['lists'] as $list) {
    if (!isset($list['id'], $list['createdAt'])) {
        continue;
    }
    $listId = (string)$list['id'];
    $insertList->execute([
        'id' => $listId,
        'created_at' => (string)$list['createdAt'],
        'store_id' => $list['storeId'] ?? null
    ]);
    foreach (($list['items'] ?? []) as $item) {
        if (!isset($item['id'], $item['productId'])) {
            continue;
        }
        $insertItem->execute([
            'id' => (string)$item['id'],
            'list_id' => $listId,
            'product_id' => (string)$item['productId'],
            'checked' => (int)($item['checked'] ?? false),
            'quantity' => max(1, (int)($item['quantity'] ?? 1)),
            'note' => $item['note'] ?? null
        ]);
    }
}

$revision = (int)$legacy['revision'];
$activeListId = $state['activeListId'] ?? null;
$locale = in_array($state['locale'], ['de', 'en', 'fr', 'es'], true) ? $state['locale'] : 'de';
$pdo->prepare('UPDATE app_meta SET revision = :revision, locale = :locale, active_list_id = :active_list_id WHERE id = 1')
    ->execute(['revision' => $revision, 'locale' => $locale, 'active_list_id' => $activeListId]);

if ($maxProductId > 0) {
    $pdo->exec('DELETE FROM product_seq');
    $stmt = $pdo->prepare('INSERT INTO product_seq (id) VALUES (:id)');
    $stmt->execute(['id' => $maxProductId]);
}

$legacyName = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='app_state_legacy'")->fetchColumn();
if (!$legacyName) {
    $pdo->exec('ALTER TABLE app_state RENAME TO app_state_legacy');
}

$pdo->commit();

jsonResponse(['status' => 'migrated', 'revision' => $revision]);
