<?php
/**
 * Contact Form API
 */
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'send':
        sendMessage();
        break;
    case 'list':
        listMessages();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function sendMessage() {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $subject = trim($data['subject'] ?? '');
    $message = trim($data['message'] ?? '');

    $errors = [];
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email) || !isValidEmail($email)) $errors[] = 'Valid email is required';
    if (empty($subject)) $errors[] = 'Subject is required';
    if (empty($message)) $errors[] = 'Message is required';

    if (!empty($errors)) {
        jsonResponse(['success' => false, 'message' => implode('. ', $errors)], 400);
    }

    $db = getDB();
    $id = $db->insert(
        "INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)",
        [$name, $email, $phone, $subject, $message]
    );

    jsonResponse([
        'success' => true,
        'message' => 'Thank you! Your message has been sent successfully. We will respond within 24-48 hours.',
        'id' => $id
    ]);
}

function listMessages() {
    requireAdmin();
    $db = getDB();
    $messages = $db->fetchAll("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 100");
    jsonResponse(['success' => true, 'messages' => $messages]);
}
