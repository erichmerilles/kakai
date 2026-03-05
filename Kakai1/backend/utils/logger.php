<?php
function logActivity($pdo, $user_id, $action, $module, $description)
{
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, module, description) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$user_id, $action, $module, $description]);
    } catch (PDOException $e) {
        // handle error
        return false;
    }
}
