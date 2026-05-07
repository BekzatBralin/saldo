<?php
/**
 * /api/parse/ai — Gemini fallback парсер банковских выписок.
 * Принимает multipart/form-data с полем 'file' (PDF).
 * Возвращает JSON транзакций в том же формате что и Python парсер.
 */

require_once __DIR__ . '/../../config/config.php';

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

$pdfBytes  = file_get_contents($tmpPath);
$pdfBase64 = base64_encode($pdfBytes);

$prompt = <<<PROMPT
Это банковская выписка (файл: {$origName}).
Извлеки ВСЕ транзакции и верни ТОЛЬКО валидный JSON без markdown, без пояснений, без ```json.

Формат ответа:
{
  "bank": "kaspi / freedom / other",
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
      "hash":   "уникальная строка"
    }
  ]
}

Правила:
- amount: отрицательное = расход, положительное = доход
- type: только одно из: Покупка, Перевод, Пополнение, Снятие, Разное
- date формат: DD.MM.YY (двузначный год)
- hash: любая уникальная строка до 32 символов
- Включи АБСОЛЮТНО ВСЕ транзакции, ничего не пропускай
- JSON должен быть полным и валидным
PROMPT;

function callGemini(string $model, string $pdfBase64, string $prompt, string $apiKey): array {
    $requestBody = json_encode([
        'model'    => $model,
        'contents' => [[
            'parts' => [
                [
                    'inline_data' => [
                        'mime_type' => 'application/pdf',
                        'data'      => $pdfBase64,
                    ]
                ],
                ['text' => $prompt]
            ]
        ]],
        'generationConfig' => [
            'temperature'     => 0.1,
            'maxOutputTokens' => 65536,
        ],
    ], JSON_UNESCAPED_UNICODE);

    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $requestBody,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 120,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    return [$httpCode, $response, $curlErr];
}

function parseGeminiResponse(string $response): ?array {
    $data = json_decode($response, true);
    $raw  = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!$raw) return null;

    // Убираем markdown обёртки
    $clean = preg_replace('/^```json\s*/i', '', trim($raw));
    $clean = preg_replace('/```\s*$/', '', $clean);
    $clean = trim($clean);

    $parsed = json_decode($clean, true);
    if (!$parsed || empty($parsed['transactions'])) return null;

    return $parsed;
}

// ── Попытка 1: gemini-2.5-flash ───────────────────────────────────────────────
[$httpCode, $response, $curlErr] = callGemini('gemini-2.5-flash', $pdfBase64, $prompt, GEMINI_API_KEY);

$parsed = null;
if (!$curlErr && $httpCode === 200) {
    $parsed = parseGeminiResponse($response);
}

// ── Попытка 2: gemini-2.0-flash если первый не справился ─────────────────────
if (!$parsed) {
    [$httpCode2, $response2, $curlErr2] = callGemini('gemini-2.0-flash', $pdfBase64, $prompt, GEMINI_API_KEY);
    if (!$curlErr2 && $httpCode2 === 200) {
        $parsed = parseGeminiResponse($response2);
    }
}

if (!$parsed) {
    http_response_code(422);
    $errMsg = json_decode($response, true)['error']['message'] ?? 'Не удалось извлечь транзакции';
    echo json_encode(['error' => $errMsg]);
    exit;
}

// Генерируем hash если нет
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