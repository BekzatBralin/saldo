<?php
// PATCH /api/transactions/{id} — обновить тег транзакции

$userId = requireAuth();
$db     = Database::get();
$body   = getBody();

// $id пришёл из роутера через глобальную переменную
global $id;
if (!$id) jsonError('ID не указан');

$tagId = array_key_exists('tag_id', $body) ? ($body['tag_id'] ? (int)$body['tag_id'] : null) : false;
if ($tagId === false) jsonError('Поле tag_id обязательно');

// Убеждаемся что транзакция принадлежит юзеру
$check = $db->prepare('SELECT id FROM transactions WHERE id = ? AND user_id = ?');
$check->execute([$id, $userId]);
if (!$check->fetch()) jsonError('Транзакция не найдена', 404);

$db->prepare('UPDATE transactions SET tag_id = ? WHERE id = ?')
   ->execute([$tagId ?: null, $id]);

jsonResponse(['ok' => true]);
