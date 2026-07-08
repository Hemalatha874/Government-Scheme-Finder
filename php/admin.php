<?php
/**
 * Admin Panel API
 */
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'dashboard':
        getDashboard();
        break;
    case 'users':
        getUsers();
        break;
    case 'delete_user':
        deleteUser();
        break;
    case 'add_scheme':
        addScheme();
        break;
    case 'update_scheme':
        updateScheme();
        break;
    case 'delete_scheme':
        deleteScheme();
        break;
    case 'applications':
        getApplications();
        break;
    case 'documents':
        getAllDocuments();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function getDashboard() {
    requireAdmin();
    $db = getDB();

    $stats = [
        'total_users' => $db->fetchOne("SELECT COUNT(*) as c FROM users")['c'],
        'total_schemes' => $db->fetchOne("SELECT COUNT(*) as c FROM schemes WHERE is_active = 1")['c'],
        'total_applications' => $db->fetchOne("SELECT COUNT(*) as c FROM applications")['c'],
        'total_documents' => $db->fetchOne("SELECT COUNT(*) as c FROM documents")['c'],
        'unread_messages' => $db->fetchOne("SELECT COUNT(*) as c FROM contact_messages WHERE is_read = 0")['c'],
    ];

    $recentActivity = $db->fetchAll(
        "(SELECT 'application' as type, u.full_name as user_name, s.scheme_name as detail, a.applied_at as activity_date 
          FROM applications a JOIN users u ON a.user_id = u.id JOIN schemes s ON a.scheme_id = s.id 
          ORDER BY a.applied_at DESC LIMIT 5)
         UNION ALL
         (SELECT 'registration' as type, full_name as user_name, email as detail, created_at as activity_date 
          FROM users ORDER BY created_at DESC LIMIT 5)
         ORDER BY activity_date DESC LIMIT 10"
    );

    jsonResponse(['success' => true, 'stats' => $stats, 'recent_activity' => $recentActivity]);
}

function getUsers() {
    requireAdmin();
    $db = getDB();
    $users = $db->fetchAll(
        "SELECT u.id, u.full_name, u.email, u.mobile, u.created_at, 
                up.age, up.state, up.occupation 
         FROM users u LEFT JOIN user_profiles up ON u.id = up.user_id 
         ORDER BY u.created_at DESC"
    );
    jsonResponse(['success' => true, 'users' => $users]);
}

function deleteUser() {
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $userId = (int)($data['user_id'] ?? 0);
    $db = getDB();
    $db->execute("DELETE FROM users WHERE id = ?", [$userId]);
    jsonResponse(['success' => true, 'message' => 'User deleted']);
}

function addScheme() {
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $required = ['scheme_code', 'scheme_name', 'category', 'description', 'benefits'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            jsonResponse(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'], 400);
        }
    }

    $db = getDB();
    $id = $db->insert(
        "INSERT INTO schemes (scheme_code, scheme_name, category, description, benefits, min_age, max_age, 
         income_limit, gender_eligibility, education_requirement, occupation, state_ut, caste_category,
         disability_eligibility, farmer_eligibility, student_eligibility, required_documents,
         application_steps, official_website, official_application_link, helpline_number, last_updated)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $data['scheme_code'], $data['scheme_name'], $data['category'], $data['description'], $data['benefits'],
            $data['min_age'] ?? 0, $data['max_age'] ?? 120, $data['income_limit'] ?? null,
            $data['gender_eligibility'] ?? 'Any', $data['education_requirement'] ?? 'Any',
            $data['occupation'] ?? 'Any', $data['state_ut'] ?? 'All India', $data['caste_category'] ?? 'Any',
            $data['disability_eligibility'] ?? 'Any', $data['farmer_eligibility'] ?? 'Any',
            $data['student_eligibility'] ?? 'Any', $data['required_documents'] ?? '',
            $data['application_steps'] ?? '', $data['official_website'] ?? '', $data['official_application_link'] ?? '',
            $data['helpline_number'] ?? '', $data['last_updated'] ?? date('Y-m-d')
        ]
    );

    jsonResponse(['success' => true, 'message' => 'Scheme added successfully', 'id' => $id]);
}

function updateScheme() {
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = (int)($data['id'] ?? 0);

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'Scheme ID required'], 400);
    }

    $db = getDB();
    $db->execute(
        "UPDATE schemes SET scheme_name=?, category=?, description=?, benefits=?, min_age=?, max_age=?,
         income_limit=?, gender_eligibility=?, caste_category=?, farmer_eligibility=?, student_eligibility=?,
         required_documents=?, application_steps=?, official_website=?, official_application_link=?,
         helpline_number=?, last_updated=? WHERE id=?",
        [
            $data['scheme_name'], $data['category'], $data['description'], $data['benefits'],
            $data['min_age'] ?? 0, $data['max_age'] ?? 120, $data['income_limit'] ?? null,
            $data['gender_eligibility'] ?? 'Any', $data['caste_category'] ?? 'Any',
            $data['farmer_eligibility'] ?? 'Any', $data['student_eligibility'] ?? 'Any',
            $data['required_documents'] ?? '', $data['application_steps'] ?? '',
            $data['official_website'] ?? '', $data['official_application_link'] ?? '',
            $data['helpline_number'] ?? '', $data['last_updated'] ?? date('Y-m-d'), $id
        ]
    );

    jsonResponse(['success' => true, 'message' => 'Scheme updated successfully']);
}

function deleteScheme() {
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = (int)($data['id'] ?? 0);
    $db = getDB();
    $db->execute("UPDATE schemes SET is_active = 0 WHERE id = ?", [$id]);
    jsonResponse(['success' => true, 'message' => 'Scheme deactivated']);
}

function getApplications() {
    requireAdmin();
    $db = getDB();
    $apps = $db->fetchAll(
        "SELECT a.*, u.full_name, u.email, s.scheme_name, s.category 
         FROM applications a 
         JOIN users u ON a.user_id = u.id 
         JOIN schemes s ON a.scheme_id = s.id 
         ORDER BY a.applied_at DESC"
    );
    jsonResponse(['success' => true, 'applications' => $apps]);
}

function getAllDocuments() {
    requireAdmin();
    $db = getDB();
    $docs = $db->fetchAll(
        "SELECT d.*, u.full_name, u.email FROM documents d 
         JOIN users u ON d.user_id = u.id ORDER BY d.uploaded_at DESC"
    );
    jsonResponse(['success' => true, 'documents' => $docs]);
}
