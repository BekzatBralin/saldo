<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Проверяет JWT из cookie, возвращает user_id.
 * Если токен невалидный — сразу отвечает 401 и завершает скрипт.
 */
function requireAuth(): int {
    if (APP_ENV === 'development') {
        $db = Database::get();
        $id = $db->query("SELECT id FROM users LIMIT 1")->fetchColumn();
        if (!$id) {
            $db->query("INSERT INTO users (google_id, email, name) VALUES ('dev1', 'dev@example.com', 'Dev User')");
            $id = $db->lastInsertId();
            // create default tags
            $db->query("INSERT INTO tags (user_id, name, color, is_system) VALUES 
                ($id, 'Зарплата', '#4ecb81', 1),
                ($id, 'Покупки', '#f07070', 1),
                ($id, 'Перевод', '#4a90d9', 1),
                ($id, 'Наличные', '#ff9500', 1),
                ($id, 'Коммуналка', '#a82c2c', 1),
                ($id, 'Здоровье', '#8e44ad', 1),
                ($id, 'Доход', '#2ecc71', 1),
                ($id, 'Кредит', '#e74c3c', 1),
                ($id, 'Депозит', '#3498db', 1),
                ($id, 'Получено', '#27ae60', 1),
                ($id, 'Транспорт', '#f1c40f', 1),
                ($id, 'Еда', '#e67e22', 1),
                ($id, 'Другое', '#95a5a6', 1)");
        }
        return (int) $id;
    }

    $token = $_COOKIE['kaspi_token'] ?? null;

    if (!$token) {
        jsonError('Не авторизован', 401);
    }

    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        return (int) $decoded->sub;
    } catch (Exception $e) {
        jsonError('Токен недействителен', 401);
    }
}

/**
 * Создаёт JWT для юзера.
 */
function createToken(int $userId): string {
    $payload = [
        'sub' => $userId,
        'iat' => time(),
        'exp' => time() + JWT_TTL,
    ];
    return JWT::encode($payload, JWT_SECRET, 'HS256');
}

/**
 * Устанавливает httpOnly cookie с JWT.
 */
function setAuthCookie(string $token): void {
    setcookie('kaspi_token', $token, [
        'expires'  => time() + JWT_TTL,
        'path'     => '/',
        'secure'   => APP_ENV === 'production',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Удаляет cookie (logout).
 */
function clearAuthCookie(): void {
    setcookie('kaspi_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
