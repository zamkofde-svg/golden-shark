<?php
/**
 * Тексты сайта (CMS-lite).
 * GET  — публично: {"content": {ключ: значение, ...}} — только переопределённые.
 * POST — только админ: {"items": {ключ: значение, ...}} — пустое значение удаляет
 *        переопределение (возврат к тексту по умолчанию из HTML).
 */
require __DIR__ . '/lib.php';

$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS site_content (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query('SELECT k, v FROM site_content')->fetchAll(PDO::FETCH_KEY_PAIR);
    json_out(['content' => $rows ?: new stdClass()]);
}

only_method('POST');
require_admin();
$body = json_in();
$items = $body['items'] ?? null;
if (!is_array($items)) {
    json_out(['error' => 'bad_request', 'message' => 'Ожидается items {ключ: значение}'], 400);
}

$up  = $pdo->prepare('INSERT INTO site_content (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
$del = $pdo->prepare('DELETE FROM site_content WHERE k = ?');
$saved = 0; $cleared = 0;
foreach ($items as $k => $v) {
    $k = trim((string) $k);
    if ($k === '' || !preg_match('/^[a-z0-9_.\-]{1,64}$/i', $k)) continue;
    $v = trim((string) $v);
    if ($v === '') { $del->execute([$k]); $cleared++; }
    else { $up->execute([$k, $v]); $saved++; }
}
json_out(['ok' => true, 'saved' => $saved, 'cleared' => $cleared]);
