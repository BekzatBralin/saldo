<?php
// POST /api/ai/tag — автотегирование транзакций
// Body: { merchants: [{detail, type, amount}, ...] }
// Возвращает: { "detail|type": "тег", ... }

$userId = requireAuth();
$db     = Database::get();
$body   = getBody();

$merchants = $body['merchants'] ?? [];
if (empty($merchants) || !is_array($merchants)) {
    jsonError('merchants обязателен');
}

// Нормализуем входные данные — принимаем и старый формат (строки) и новый (объекты)
$normalized = [];
foreach ($merchants as $m) {
    if (is_string($m)) {
        $normalized[] = ['detail' => $m, 'type' => '', 'amount' => 0];
    } else {
        $normalized[] = [
            'detail' => $m['detail'] ?? '',
            'type'   => $m['type']   ?? '',
            'amount' => (float)($m['amount'] ?? 0),
        ];
    }
}

// Кэш — ключ: detail|type
$cached     = [];
$toClassify = [];

$detailList = array_unique(array_column($normalized, 'detail'));
if ($detailList) {
    $placeholders = implode(',', array_fill(0, count($detailList), '?'));
    $cacheStmt = $db->prepare("
        SELECT m.name, m.tx_type, tg.name AS tag_name
        FROM merchants m
        LEFT JOIN tags tg ON tg.id = m.tag_id
        WHERE m.user_id = ? AND m.name IN ($placeholders)
    ");
    $cacheStmt->execute(array_merge([$userId], $detailList));
    foreach ($cacheStmt->fetchAll() as $row) {
        $key = $row['name'] . '|' . ($row['tx_type'] ?? '');
        $cached[$key] = $row['tag_name'];
    }
}

foreach ($normalized as $tx) {
    $key = $tx['detail'] . '|' . $tx['type'];
    if (!isset($cached[$key])) {
        $toClassify[] = $tx;
    }
}

$aiResult = [];

if (!empty($toClassify)) {
    $tagStmt = $db->prepare('SELECT name FROM tags WHERE user_id = ?');
    $tagStmt->execute([$userId]);
    $tagNames = array_column($tagStmt->fetchAll(), 'name');
    $tagList  = implode(', ', $tagNames);

    // Формируем список с полным контекстом
    $lines = [];
    foreach ($toClassify as $tx) {
        $sign   = $tx['amount'] >= 0 ? '+' : '';
        $lines[] = "- detail: \"{$tx['detail']}\", type: \"{$tx['type']}\", amount: {$sign}{$tx['amount']}";
    }
    $merchantList = implode("\n", $lines);
    // Загружаем пользовательскую подсказку
    $userPromptStmt = $db->prepare('SELECT ai_prompt FROM users WHERE id = ?');
    $userPromptStmt->execute([$userId]);
    $userRow    = $userPromptStmt->fetch();
    $userHint   = trim($userRow['ai_prompt'] ?? '');
    $userHintBlock = $userHint
        ? "\nДополнительные подсказки от пользователя:\n{$userHint}\n"
        : '';
    $prompt = <<<PROMPT
Классифицируй каждую транзакцию по одному из тегов.
 
Доступные теги: {$tagList}
 
Правила:
- "С Kaspi Депозита" или detail содержит "Депозит" → тег "Депозит"
- type = "Пополнение" и amount > 0 и похоже на зарплату/доход → "Доход" или "Зарплата"
- type = "Перевод" и amount < 0 → "Перевод"
- type = "Перевод" и amount > 0 → "Получено"
- type = "Покупка" → смотри на detail: магазин→"Покупки", аптека→"Здоровье", транспорт→"Транспорт" и т.д.
- type = "Снятие" → "Наличные"
- Кредит/погашение/рассрочка → "Кредит"
- Коммуналка/телеком/интернет → "Коммуналка"
- Если не знаешь → "Другое"
{$userHintBlock}
Транзакции:
{$merchantList}
 
Ответь ТОЛЬКО валидным JSON объектом вида {"detail|type": "тег"}.
Ключ = значение поля detail + "|" + значение поля type.
Без пояснений, без markdown, без лишних символов.
PROMPT;

    $payload = [
        'model'       => DEEPSEEK_MODEL,
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'max_tokens'  => 2000,
        'temperature' => 0.1,
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
        CURLOPT_TIMEOUT => 30,
    ]);

    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res && $code === 200) {
        $data    = json_decode($res, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        $content = preg_replace('/```json|```/', '', $content);
        $aiResult = json_decode(trim($content), true) ?? [];

        // Сохраняем в кэш мерчантов
        if (!empty($aiResult)) {
            $tagMapStmt = $db->prepare('SELECT id, name FROM tags WHERE user_id = ?');
            $tagMapStmt->execute([$userId]);
            $tagMap = array_column($tagMapStmt->fetchAll(), 'id', 'name');

            // Проверяем есть ли колонка tx_type в merchants
            // Если нет — добавляем (миграция на лету)
            try {
                $db->query('SELECT tx_type FROM merchants LIMIT 1');
            } catch (Exception $e) {
                $db->query('ALTER TABLE merchants ADD COLUMN tx_type VARCHAR(50) DEFAULT NULL');
            }

            $insertMerchant = $db->prepare('
                INSERT INTO merchants (user_id, name, tx_type, tag_id)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE tag_id = VALUES(tag_id), tx_type = VALUES(tx_type)
            ');

            foreach ($aiResult as $key => $tagName) {
                [$detailName, $txType] = array_pad(explode('|', $key, 2), 2, '');
                $tagId = $tagMap[$tagName] ?? null;
                $insertMerchant->execute([$userId, $detailName, $txType, $tagId]);
            }
        }
    }
}

// Объединяем кэш + AI
$result = array_merge($cached, $aiResult);
jsonResponse($result);