<?php
// DELETE /api/transactions — удалить все транзакции пользователя
$userId = requireAuth();
$db     = Database::get();

$db->beginTransaction();
try {
    $db->prepare('DELETE FROM transactions WHERE user_id = ?')->execute([$userId]);
    $db->prepare('UPDATE users SET balance = 0, balance_freedom = 0, balance_other = 0 WHERE id = ?')->execute([$userId]);
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    jsonError('Ошибка при очистке: ' . $e->getMessage(), 500);
}

jsonResponse(['ok' => true]);