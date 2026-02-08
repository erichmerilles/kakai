<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function hasPermission($key) {

    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
        return true; 
    }

    // Check the specific permission flag
    if (isset($_SESSION['permissions'][$key]) && $_SESSION['permissions'][$key] == 1) {
        return true;
    }

    return false;
}

function requirePermission($key) {
    if (!hasPermission($key)) {
        http_response_code(403);
        die("403 Forbidden: You do not have permission to access this feature.");
    }
}
?>