<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';

// check permissions
if (!hasPermission('inv_delete')) {
  header('Location: ../../frontend/inventory/inventory_overview.php?error=unauthorized');
  exit;
}

// validate item_id
$item_id = intval($_GET['id'] ?? $_GET['item_id'] ?? 0);

if (!$item_id) {
  header('Location: ../../frontend/inventory/inventory_overview.php?error=missing_id');
  exit;
}

try {
  $pdo->beginTransaction();

  // delete related movements first
  $stmtMov = $pdo->prepare('DELETE FROM inventory_movements WHERE item_id = ?');
  $stmtMov->execute([$item_id]);

  // delete item
  $stmt = $pdo->prepare('DELETE FROM inventory WHERE item_id = ?');
  $stmt->execute([$item_id]);

  $pdo->commit();

  // redirect to overview on success
  header('Location: ../../frontend/inventory/inventory_overview.php?msg=deleted');
} catch (Exception $e) {
  $pdo->rollBack();
  // redirect to overview with error message
  header('Location: ../../frontend/inventory/inventory_overview.php?error=delete_failed');
}
exit;
