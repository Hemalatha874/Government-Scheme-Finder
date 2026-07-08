<?php
/**
 * Application Configuration
 * Government Scheme Eligibility Finder
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'govt_scheme_finder');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Government Scheme Eligibility Finder');
define('APP_URL', '/govtscheme');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('DOWNLOAD_DIR', __DIR__ . '/../downloads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

/**
 * Require user login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'Please login first', 'redirect' => 'login.html'], 401);
    }
}

/**
 * Require admin login
 */
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'Admin access required', 'redirect' => 'admin.html'], 403);
    }
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate mobile (Indian)
 */
function isValidMobile($mobile) {
    return preg_match('/^[6-9]\d{9}$/', $mobile);
}
