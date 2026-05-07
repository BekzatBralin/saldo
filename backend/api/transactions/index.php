<?php
ob_start(); // Включаем буферизацию
ini_set('display_errors', '1');
error_reporting(E_ALL);
// 1. Подключаем автозагрузку всех библиотек (JWT и прочих)
require_once __DIR__ . '/../../vendor/autoload.php';

// 2. Подключаем твои внутренние файлы
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../db/database.php';
require_once __DIR__ . '/../../middleware/auth.php';



// GET /api/transactions

$userId = requireAuth();
$db     = Database::get();

// Фильтры из query string
$type       = $_GET['type']       ?? null;   // Покупка / Перевод / ...
$tagId      = $_GET['tag_id']     ?? null;
$dateFrom   = $_GET['date_from']  ?? null;
$dateTo     = $_GET['date_to']    ?? null;
$search     = $_GET['search']     ?? null;
$isDeposit  = isset($_GET['is_deposit']) ? (int)$_GET['is_deposit'] : null;
$limit      = min((int)($_GET['limit'] ?? 100), 500);
$offset     = (int)($_GET['offset'] ?? 0);

$where  = ['t.user_id = ?'];
$params = [$userId];

if ($type)     { $where[] = 't.type = ?';       $params[] = $type; }
if ($tagId)    { $where[] = 't.tag_id = ?';     $params[] = (int)$tagId; }
if ($dateFrom) { $where[] = 't.date >= ?';      $params[] = $dateFrom; }
if ($dateTo)   { $where[] = 't.date <= ?';      $params[] = $dateTo; }
if ($search)   { $where[] = 't.detail LIKE ?';  $params[] = '%' . $search . '%'; }
if ($isDeposit !== null) { $where[] = 't.is_deposit = ?'; $params[] = $isDeposit; }

$whereStr = implode(' AND ', $where);

// Общее количество (для пагинации)
$countStmt = $db->prepare("SELECT COUNT(*) FROM transactions t WHERE $whereStr");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

// Сами данные
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare("
    SELECT
        t.id, t.date, t.amount, t.type, t.detail,
        t.tag_id, t.bank, t.is_deposit, t.hash, t.created_at,
        tg.name  AS tag_name,
        tg.color AS tag_color
    FROM transactions t
    LEFT JOIN tags tg ON tg.id = t.tag_id
    WHERE $whereStr
    ORDER BY t.date DESC, t.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// amount приходит как string из PDO — приводим
foreach ($rows as &$row) {
    $row['amount']     = (float) $row['amount'];
    $row['is_deposit'] = (bool)  $row['is_deposit'];
}

jsonResponse([
    'data'   => $rows,
    'total'  => $total,
    'limit'  => $limit,
    'offset' => $offset,
]);
