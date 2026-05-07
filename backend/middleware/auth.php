<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Проверяет JWT из cookie, возвращает user_id.
 * Если токен невалидный — сразу отвечает 401 и завершает скрипт.
 */
function requireAuth(): int {
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
