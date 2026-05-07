<?php
// POST /api/ai/chat — проксируем сообщение в DeepSeek
// Body: { messages: [{role, content}], context: {balance, income, expense} }

$userId = requireAuth();
$db     = Database::get();
$body   = getBody();

$messages = $body['messages'] ?? [];
$context  = $body['context']  ?? [];

if (empty($messages)) jsonError('messages обязателен');

// Получаем имя юзера для промпта
$user = $db->prepare('SELECT name FROM users WHERE id = ?');
$user->execute([$userId]);
$userName = $user->fetchColumn() ?: 'пользователь';

// Системный промт с контекстом аккаунта
$systemPrompt = <<<PROMPT
Ты финансовый помощник в приложении Kaspi App для {$userName}.

Приложение отслеживает транзакции из банковских выписок (Kaspi Gold, Freedom Finance).

Текущий контекст аккаунта:
- Баланс: {$context['balance']} ₸
- Доходы за период: {$context['income']} ₸  
- Расходы за период: {$context['expense']} ₸
- Количество транзакций: {$context['tx_count']}

Отвечай на русском языке. Будь конкретным и полезным. 
Если спрашивают про транзакции — опирайся на цифры выше.
Не придумывай данных которых у тебя нет.
PROMPT;

$payload = [
    'model'    => DEEPSEEK_MODEL,
    'messages' => array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        $messages
    ),
    'max_tokens'  => 1000,
    'temperature' => 0.7,
    'stream'      => false,
];

$ch = curl_init(DEEPSEEK_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . DEEPSEEK_API_KEY,
    ],
    CURLOPT_TIMEOUT => 300,
]);

$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$res) jsonError('Ошибка соединения с DeepSeek', 502);

$data = json_decode($res, true);

if ($code !== 200 || empty($data['choices'][0]['message']['content'])) {
    jsonError('DeepSeek вернул ошибку: ' . ($data['error']['message'] ?? 'unknown'), 502);
}

jsonResponse([
    'reply' => $data['choices'][0]['message']['content'],
    'usage' => $data['usage'] ?? null,
]);
