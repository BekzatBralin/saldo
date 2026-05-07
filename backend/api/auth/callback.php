<?php
// GET /api/auth/callback — Google вернул code, обмениваем на профиль

$code = $_GET['code'] ?? null;
if (!$code) jsonError('Код авторизации отсутствует');

// ── 1. Меняем code на access_token ───────────────────────────────────────────
$tokenRes = curlPost('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]);

if (!isset($tokenRes['access_token'])) {
    jsonError('Не удалось получить токен Google: ' . ($tokenRes['error_description'] ?? 'unknown'));
}

// ── 2. Получаем профиль юзера ─────────────────────────────────────────────────
$profile = curlGet(
    'https://www.googleapis.com/oauth2/v2/userinfo',
    $tokenRes['access_token']
);

if (!isset($profile['id'])) {
    jsonError('Не удалось получить профиль Google');
}

// ── 3. Создаём или обновляем юзера в БД ──────────────────────────────────────
$db = Database::get();

$stmt = $db->prepare('SELECT id FROM users WHERE google_id = ?');
$stmt->execute([$profile['id']]);
$user = $stmt->fetch();

if ($user) {
    // Обновляем имя и аватар (могли измениться)
    $db->prepare('UPDATE users SET name = ?, avatar = ? WHERE id = ?')
       ->execute([$profile['name'], $profile['picture'] ?? null, $user['id']]);
    $userId = $user['id'];
} else {
    // Новый юзер
    $db->prepare('INSERT INTO users (google_id, email, name, avatar) VALUES (?,?,?,?)')
       ->execute([$profile['id'], $profile['email'], $profile['name'], $profile['picture'] ?? null]);
    $userId = (int) $db->lastInsertId();

    // Создаём системные теги для нового юзера
    createDefaultTags($db, $userId);
}

// ── 4. Выдаём JWT в cookie и редиректим на фронт ─────────────────────────────
$token = createToken($userId);
setAuthCookie($token);

header('Location: ' . APP_URL . '/');
exit;

// ── Helpers ───────────────────────────────────────────────────────────────────

function curlPost(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

function curlGet(string $url, string $accessToken): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

function createDefaultTags(PDO $db, int $userId): void {
    $tags = [
        ['Зарплата',    '#1a7a45', 1],
        ['Покупки',     '#c05c20', 1],
        ['Здоровье',    '#e42420', 1],
        ['Транспорт',   '#2c5fa8', 1],
        ['Коммуналка',  '#7a4a1a', 1],
        ['Перевод',     '#555555', 1],
        ['Получено',    '#1a9455', 1],
        ['Наличные',    '#888888', 1],
        ['Доход',       '#1a7a45', 1],
        ['Депозит',     '#4a90d9', 1],
        ['Другое',      '#aaaaaa', 1],
    ];

    $stmt = $db->prepare('INSERT INTO tags (user_id, name, color, is_system) VALUES (?,?,?,?)');
    foreach ($tags as [$name, $color, $sys]) {
        $stmt->execute([$userId, $name, $color, $sys]);
    }
}
