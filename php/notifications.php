<?php
/**
 * Notifications API
 */
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listNotifications();
        break;
    case 'add':
        addNotification();
        break;
    case 'delete':
        deleteNotification();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function listNotifications() {
    $db = getDB();
    $type = $_GET['type'] ?? '';

    $where = "is_active = 1";
    $params = [];

    if ($type && $type !== 'all') {
        $where .= " AND notification_type = ?";
        $params[] = $type;
    }

    $notifications = $db->fetchAll(
        "SELECT n.*, s.scheme_name FROM notifications n 
         LEFT JOIN schemes s ON n.scheme_id = s.id 
         WHERE {$where} ORDER BY n.created_at DESC",
        $params
    );

    jsonResponse(['success' => true, 'notifications' => $notifications]);
}

function addNotification() {
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $title = trim($data['title'] ?? '');
    $message = trim($data['message'] ?? '');
    $type = $data['notification_type'] ?? 'announcement';
    $deadline = $data['deadline_date'] ?? null;

    if (empty($title) || empty($message)) {
        jsonResponse(['success' => false, 'message' => 'Title and message are required'], 400);
    }

    $db = getDB();
    $id = $db->insert(
        "INSERT INTO notifications (title, message, notification_type, deadline_date) VALUES (?, ?, ?, ?)",
        [$title, $message, $type, $deadline]
    );

    jsonResponse(['success' => true, 'message' => 'Notification added', 'id' => $id]);
}

function deleteNotification() {
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = (int)($data['id'] ?? 0);

    $db = getDB();
    $db->execute("UPDATE notifications SET is_active = 0 WHERE id = ?", [$id]);
    jsonResponse(['success' => true, 'message' => 'Notification removed']);
}
