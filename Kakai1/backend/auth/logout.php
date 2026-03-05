<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../utils/logger.php';

session_start();

if (isset($_SESSION['user_id'])) {
    logActivity($pdo, $_SESSION['user_id'], 'Logout', 'Authentication', 'User logged out of the system.');
}

session_unset();
session_destroy();

header("Location: ../../frontend/auth/login.php");
exit;
