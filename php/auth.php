<?php
/**
 * Authentication API - Register, Login, Logout, Forgot Password
 */
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'register':
        handleRegister();
        break;
    case 'login':
        handleLogin();
        break;
    case 'admin_login':
        handleAdminLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check_session':
        handleCheckSession();
        break;
    case 'forgot_password':
        handleForgotPassword();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleRegister() {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $fullName = trim($data['full_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $mobile = trim($data['mobile'] ?? '');
    $password = $data['password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';

    $errors = [];
    if (empty($fullName)) $errors[] = 'Full name is required';
    if (empty($email) || !isValidEmail($email)) $errors[] = 'Valid email is required';
    if (empty($mobile) || !isValidMobile($mobile)) $errors[] = 'Valid 10-digit mobile number is required';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match';

    if (!empty($errors)) {
        jsonResponse(['success' => false, 'message' => implode('. ', $errors)], 400);
    }

    $db = getDB();
    $existing = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        jsonResponse(['success' => false, 'message' => 'Email already registered'], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $userId = $db->insert(
        "INSERT INTO users (full_name, email, mobile, password_hash) VALUES (?, ?, ?, ?)",
        [$fullName, $email, $mobile, $hash]
    );

    // Create empty profile
    $db->insert("INSERT INTO user_profiles (user_id, full_name) VALUES (?, ?)", [$userId, $fullName]);

    jsonResponse([
        'success' => true,
        'message' => 'Registration successful! Please login.',
        'redirect' => 'login.html'
    ]);
}

function handleLogin() {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Email and password are required'], 400);
    }

    $db = getDB();
    $user = $db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid email or password'], 401);
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];

    jsonResponse([
        'success' => true,
        'message' => 'Login successful!',
        'redirect' => 'dashboard.html',
        'user' => ['id' => $user['id'], 'name' => $user['full_name'], 'email' => $user['email']]
    ]);
}

function handleAdminLogin() {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $username = trim($data['username'] ?? $data['email'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Username and password are required'], 400);
    }

    $db = getDB();
    $admin = $db->fetchOne(
        "SELECT * FROM admins WHERE username = ? OR email = ?",
        [$username, $username]
    );

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid admin credentials'], 401);
    }

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['admin_username'] = $admin['username'];

    jsonResponse([
        'success' => true,
        'message' => 'Admin login successful!',
        'redirect' => 'admin.html',
        'admin' => ['id' => $admin['id'], 'name' => $admin['full_name']]
    ]);
}

function handleLogout() {
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logged out successfully', 'redirect' => 'index.html']);
}

function handleCheckSession() {
    if (isLoggedIn()) {
        jsonResponse([
            'success' => true,
            'logged_in' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'] ?? '',
                'email' => $_SESSION['user_email'] ?? ''
            ]
        ]);
    } elseif (isAdminLoggedIn()) {
        jsonResponse([
            'success' => true,
            'logged_in' => true,
            'is_admin' => true,
            'admin' => [
                'id' => $_SESSION['admin_id'],
                'name' => $_SESSION['admin_name'] ?? ''
            ]
        ]);
    } else {
        jsonResponse(['success' => true, 'logged_in' => false]);
    }
}

function handleForgotPassword() {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $email = trim($data['email'] ?? '');

    if (empty($email) || !isValidEmail($email)) {
        jsonResponse(['success' => false, 'message' => 'Valid email is required'], 400);
    }

    $db = getDB();
    $user = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);

    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'No account found with this email'], 404);
    }

    // In production, send reset email. For demo, show success message.
    jsonResponse([
        'success' => true,
        'message' => 'Password reset link has been sent to your email address.'
    ]);
}
