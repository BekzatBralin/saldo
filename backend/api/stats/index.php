<?php
// GET /api/stats?date_from=2026-03-01&date_to=2026-04-19

$userId   = requireAuth();
$db       = Database::get();
$dateFrom = $_GET['date_from'] ?? null;
$dateTo   = $_GET['date_to']   ?? null;

// ── Суммарные цифры ───────────────────────────────────────────────────────────
$params = [$userId];
$dateWhere = '';
if ($dateFrom) { $dateWhere .= ' AND date >= ?'; $params[] = $dateFrom; }
if ($dateTo)   { $dateWhere .= ' AND date <= ?'; $params[] = $dateTo; }

$summary = $db->prepare("
    SELECT
        SUM(CASE WHEN amount > 0 AND is_deposit = 0 THEN amount  ELSE 0 END) AS income,
        SUM(CASE WHEN amount < 0 AND is_deposit = 0 THEN ABS(amount) ELSE 0 END) AS expense,
        SUM(CASE WHEN is_deposit = 1 THEN amount ELSE 0 END) AS deposit_flow,
        COUNT(*) AS tx_count
    FROM transactions
    WHERE user_id = ? $dateWhere
");
$summary->execute($params);
$s = $summary->fetch();

$income  = (float) ($s['income']  ?? 0);
$expense = (float) ($s['expense'] ?? 0);
$profit  = $income - $expense;

// ── По тегам ──────────────────────────────────────────────────────────────────
$byTag = $db->prepare("
    SELECT
        COALESCE(tg.name, 'Без тега') AS tag_name,
        COALESCE(tg.color, '#aaa')    AS tag_color,
        SUM(CASE WHEN t.amount > 0 THEN t.amount    ELSE 0 END) AS income,
        SUM(CASE WHEN t.amount < 0 THEN ABS(t.amount) ELSE 0 END) AS expense
    FROM transactions t
    LEFT JOIN tags tg ON tg.id = t.tag_id
    WHERE t.user_id = ? AND t.is_deposit = 0 $dateWhere
    GROUP BY t.tag_id, tg.name, tg.color
    ORDER BY expense DESC
");
$byTag->execute($params);
$tagRows = $byTag->fetchAll();

foreach ($tagRows as &$row) {
    $row['income']  = (float) $row['income'];
    $row['expense'] = (float) $row['expense'];
}

// ── По дням (для графика) ─────────────────────────────────────────────────────
$byDay = $db->prepare("
    SELECT
        date,
        SUM(CASE WHEN amount > 0 AND is_deposit = 0 THEN amount    ELSE 0 END) AS income,
        SUM(CASE WHEN amount < 0 AND is_deposit = 0 THEN ABS(amount) ELSE 0 END) AS expense
    FROM transactions
    WHERE user_id = ? $dateWhere
    GROUP BY date
    ORDER BY date ASC
");
$byDay->execute($params);
$dayRows = $byDay->fetchAll();

foreach ($dayRows as &$row) {
    $row['income']  = (float) $row['income'];
    $row['expense'] = (float) $row['expense'];
}

jsonResponse([
    'summary' => [
        'income'        => $income,
        'expense'       => $expense,
        'profit'        => $profit,
        'profit_pct'    => $income > 0 ? round($profit / $income * 100, 1) : 0,
        'saved_pct'     => $income > 0 ? round(max(0, $profit) / $income * 100, 1) : 0,
        'deposit_flow'  => (float) ($s['deposit_flow'] ?? 0),
        'tx_count'      => (int)   ($s['tx_count']     ?? 0),
    ],
    'by_tag' => $tagRows,
    'by_day' => $dayRows,
]);
