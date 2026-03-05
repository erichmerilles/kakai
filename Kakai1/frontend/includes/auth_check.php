<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// checl permission
function hasPermission($key)
{
    // admin always has full access
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
        return true;
    }

    if (!isset($_SESSION['employee_id'])) {
        return false;
    }

    // refresh permission
    global $pdo;
    if (!isset($pdo)) {
        require_once __DIR__ . '/../../config/db.php';
    }

    try {
        $stmt = $pdo->prepare("SELECT $key FROM employee_permissions WHERE employee_id = ?");
        $stmt->execute([$_SESSION['employee_id']]);
        $perm = $stmt->fetchColumn();

        // update session 
        $_SESSION['permissions'][$key] = $perm;

        return $perm == 1;
    } catch (Exception $e) {
        // return false on any error
        return isset($_SESSION['permissions'][$key]) && $_SESSION['permissions'][$key] == 1;
    }
}

function requirePermission($key)
{
    if (!hasPermission($key)) {
        // redirect unauthorized users to their dashboard
        header("Location: /kakai1/frontend/dashboard/employee_dashboard.php");
        exit;
    }
}
