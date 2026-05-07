<?php
// POST /api/transactions — импорт массива транзакций из выписки
ob_start(); // Включаем буферизацию
ini_set('display_errors', '1');
error_reporting(E_ALL);
$userId = requireAuth();
$db     = Database::get();
$body   = getBody();

$transactions = $body['transactions'] ?? [];
$bank         = $body['bank']         ?? 'kaspi';

if (!is_array($transactions) || empty($transactions)) {
    jsonError('Поле transactions обязательно и должно быть массивом');
}

// Определяем теги для мерчантов через кэш
// Загружаем кэш мерчантов юзера одним запросом
$merchantStmt = $db->prepare(
    'SELECT name, tag_id FROM merchants WHERE user_id = ?'
);
$merchantStmt->execute([$userId]);
$merchantCache = [];
foreach ($merchantStmt->fetchAll() as $row) {
    $merchantCache[mb_strtolower($row['name'])] = $row['tag_id'];
}

// Системные теги для autoTag
$tagStmt = $db->prepare(
    'SELECT id, name FROM tags WHERE user_id = ? AND is_system = 1'
);
$tagStmt->execute([$userId]);
$systemTags = [];
foreach ($tagStmt->fetchAll() as $row) {
    $systemTags[$row['name']] = $row['id'];
}

$insertTx = $db->prepare('
    INSERT IGNORE INTO transactions
        (user_id, date, amount, type, detail, tag_id, bank, is_deposit, hash)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?)
');

$added   = 0;
$skipped = 0;

$db->beginTransaction();
try {
    foreach ($transactions as $tx) {
        // Валидация обязательных полей
        if (empty($tx['hash']) || empty($tx['date']) || !isset($tx['amount'])) {
            $skipped++;
            continue;
        }

        $isDeposit = isDepositTx($tx);
        $tagId     = resolveTag($tx, $merchantCache, $systemTags);

        // Конвертируем дату DD.MM.YY → YYYY-MM-DD
        $date = kaspiDateToSql($tx['date']);

        $insertTx->execute([
            $userId,
            $date,
            (float) $tx['amount'],
            $tx['type']   ?? '',
            $tx['detail'] ?? '',
            $tagId,
            $bank,
            $isDeposit ? 1 : 0,
            $tx['hash'],
        ]);

        if ($insertTx->rowCount() > 0) {
            $added++;
        } else {
            $skipped++; // дубликат (IGNORE сработал)
        }
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    jsonError('Ошибка при сохранении: ' . $e->getMessage(), 500);
}
// Сохраняем реальный баланс из выписки
if (isset($body['balance_end']) && is_numeric($body['balance_end'])) {
    if ($bank === 'freedom')     $column = 'balance_freedom';
    elseif ($bank === 'kaspi')   $column = 'balance';
    else                         $column = 'balance_other';
    $db->prepare("UPDATE users SET {$column} = ? WHERE id = ?")
       ->execute([(float)$body['balance_end'], $userId]);
}
jsonResponse([
    'added'   => $added,
    'skipped' => $skipped,
    'total'   => count($transactions),
]);

// ── Helpers ───────────────────────────────────────────────────────────────────

function kaspiDateToSql(string $date): string {
    // DD.MM.YY → YYYY-MM-DD
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{2})$/', $date, $m)) {
        return "20{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return $date; // уже в нужном формате
}

function isDepositTx(array $tx): bool {
    $detail = $tx['detail'] ?? '';
    return str_contains($detail, 'Депозит') || ($tx['type'] === 'Разное');
}

function resolveTag(array $tx, array &$cache, array $systemTags): ?int {
    $detail = mb_strtolower($tx['detail'] ?? '');
    $type   = $tx['type'] ?? '';
    $amount = (float) ($tx['amount'] ?? 0);

    // Сначала смотрим кэш мерчантов
    if (isset($cache[$detail])) return $cache[$detail] ?: null;

    // Автотег по ключевым словам
    $tag = null;

if ($amount > 0 && str_contains($detail, 'зарплата'))          $tag = 'Зарплата';
elseif (str_contains($detail, 'аптек') || str_contains($detail, 'pharm') || str_contains($detail, 'фарм')) $tag = 'Здоровье';
elseif (str_contains($detail, 'qr') || str_contains($detail, 'onay') || str_contains($detail, 'транспорт')) $tag = 'Транспорт';
elseif (str_contains($detail, 'телеком') || str_contains($detail, 'tele2') || str_contains($detail, 'алсеко') || str_contains($detail, 'квитанц')) $tag = 'Коммуналка';
elseif (str_contains($detail, 'депозит'))                       $tag = 'Депозит';
elseif ($type === 'Покупка')                                    $tag = 'Покупки';
elseif ($type === 'Снятие')                                     $tag = 'Наличные';
elseif ($type === 'Перевод' && $amount < 0)                     $tag = null; // AI разберётся
elseif ($type === 'Перевод' && $amount > 0)                     $tag = 'Получено';
elseif ($type === 'Пополнение')                                 $tag = 'Доход';
else                                                            $tag = 'Другое';

return $systemTags[$tag] ?? null;
}
