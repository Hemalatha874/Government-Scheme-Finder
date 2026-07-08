<?php
/**
 * Profile Management API
 */
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        getProfile();
        break;
    case 'save':
        saveProfile();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function getProfile() {
    requireLogin();
    $db = getDB();
    $profile = $db->fetchOne(
        "SELECT up.*, u.email, u.mobile FROM user_profiles up 
         JOIN users u ON up.user_id = u.id WHERE up.user_id = ?",
        [$_SESSION['user_id']]
    );

    if (!$profile) {
        $profile = ['user_id' => $_SESSION['user_id'], 'full_name' => $_SESSION['user_name'] ?? ''];
    }

    jsonResponse(['success' => true, 'profile' => $profile]);
}

function saveProfile() {
    requireLogin();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $fields = [
        'full_name', 'age', 'gender', 'date_of_birth', 'state', 'district',
        'occupation', 'education', 'annual_income', 'category',
        'disability_status', 'is_farmer', 'is_student'
    ];

    $values = [];
    foreach ($fields as $field) {
        $values[$field] = $data[$field] ?? null;
    }

    if (empty($values['full_name'])) {
        jsonResponse(['success' => false, 'message' => 'Full name is required'], 400);
    }

    $db = getDB();
    $existing = $db->fetchOne("SELECT id FROM user_profiles WHERE user_id = ?", [$_SESSION['user_id']]);

    if ($existing) {
        $db->execute(
            "UPDATE user_profiles SET 
                full_name = ?, age = ?, gender = ?, date_of_birth = ?,
                state = ?, district = ?, occupation = ?, education = ?,
                annual_income = ?, category = ?, disability_status = ?,
                is_farmer = ?, is_student = ?
             WHERE user_id = ?",
            [
                $values['full_name'], $values['age'], $values['gender'], $values['date_of_birth'],
                $values['state'], $values['district'], $values['occupation'], $values['education'],
                $values['annual_income'], $values['category'], $values['disability_status'],
                $values['is_farmer'], $values['is_student'], $_SESSION['user_id']
            ]
        );
    } else {
        $db->insert(
            "INSERT INTO user_profiles (user_id, full_name, age, gender, date_of_birth, state, district, occupation, education, annual_income, category, disability_status, is_farmer, is_student) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $_SESSION['user_id'], $values['full_name'], $values['age'], $values['gender'],
                $values['date_of_birth'], $values['state'], $values['district'], $values['occupation'],
                $values['education'], $values['annual_income'], $values['category'],
                $values['disability_status'], $values['is_farmer'], $values['is_student']
            ]
        );
    }

    // Update user name
    $db->execute("UPDATE users SET full_name = ? WHERE id = ?", [$values['full_name'], $_SESSION['user_id']]);
    $_SESSION['user_name'] = $values['full_name'];

    jsonResponse(['success' => true, 'message' => 'Profile saved successfully!']);
}
