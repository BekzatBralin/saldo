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

// Получаем подсказку пользователя (ai_prompt)
$userPrompt = $db->query("SELECT ai_prompt FROM users WHERE id = $userId")->fetchColumn() ?: '';

$tagBreakdown = '';
if (!empty($context['tagBreakdown'])) {
    foreach ($context['tagBreakdown'] as $tag) {
        $tagBreakdown .= "  - {$tag['name']}: " . number_format($tag['total'], 0, '.', ' ') . " ₸\n";
    }
}

$incomeBreakdown = '';
if (!empty($context['incomeBreakdown'])) {
    foreach ($context['incomeBreakdown'] as $tag) {
        $incomeBreakdown .= "  - {$tag['name']}: " . number_format($tag['total'], 0, '.', ' ') . " ₸\n";
    }
}

$recent = '';
if (!empty($context['recentTransactions'])) {
    foreach ($context['recentTransactions'] as $tx) {
        $sign = $tx['amount'] >= 0 ? '+' : '';
        $recent .= "  {$tx['date']}: {$sign}{$tx['amount']} ₸ | {$tx['type']} | {$tx['detail']} | Тег: {$tx['tag_name']}\n";
    }
}

$allTags = !empty($context['allTags']) ? implode(', ', $context['allTags']) : 'не указаны';

// Системный промт с контекстом аккаунта
$systemPrompt = <<<PROMPT
Ты финансовый помощник в приложении Saldo для {$userName}.
Приложение отслеживает транзакции из банковских выписок (Kaspi Gold, Freedom Finance).

=== ТЕКУЩИЙ КОНТЕКСТ АККАУНТА ===
Период данных: {$context['dateRange']['from']} — {$context['dateRange']['to']}
Баланс (расчётный): {$context['balance']} ₸
Доходы за период: {$context['income']} ₸
Расходы за период: {$context['expense']} ₸
Количество транзакций: {$context['tx_count']}

Доступные теги пользователя: {$allTags}

Расходы по тегам (топ-10):
{$tagBreakdown}
Доходы по тегам:
{$incomeBreakdown}
Последние 20 транзакций:
{$recent}
=== ПОДСКАЗКИ ПОЛЬЗОВАТЕЛЯ ===
{$userPrompt}

Правила ответа:
- Отвечай на русском языке
- Будь конкретным и полезным, опирайся на цифры выше
- Не придумывай данных которых нет в контексте
- Суммы округляй до рублей/тенге, форматируй с пробелами (100 000 ₸)
- Если данных мало или пользователь не загрузил выписку — скажи об этом
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
