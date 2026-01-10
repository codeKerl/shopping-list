<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

requireAuth();

$pdo = db();
$stateData = loadState($pdo);
jsonResponse([
    'state' => $stateData['state'],
    'revision' => $stateData['revision']
]);
