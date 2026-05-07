<?php
// GET/POST/DELETE /api/tags

$userId = requireAuth();
$db     = Database::get();

// ── GET — список тегов ────────────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $db->prepare('SELECT * FROM tags WHERE user_id = ? ORDER BY is_system DESC, name ASC');
    $stmt->execute([$userId]);
    $tags = $stmt->fetchAll();
    foreach ($tags as &$tag) $tag['is_system'] = (bool) $tag['is_system'];
    jsonResponse($tags);
}

// ── POST — создать тег ────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body  = getBody();
    $name  = trim($body['name']  ?? '');
    $color = trim($body['color'] ?? '#888888');

    if (!$name) jsonError('Название обязательно');
    if (mb_strlen($name) > 64) jsonError('Название слишком длинное');

    // Проверяем дубликат
    $dup = $db->prepare('SELECT id FROM tags WHERE user_id = ? AND name = ?');
    $dup->execute([$userId, $name]);
    if ($dup->fetch()) jsonError('Тег с таким названием уже существует');

    $db->prepare('INSERT INTO tags (user_id, name, color, is_system) VALUES (?,?,?,0)')
       ->execute([$userId, $name, $color]);

    $newId = (int) $db->lastInsertId();
    jsonResponse(['id' => $newId, 'name' => $name, 'color' => $color, 'is_system' => false], 201);
}

// ── DELETE — удалить тег ──────────────────────────────────────────────────────
if ($method === 'DELETE') {
    global $id;
    if (!$id) jsonError('ID не указан');

    // Нельзя удалять системные теги
    $tag = $db->prepare('SELECT is_system FROM tags WHERE id = ? AND user_id = ?');
    $tag->execute([$id, $userId]);
    $tag = $tag->fetch();

    if (!$tag)              jsonError('Тег не найден', 404);
    if ($tag['is_system'])  jsonError('Системные теги нельзя удалять');

    // Транзакции с этим тегом → tag_id = NULL (через ON DELETE SET NULL в схеме)
    $db->prepare('DELETE FROM tags WHERE id = ? AND user_id = ?')->execute([$id, $userId]);

    jsonResponse(['ok' => true]);
}

jsonError('Метод не поддерживается', 405);
