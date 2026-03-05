<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
$id = intval($_POST['notification_id'] ?? 0);
if (!$id) { echo json_encode(['success'=>false,'message'=>'missing id']); exit; }
try {
  $stmt = $pdo->prepare('UPDATE notifications SET status = "Read" WHERE notification_id = ?');
  $stmt->execute([$id]);
  echo json_encode(['success'=>true]);
} catch (Exception $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
