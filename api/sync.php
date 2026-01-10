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
$stateData = loadState($pdo);
$state = $stateData['state'];
$revision = $stateData['revision'];

$state['categories'] = $state['categories'] ?? [];
$state['products'] = $state['products'] ?? [];
$state['stores'] = $state['stores'] ?? [];
$state['lists'] = $state['lists'] ?? [];

$productsByName = [];
$productsById = [];
foreach ($state['products'] as $product) {
    if (!isset($product['id'], $product['name'])) {
        continue;
    }
    $productsByName[normalizeName((string)$product['name'])] = (string)$product['id'];
    $productsById[(string)$product['id']] = $product;
}

$categoriesById = [];
foreach ($state['categories'] as $category) {
    if (!isset($category['id'])) {
        continue;
    }
    $categoriesById[(string)$category['id']] = $category;
}

$listsById = [];
foreach ($state['lists'] as $list) {
    if (!isset($list['id'])) {
        continue;
    }
    $listsById[(string)$list['id']] = $list;
}

$productIdMap = [];

$ensureList = function (string $listId) use (&$listsById): array {
    if (isset($listsById[$listId])) {
        return $listsById[$listId];
    }
    $list = [
        'id' => $listId,
        'createdAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        'items' => []
    ];
    $listsById[$listId] = $list;
    return $list;
};

$updateList = function (array $list) use (&$listsById): void {
    if (!isset($list['id'])) {
        return;
    }
    $listsById[(string)$list['id']] = $list;
};

$mapProductId = function (?string $localId) use (&$productIdMap): ?string {
    if ($localId === null) {
        return null;
    }
    return $productIdMap[$localId] ?? $localId;
};

$addProduct = function (string $name, ?string $categoryId) use (&$productsByName, &$productsById, &$state, &$productIdMap, $pdo): string {
    $key = normalizeName($name);
    if ($key !== '' && isset($productsByName[$key])) {
        $existingId = $productsByName[$key];
        if ($categoryId && isset($productsById[$existingId])) {
            $productsById[$existingId]['categoryId'] = $categoryId;
        }
        return $existingId;
    }
    $id = nextProductId($pdo);
    $product = [
        'id' => $id,
        'name' => $name,
        'categoryId' => $categoryId
    ];
    $state['products'][] = $product;
    $productsByName[$key] = $id;
    $productsById[$id] = $product;
    return $id;
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
                $state['categories'][] = $category;
                $categoriesById[$id] = $category;
            }
            break;
        case 'store:create':
            $store = $payload['store'] ?? null;
            if (!is_array($store) || !isset($store['id'], $store['name'])) {
                break;
            }
            $exists = false;
            foreach ($state['stores'] as $existing) {
                if ((string)$existing['id'] === (string)$store['id']) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $store['categoryOrder'] = $store['categoryOrder'] ?? [];
                $state['stores'][] = $store;
            }
            break;
        case 'store:order':
            $storeId = isset($payload['storeId']) ? (string)$payload['storeId'] : '';
            $categoryIds = $payload['categoryIds'] ?? [];
            if ($storeId === '' || !is_array($categoryIds)) {
                break;
            }
            foreach ($state['stores'] as $index => $store) {
                if ((string)$store['id'] === $storeId) {
                    $state['stores'][$index]['categoryOrder'] = array_values($categoryIds);
                    break;
                }
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
            foreach ($state['products'] as $index => $product) {
                if ((string)$product['id'] === $mappedId) {
                    $state['products'][$index]['categoryId'] = $categoryId;
                    break;
                }
            }
            break;
        case 'list:create':
            $list = $payload['list'] ?? null;
            if (!is_array($list) || !isset($list['id'])) {
                break;
            }
            $listId = (string)$list['id'];
            if (!isset($listsById[$listId])) {
                $list['items'] = $list['items'] ?? [];
                $listsById[$listId] = $list;
            }
            break;
        case 'list:store:set':
            $listId = isset($payload['listId']) ? (string)$payload['listId'] : '';
            $storeId = isset($payload['storeId']) ? (string)$payload['storeId'] : null;
            if ($listId === '') {
                break;
            }
            $list = $ensureList($listId);
            $list['storeId'] = $storeId ?: null;
            $updateList($list);
            break;
        case 'list:item:add':
            $listId = isset($payload['listId']) ? (string)$payload['listId'] : '';
            $item = $payload['item'] ?? null;
            $productName = isset($payload['productName']) ? (string)$payload['productName'] : '';
            if ($listId === '' || !is_array($item) || !isset($item['id'], $item['productId'])) {
                break;
            }
            $list = $ensureList($listId);
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
            $exists = false;
            foreach ($list['items'] as $existingItem) {
                if ((string)$existingItem['id'] === (string)$item['id']) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $item['productId'] = $productId;
                $item['checked'] = (bool)($item['checked'] ?? false);
                $list['items'][] = $item;
            }
            $updateList($list);
            break;
        case 'list:item:toggle':
            $listId = isset($payload['listId']) ? (string)$payload['listId'] : '';
            $itemId = isset($payload['itemId']) ? (string)$payload['itemId'] : '';
            $checked = isset($payload['checked']) ? (bool)$payload['checked'] : null;
            if ($listId === '' || $itemId === '' || $checked === null) {
                break;
            }
            $list = $ensureList($listId);
            foreach ($list['items'] as $index => $listItem) {
                if ((string)$listItem['id'] === $itemId) {
                    $list['items'][$index]['checked'] = $checked;
                    break;
                }
            }
            $updateList($list);
            break;
        case 'list:item:remove':
            $listId = isset($payload['listId']) ? (string)$payload['listId'] : '';
            $itemId = isset($payload['itemId']) ? (string)$payload['itemId'] : '';
            if ($listId === '' || $itemId === '') {
                break;
            }
            $list = $ensureList($listId);
            $list['items'] = array_values(array_filter($list['items'], function ($listItem) use ($itemId) {
                return (string)$listItem['id'] !== $itemId;
            }));
            $updateList($list);
            break;
        default:
            break;
    }
}

$state['lists'] = array_values($listsById);

$revision = $revision + 1;
saveState($pdo, $state, $revision);

jsonResponse([
    'ok' => true,
    'revision' => $revision
]);
