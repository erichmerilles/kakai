<?php
/*session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents("php://input"), true);
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT user_id, employee_id, username, password, role, status FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    $user = $result->fetch_assoc();

    if (strtolower(trim($user['status'])) !== 'active') {
        echo json_encode(['success' => false, 'message' => 'Your account is inactive. Please contact admin.']);
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
        exit;
    }

    // Create session
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['employee_id'] = $user['employee_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    // Update last_login
    $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $update->bind_param("i", $user['user_id']);
    $update->execute();

    // Redirect based on role
    $redirect = ($user['role'] === 'Admin') 
        ? '../../frontend/dashboard/admin_dashboard.php' 
        : '../../frontend/dashboard/employee_dashboard.php';

    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'redirect' => $redirect
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}*/

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../utils/logger.php';
header('Content-Type: application/json');

try {
    // get input data
    $input = json_decode(file_get_contents("php://input"), true);
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
        exit;
    }

    // fetch user
    $stmt = $pdo->prepare("SELECT user_id, employee_id, username, password, role, status FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // check existing user
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    // check status
    if (strtolower(trim($user['status'])) !== 'active') {
        echo json_encode(['success' => false, 'message' => 'Your account is inactive. Please contact admin.']);
        exit;
    }

    // verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
        exit;
    }

    // set session
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['employee_id'] = $user['employee_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    // fetch permissions
    try {
        $permStmt = $pdo->prepare("SELECT * FROM employee_permissions WHERE employee_id = ?");
        $permStmt->execute([$user['employee_id']]);
        $perms = $permStmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['permissions'] = $perms ? $perms : [];
    } catch (Exception $e) {
        $_SESSION['permissions'] = [];
    }

    // update last login
    $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $update->execute([$user['user_id']]);

    // log activity
    logActivity($pdo, $_SESSION['user_id'], 'Login', 'Authentication', 'User successfully logged into the system.');

    // redirect based on role
    $redirect = ($user['role'] === 'Admin')
        ? '../../frontend/dashboard/admin_dashboard.php'
        : '../../frontend/dashboard/employee_dashboard.php';

    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'redirect' => $redirect
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
