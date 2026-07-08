<?php
/**
 * Documents API - Upload, List, Delete
 */
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listDocuments();
        break;
    case 'upload':
        uploadDocument();
        break;
    case 'delete':
        deleteDocument();
        break;
    case 'types':
        getDocumentTypes();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function getDocumentTypes() {
    $types = [
        ['type' => 'aadhaar', 'name' => 'Aadhaar Card', 'required' => true],
        ['type' => 'income_certificate', 'name' => 'Income Certificate', 'required' => true],
        ['type' => 'community_certificate', 'name' => 'Community Certificate', 'required' => false],
        ['type' => 'residence_certificate', 'name' => 'Residence Certificate', 'required' => true],
        ['type' => 'education_certificate', 'name' => 'Education Certificate', 'required' => false],
        ['type' => 'bank_passbook', 'name' => 'Bank Passbook', 'required' => true],
        ['type' => 'passport_photo', 'name' => 'Passport Photo', 'required' => true],
        ['type' => 'disability_certificate', 'name' => 'Disability Certificate', 'required' => false],
    ];
    jsonResponse(['success' => true, 'types' => $types]);
}

function listDocuments() {
    requireLogin();
    $db = getDB();
    $docs = $db->fetchAll(
        "SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC",
        [$_SESSION['user_id']]
    );
    jsonResponse(['success' => true, 'documents' => $docs]);
}

function uploadDocument() {
    requireLogin();

    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'No file uploaded or upload error'], 400);
    }

    $documentType = trim($_POST['document_type'] ?? '');
    if (empty($documentType)) {
        jsonResponse(['success' => false, 'message' => 'Document type is required'], 400);
    }

    $file = $_FILES['document'];
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        jsonResponse(['success' => false, 'message' => 'File size exceeds 5MB limit'], 400);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        jsonResponse(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, JPG, PNG, DOC'], 400);
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $fileName = $_SESSION['user_id'] . '_' . $documentType . '_' . time() . '.' . $ext;
    $filePath = UPLOAD_DIR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        jsonResponse(['success' => false, 'message' => 'Failed to save file'], 500);
    }

    $db = getDB();
    $docId = $db->insert(
        "INSERT INTO documents (user_id, document_type, file_name, file_path, file_size) VALUES (?, ?, ?, ?, ?)",
        [$_SESSION['user_id'], $documentType, $file['name'], 'uploads/' . $fileName, $file['size']]
    );

    jsonResponse([
        'success' => true,
        'message' => 'Document uploaded successfully!',
        'document' => [
            'id' => $docId,
            'document_type' => $documentType,
            'file_name' => $file['name'],
            'file_path' => 'uploads/' . $fileName
        ]
    ]);
}

function deleteDocument() {
    requireLogin();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $docId = (int)($data['id'] ?? 0);

    $db = getDB();
    $doc = $db->fetchOne("SELECT * FROM documents WHERE id = ? AND user_id = ?", [$docId, $_SESSION['user_id']]);

    if (!$doc) {
        jsonResponse(['success' => false, 'message' => 'Document not found'], 404);
    }

    $fullPath = __DIR__ . '/../' . $doc['file_path'];
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }

    $db->execute("DELETE FROM documents WHERE id = ?", [$docId]);
    jsonResponse(['success' => true, 'message' => 'Document deleted successfully']);
}
