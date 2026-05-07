<?php
// GET  /api/user/prompt — получить системный промпт
// PATCH /api/user/prompt — сохранить системный промпт
$userId = requireAuth();
$db     = Database::get();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT ai_prompt FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    jsonResponse(['ai_prompt' => $row['ai_prompt'] ?? '']);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $body   = getBody();
    $prompt = trim($body['ai_prompt'] ?? '');

    if (mb_strlen($prompt) > 2000) {
        jsonError('Промпт слишком длинный (максимум 2000 символов)');
    }

    $db->prepare('UPDATE users SET ai_prompt = ? WHERE id = ?')
       ->execute([$prompt ?: null, $userId]);

    jsonResponse(['ok' => true]);
}

jsonError('Method not allowed', 405);