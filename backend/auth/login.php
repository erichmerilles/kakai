<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

// Security
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME_SECONDS', 15 * 60);

try {
    $input = json_decode(file_get_contents("php://input"), true);
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
        exit;
    }

    // Retrieve security fields
    $stmt = $conn->prepare("
        SELECT user_id, employee_id, username, password, role, status, login_attempts, lockout_time 
        FROM users 
        WHERE username = ? LIMIT 1
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        exit;
    }

    $user = $result->fetch_assoc();
    $userId = $user['user_id'];
    $current_attempts = $user['login_attempts'] ?? 0;

    // Check for account lockout
    if ($user['lockout_time'] !== null && strtotime($user['lockout_time']) > time()) {
        echo json_encode(['success' => false, 'message' => 'Your account is temporarily locked. Please try again later.']);
        exit;
    }

    if (strtolower(trim($user['status'])) !== 'active') {
        echo json_encode(['success' => false, 'message' => 'Your account is inactive. Please contact admin.']);
        exit;
    }

    // Password Verification
    if (!password_verify($password, $user['password'])) {
        $current_attempts++;
        $lockout_time_sql = 'NULL';
        $lockout_message = 'Incorrect password.';

        if ($current_attempts >= MAX_LOGIN_ATTEMPTS) {
            // Lockout triggered
            $lockout_end = date('Y-m-d H:i:s', time() + LOCKOUT_TIME_SECONDS);
            $lockout_time_sql = "'{$lockout_end}'";
            $lockout_message = "Incorrect password. Account locked for " . (LOCKOUT_TIME_SECONDS / 60) . " minutes.";
        }
        
        // Update attempts and potentially lockout time
        $updateFail = $conn->prepare("UPDATE users SET login_attempts = ?, lockout_time = {$lockout_time_sql} WHERE user_id = ?");
        $updateFail->bind_param("ii", $current_attempts, $userId);
        $updateFail->execute();
        $updateFail->close();

        echo json_encode(['success' => false, 'message' => $lockout_message]);
        exit;
    }

    // Prevent Session Fixation
    session_regenerate_id(true);

    // Create session
    $_SESSION['user_id'] = $userId;
    $_SESSION['employee_id'] = $user['employee_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    // Update last_login AND reset login_attempts/lockout_time
    $updateSuccess = $conn->prepare("
        UPDATE users 
        SET last_login = NOW(), login_attempts = 0, lockout_time = NULL 
        WHERE user_id = ?
    ");
    $updateSuccess->bind_param("i", $userId);
    $updateSuccess->execute();
    $updateSuccess->close();

    // Redirect based on role
    $redirect = ($user['role'] === 'Admin') 
        ? 'frontend/dashboard/admin_dashboard.php' 
        : 'frontend/dashboard/employee_dashboard.php';

    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'redirect' => $redirect
    ]);

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage()); // Log internal error
    echo json_encode(['success' => false, 'message' => 'An unexpected server error occurred.']);
}

?>