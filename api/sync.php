<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

requireAuth();

$input = readJsonInput();
$events = $input['events'] ?? [];
if (!is_array($events)) {
    jsonResponse(['error' => 'invalid events'], 400);
}

$pdo = db();
$pdo->beginTransaction();

$meta = $pdo->query('SELECT revision, locale, active_list_id FROM app_meta WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
$revision = (int)($meta['revision'] ?? 0);
$activeListId = $meta['active_list_id'] ?? null;

$categoriesById = [];
foreach ($pdo->query('SELECT id FROM categories')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $categoriesById[(string)$row['id']] = true;
}

$unitsById = [];
foreach ($pdo->query('SELECT id FROM units')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $unitsById[(string)$row['id']] = true;
}

$productsByName = [];
$productsById = [];
foreach ($pdo->query('SELECT id, name, category_id FROM products')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $productsById[(string)$row['id']] = $row;
    $productsByName[normalizeName((string)$row['name'])] = (string)$row['id'];
}

$listsById = [];
foreach ($pdo->query('SELECT id, created_at, store_id FROM lists')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $listsById[(string)$row['id']] = $row;
}

$productIdMap = [];

$addProduct = function (string $name, ?string $categoryId) use ($pdo, &$productsByName, &$productsById): string {
    $key = normalizeName($name);
    if ($key !== '' && isset($productsByName[$key])) {
        $existingId = $productsByName[$key];
        if ($categoryId) {
            $stmt = $pdo->prepare('UPDATE products SET category_id = :category_id WHERE id = :id');
            $stmt->execute(['category_id' => $categoryId, 'id' => $existingId]);
        }
        return $existingId;
    }
    $id = nextProductId($pdo);
    $stmt = $pdo->prepare('INSERT INTO products (id, name, category_id) VALUES (:id, :name, :category_id)');
    $stmt->execute([
        'id' => $id,
        'name' => $name,
        'category_id' => $categoryId
    ]);
    $productsByName[$key] = $id;
    $productsById[$id] = ['id' => $id, 'name' => $name, 'category_id' => $categoryId];
    return $id;
};

$ensureList = function (string $listId) use ($pdo, &$listsById): array {
    if (isset($listsById[$listId])) {
        return $listsById[$listId];
    }
    $createdAt = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
    $stmt = $pdo->prepare('INSERT INTO lists (id, created_at, store_id) VALUES (:id, :created_at, NULL)');
    $stmt->execute(['id' => $listId, 'created_at' => $createdAt]);
    $listsById[$listId] = ['id' => $listId, 'created_at' => $createdAt, 'store_id' => null];
    return $listsById[$listId];
};

$mapProductId = function (?string $localId) use (&$productIdMap): ?string {
    if ($localId === null) {
        return null;
    }
    return $productIdMap[$localId] ?? $localId;
};

foreach ($events as $event) {
    if (!is_array($event)) {
        continue;
    }
    $type = $event['type'] ?? '';
    $payload = $event['payload'] ?? [];
    if (!is_array($payload)) {
        $payload = [];
    }

    switch ($type) {
        case 'category:create':
            $category = $payload['category'] ?? null;
            if (!is_array($category) || !isset($category['id'], $category['name'])) {
                break;
            }
            $id = (string)$category['id'];
            if (!isset($categoriesById[$id])) {
                $stmt = $pdo->prepare('INSERT INTO categories (id, name) VALUES (:id, :name)');
                $stmt->execute(['id' => $id, 'name' => (string)$category['name']]);
                $categoriesById[$id] = true;
            }
            break;
        case 'unit:create':
            $unit = $payload['unit'] ?? null;
            if (!is_array($unit) || !isset($unit['id'], $unit['name'])) {
                break;
            }
            $id = (string)$unit['id'];
            if (!isset($unitsById[$id])) {
                $stmt = $pdo->prepare('INSERT INTO units (id, name) VALUES (:id, :name)');
                $stmt->execute(['id' => $id, 'name' => (string)$unit['name']]);
                $unitsById[$id] = true;
            }
            break;
        case 'unit:update':
            $unitId = isset($payload['unitId']) ? (string)$payload['unitId'] : '';
            $name = isset($payload['name']) ? (string)$payload['name'] : '';
            if ($unitId === '' || $name === '') {
                break;
            }
            $stmt = $pdo->prepare('UPDATE units SET name = :name WHERE id = :id');
            $stmt->execute(['name' => $name, 'id' => $unitId]);
            break;
        case 'unit:remove':
            $unitId = isset($payload['unitId']) ? (string)$payload['unitId'] : '';
            if ($unitId === '') {
                break;
            }
            $stmt = $pdo->prepare('DELETE FROM units WHERE id = :id');
            $stmt->execute(['id' => $unitId]);
            break;
        case 'store:create':
            $store = $payload['store'] ?? null;
            if (!is_array($store) || !isset($store['id'], $store['name'])) {
                break;
            }
            $id = (string)$store['id'];
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO stores (id, name) VALUES (:id, :name)');
            $stmt->execute(['id' => $id, 'name' => (string)$store['name']]);
            if (isset($store['categoryOrder']) && is_array($store['categoryOrder'])) {
                $stmt = $pdo->prepare('DELETE FROM store_category_order WHERE store_id = :store_id');
                $stmt->execute(['store_id' => $id]);
                $insert = $pdo->prepare('INSERT INTO store_category_order (store_id, category_id, position) VALUES (:store_id, :category_id, :position)');
                $position = 0;
                foreach ($store['categoryOrder'] as $categoryId) {
                    $insert->execute([
                        'store_id' => $id,
                        'category_id' => (string)$categoryId,
                        'position' => $position
                    ]);
                    $position++;
                }
            }
            break;
        case 'store:order':
            $storeId = isset($payload['storeId']) ? (string)$payload['storeId'] : '';
            $categoryIds = $payload['categoryIds'] ?? [];
            if ($storeId === '' || !is_array($categoryIds)) {
                break;
            }
            $stmt = $pdo->prepare('DELETE FROM store_category_order WHERE store_id = :store_id');
            $stmt->execute(['store_id' => $storeId]);
            $insert = $pdo->prepare('INSERT INTO store_category_order (store_id, category_id, position) VALUES (:store_id, :category_id, :position)');
            $position = 0;
            foreach ($categoryIds as $categoryId) {
                $insert->execute([
                    'store_id' => $storeId,
                    'category_id' => (string)$categoryId,
                    'position' => $position
                ]);
                $position++;
            }
            break;
        case 'product:create':
            $product = $payload['product'] ?? null;
            if (!is_array($product) || !isset($product['name'])) {
                break;
            }
            $localId = isset($product['id']) ? (string)$product['id'] : null;
            $categoryId = isset($product['categoryId']) ? (string)$product['categoryId'] : null;
            if ($categoryId !== null && !isset($categoriesById[$categoryId])) {
                $categoryId = null;
            }
            $name = (string)$product['name'];
            $serverId = $addProduct($name, $categoryId);
            if ($localId !== null) {
                $productIdMap[$localId] = $serverId;
            }
            break;
        case 'product:update':
            $productId = isset($payload['productId']) ? (string)$payload['productId'] : null;
            $categoryId = isset($payload['categoryId']) ? (string)$payload['categoryId'] : null;
            if ($productId === null) {
                break;
            }
            if ($categoryId !== null && !isset($categoriesById[$categoryId])) {
                $categoryId = null;
            }
            $mappedId = $mapProductId($productId);
            $stmt = $pdo->prepare('UPDATE products SET category_id = :category_id WHERE id = :id');
            $stmt->execute(['category_id' => $categoryId, 'id' => $mappedId]);
            break;
        case 'product:remove':
            $productId = isset($payload['productId']) ? (string)$payload['productId'] : null;
            if ($productId === null) {
                break;
            }
            $mappedId = $mapProductId($productId);
            $stmt = $pdo->prepare('DELETE FROM list_items WHERE product_id = :product_id');
            $stmt->execute(['product_id' => $mappedId]);
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $stmt->execute(['id' => $mappedId]);
            break;
        case 'list:create':
            $list = $payload['list'] ?? null;
            if (!is_array($list) || !isset($list['id'])) {
                break;
            }
            $listId = (string)$list['id'];
            if (!isset($listsById[$listId])) {
                $createdAt = $list['createdAt'] ?? (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
                $stmt = $pdo->prepare('INSERT INTO lists (id, created_at, store_id) VALUES (:id, :created_at, :store_id)');
                $stmt->execute([
                    'id' => $listId,
                    'created_at' => $createdAt,
                    'store_id' => $list['storeId'] ?? null
                ]);
                $listsById[$listId] = ['id' => $listId, 'created_at' => $createdAt, 'store_id' => $list['storeId'] ?? null];
            }
            break;
        case 'list:store:set':
            $listId = isset($payload['listId']) ? (string)$payload['listId'] : '';
            $storeId = isset($payload['storeId']) ? (string)$payload['storeId'] : null;
            if ($listId === '') {
                break;
            }
            $ensureList($listId);
            $stmt = $pdo->prepare('UPDATE lists SET store_id = :store_id WHERE id = :id');
            $stmt->execute(['store_id' => $storeId ?: null, 'id' => $listId]);
            break;
        case 'list:item:add':
            $listId = isset($payload['listId']) ? (string)$payload['listId'] : '';
            $item = $payload['item'] ?? null;
            $productName = isset($payload['productName']) ? (string)$payload['productName'] : '';
            if ($listId === '' || !is_array($item) || !isset($item['id'], $item['productId'])) {
                break;
            }
            $ensureList($listId);
            $productId = $mapProductId((string)$item['productId']);
            $productId = $productId ?: null;
            if ($productId === null || !isset($productsById[$productId])) {
                if ($productName !== '') {
                    $productId = $addProduct($productName, null);
                }
            }
            if ($productId === null) {
                break;
            }
            $stmt = $pdo->prepare('SELECT 1 FROM list_items WHERE id = :id');
            $stmt->execute(['id' => (string)$item['id']]);
            if ($stmt->fetchColumn()) {
                break;
            }
            $stmt = $pdo->prepare('INSERT INTO list_items (id, list_id, product_id, checked, quantity, note) VALUES (:id, :list_id, :product_id, :checked, :quantity, :note)');
            $stmt->execute([
                'id' => (string)$item['id'],
                'list_id' => $listId,
                'product_id' => $productId,
                'checked' => (int)($item['checked'] ?? false),
                'quantity' => max(1, (int)($item['quantity'] ?? 1)),
                'note' => $item['note'] ?? null
            ]);
            break;
        case 'list:item:toggle':
            $listId = isset($payload['listId']) ? (string)$payload['listId'] : '';
            $itemId = isset($payload['itemId']) ? (string)$payload['itemId'] : '';
            $checked = isset($payload['checked']) ? (int)(bool)$payload['checked'] : null;
            if ($listId === '' || $itemId === '' || $checked === null) {
                break;
            }
            $stmt = $pdo->prepare('UPDATE list_items SET checked = :checked WHERE id = :id AND list_id = :list_id');
            $stmt->execute(['checked' => $checked, 'id' => $itemId, 'list_id' => $listId]);
            break;
        case 'list:item:quantity':
            $listId = isset($payload['listId']) ? (string)$payload['listId'] : '';
            $itemId = isset($payload['itemId']) ? (string)$payload['itemId'] : '';
            $quantity = isset($payload['quantity']) ? (int)$payload['quantity'] : 1;
            if ($listId === '' || $itemId === '') {
                break;
            }
            $stmt = $pdo->prepare('UPDATE list_items SET quantity = :quantity WHERE id = :id AND list_id = :list_id');
            $stmt->execute([
                'quantity' => max(1, $quantity),
                'id' => $itemId,
                'list_id' => $listId
            ]);
            break;
        case 'list:item:remove':
            $listId = isset($payload['listId']) ? (string)$payload['listId'] : '';
            $itemId = isset($payload['itemId']) ? (string)$payload['itemId'] : '';
            if ($listId === '' || $itemId === '') {
                break;
            }
            $stmt = $pdo->prepare('DELETE FROM list_items WHERE id = :id AND list_id = :list_id');
            $stmt->execute(['id' => $itemId, 'list_id' => $listId]);
            break;
        case 'list:remove':
            $listId = isset($payload['listId']) ? (string)$payload['listId'] : '';
            if ($listId === '') {
                break;
            }
            $stmt = $pdo->prepare('DELETE FROM list_items WHERE list_id = :list_id');
            $stmt->execute(['list_id' => $listId]);
            $stmt = $pdo->prepare('DELETE FROM lists WHERE id = :id');
            $stmt->execute(['id' => $listId]);
            if ($activeListId === $listId) {
                $activeListId = null;
            }
            break;
        case 'settings:locale':
            $locale = isset($payload['locale']) ? (string)$payload['locale'] : 'de';
            $allowed = ['de', 'en', 'fr', 'es'];
            $locale = in_array($locale, $allowed, true) ? $locale : 'de';
            $stmt = $pdo->prepare('UPDATE app_meta SET locale = :locale WHERE id = 1');
            $stmt->execute(['locale' => $locale]);
            break;
        default:
            break;
    }
}

$revision = $revision + 1;
$stmt = $pdo->prepare('UPDATE app_meta SET revision = :revision, active_list_id = :active_list_id WHERE id = 1');
$stmt->execute(['revision' => $revision, 'active_list_id' => $activeListId]);

$pdo->commit();

jsonResponse([
    'ok' => true,
    'revision' => $revision
]);
