<?php
declare(strict_types=1);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db/database.php';
require_once __DIR__ . '/middleware/auth.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $status = 400): never {
    jsonResponse(['error' => $message], $status);
}

function getBody(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

// ── CORS (не нужен на одном домене, но пусть будет для dev) ──────────────────
if (APP_ENV === 'development') {
    header('Access-Control-Allow-Origin: http://localhost:5173');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ── Роутинг ───────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Убираем префикс /api
$path = preg_replace('#^/api#', '', $uri);
$path = rtrim($path, '/') ?: '/';

// Разбиваем путь: /transactions/42 → ['transactions', '42']
$segments = array_values(array_filter(explode('/', ltrim($path, '/'))));

$resource = $segments[0] ?? '';
$id       = isset($segments[1]) ? (int)$segments[1] : null;
$sub      = $segments[2] ?? null; // для /ai/chat, /auth/callback и т.д.

match(true) {

    // ── Auth ──────────────────────────────────────────────────────────────────
    $resource === 'auth' && $segments[1] === 'google'   => require __DIR__ . '/api/auth/google.php',
    $resource === 'auth' && $segments[1] === 'callback' => require __DIR__ . '/api/auth/callback.php',
    $resource === 'auth' && $segments[1] === 'logout'   => require __DIR__ . '/api/auth/logout.php',
    $resource === 'auth' && $segments[1] === 'me'       => require __DIR__ . '/api/auth/me.php',
        // ── User settings ─────────────────────────────────────────────────────────
    $resource === 'user' && $segments[1] === 'prompt' => require __DIR__ . '/api/user/prompt.php',

    // ── Transactions ──────────────────────────────────────────────────────────
    $resource === 'transactions' && $method === 'GET'   && !$id => require __DIR__ . '/api/transactions/index.php',
    $resource === 'transactions' && $method === 'POST'  && !$id => require __DIR__ . '/api/transactions/import.php',
    $resource === 'transactions' && $method === 'PATCH' && $id  => require __DIR__ . '/api/transactions/update.php',
    $resource === 'transactions' && $method === 'DELETE' && !$id => require __DIR__ . '/api/transactions/clear.php',

    // ── Tags ──────────────────────────────────────────────────────────────────
    $resource === 'tags' && $method === 'GET'    => require __DIR__ . '/api/tags/index.php',
    $resource === 'tags' && $method === 'POST'   => require __DIR__ . '/api/tags/index.php',
    $resource === 'tags' && $method === 'DELETE' => require __DIR__ . '/api/tags/index.php',

    // ── Stats ─────────────────────────────────────────────────────────────────
    $resource === 'stats' && $method === 'GET'   => require __DIR__ . '/api/stats/index.php',

    // ── AI ────────────────────────────────────────────────────────────────────
    $resource === 'ai' && $segments[1] === 'chat' => require __DIR__ . '/api/ai/chat.php',
    $resource === 'ai' && $segments[1] === 'tag'  => require __DIR__ . '/api/ai/tag.php',
 
    // ── Parse (PDF парсеры) ───────────────────────────────────────────────────
    $resource === 'ai' && $segments[1] === 'parse' => require __DIR__ . '/api/parse/ai.php',

    // ── 404 ───────────────────────────────────────────────────────────────────
    default => jsonError('Маршрут не найден', 404),
};
