<?php
// GET /api/auth/me — текущий юзер

$userId = requireAuth();
$db     = Database::get();

$stmt = $db->prepare('SELECT id, email, name, avatar, balance, balance_freedom, balance_other, created_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) jsonError('Пользователь не найден', 404);

jsonResponse($user);