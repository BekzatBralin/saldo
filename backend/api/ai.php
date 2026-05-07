<?php
/**
 * /api/parse/ai — Gemini fallback парсер банковских выписок.
 * Принимает multipart/form-data с полем 'file' (PDF).
 * Возвращает JSON транзакций в том же формате что и Python парсер.
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file     = $_FILES['file'];
$tmpPath  = $file['tmp_name'];
$origName = $file['name'] ?? 'statement.pdf';

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload error: ' . $file['error']]);
    exit;
}

// Читаем PDF и кодируем в base64 для Gemini
$pdfBytes  = file_get_contents($tmpPath);
$pdfBase64 = base64_encode($pdfBytes);

$prompt = <<<PROMPT
Это банковская выписка (файл: {$origName}).
Извлеки ВСЕ транзакции и верни ТОЛЬКО валидный JSON без markdown, без пояснений, без ```json.

Формат ответа:
{
  "bank": "название банка (kaspi / freedom / другое)",
  "name": "имя владельца карты",
  "period": "DD.MM.YYYY — DD.MM.YYYY",
  "summary": {
    "balanceStart": 0,
    "balanceEnd": 0,
    "income": 0,
    "expense": 0
  },
  "breakdown": [
    {"label": "Пополнения", "amount": 0, "type": "income"},
    {"label": "Покупки",    "amount": 0, "type": "expense"},
    {"label": "Переводы",   "amount": 0, "type": "expense"},
    {"label": "Снятия",     "amount": 0, "type": "expense"}
  ],
  "transactions": [
    {
      "date":   "DD.MM.YY",
      "amount": -1500.00,
      "type":   "Покупка",
      "detail": "название магазина или получателя",
      "hash":   "уникальная строка на основе даты+суммы+деталей"
    }
  ]
}

Правила:
- amount: отрицательное = расход, положительное = доход
- type: только одно из: Покупка, Перевод, Пополнение, Снятие, Разное
- date формат: DD.MM.YY (двузначный год)
- hash: btoa(date+"|"+amount+"|"+type+"|"+detail) урезанный до 32 символов
- Включи АБСОЛЮТНО ВСЕ транзакции из выписки, ничего не пропускай
PROMPT;

$requestBody = json_encode([
    'model'  => 'gemini-2.5-flash',
    'contents' => [[
        'parts' => [
            [
                'inline_data' => [
                    'mime_type' => 'application/pdf',
                    'data'      => $pdfBase64,
                ]
            ],
            [
                'text' => $prompt
            ]
        ]
    ]],
    'generationConfig' => [
        'temperature'     => 0.1,
        'maxOutputTokens' => 8192,
    ],
], JSON_UNESCAPED_UNICODE);

// Запрос к Gemini
$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-04-17:generateContent?key=' . GEMINI_API_KEY;

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestBody,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 60,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'Gemini request failed: ' . $curlErr]);
    exit;
}

$geminiData = json_decode($response, true);

if ($httpCode !== 200 || empty($geminiData['candidates'][0]['content']['parts'][0]['text'])) {
    http_response_code(502);
    $errMsg = $geminiData['error']['message'] ?? 'Unknown Gemini error';
    echo json_encode(['error' => 'Gemini error: ' . $errMsg]);
    exit;
}

$rawText = $geminiData['candidates'][0]['content']['parts'][0]['text'];

// Убираем возможные markdown-обёртки
$clean = preg_replace('/^```json\s*/i', '', trim($rawText));
$clean = preg_replace('/```\s*$/', '', $clean);
$clean = trim($clean);

$parsed = json_decode($clean, true);

if (!$parsed || empty($parsed['transactions'])) {
    http_response_code(422);
    echo json_encode([
        'error'   => 'Gemini не смог извлечь транзакции',
        'rawText' => mb_substr($rawText, 0, 500),
    ]);
    exit;
}

// Убеждаемся что у каждой транзакции есть hash
foreach ($parsed['transactions'] as &$tx) {
    if (empty($tx['hash'])) {
        $tx['hash'] = substr(
            base64_encode("{$tx['date']}|{$tx['amount']}|{$tx['type']}|{$tx['detail']}"),
            0, 32
        );
    }
}
unset($tx);

echo json_encode($parsed, JSON_UNESCAPED_UNICODE);
