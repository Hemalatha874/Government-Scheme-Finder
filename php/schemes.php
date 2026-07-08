<?php
/**
 * Schemes API - List, Search, Filter, Details, Apply
 */
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listSchemes();
        break;
    case 'details':
        getSchemeDetails();
        break;
    case 'apply':
        applyScheme();
        break;
    case 'categories':
        getCategories();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function listSchemes() {
    $db = getDB();

    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $sort = $_GET['sort'] ?? 'latest';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 9)));
    $offset = ($page - 1) * $limit;

    // Profile-based filters
    $filters = [
        'min_age' => $_GET['min_age'] ?? null,
        'max_age' => $_GET['max_age'] ?? null,
        'gender' => $_GET['gender'] ?? null,
        'income' => $_GET['income'] ?? null,
        'occupation' => $_GET['occupation'] ?? null,
        'education' => $_GET['education'] ?? null,
        'state' => $_GET['state'] ?? null,
        'category_filter' => $_GET['category_filter'] ?? null,
        'disability' => $_GET['disability'] ?? null,
        'farmer' => $_GET['farmer'] ?? null,
        'student' => $_GET['student'] ?? null,
    ];

    $where = ["is_active = 1"];
    $params = [];

    if ($search) {
        $where[] = "(scheme_name LIKE ? OR description LIKE ? OR category LIKE ? OR benefits LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    if ($category && $category !== 'all') {
        $where[] = "category = ?";
        $params[] = $category;
    }

    if ($filters['gender'] && $filters['gender'] !== 'Any') {
        $where[] = "(gender_eligibility = ? OR gender_eligibility = 'Any')";
        $params[] = $filters['gender'];
    }

    if ($filters['farmer'] === 'Yes') {
        $where[] = "(farmer_eligibility = 'Yes' OR farmer_eligibility = 'Any')";
    }

    if ($filters['student'] === 'Yes') {
        $where[] = "(student_eligibility = 'Yes' OR student_eligibility = 'Any')";
    }

    if ($filters['disability'] === 'Yes') {
        $where[] = "(disability_eligibility = 'Yes' OR disability_eligibility = 'Any')";
    }

    if ($filters['income']) {
        $where[] = "(income_limit IS NULL OR income_limit >= ?)";
        $params[] = (float)$filters['income'];
    }

    if ($filters['min_age']) {
        $where[] = "max_age >= ?";
        $params[] = (int)$filters['min_age'];
    }

    if ($filters['max_age']) {
        $where[] = "min_age <= ?";
        $params[] = (int)$filters['max_age'];
    }

    if ($filters['category_filter'] && $filters['category_filter'] !== 'Any') {
        $where[] = "(caste_category = ? OR caste_category = 'Any')";
        $params[] = $filters['category_filter'];
    }

    if ($filters['state']) {
        $where[] = "(state_ut = 'All India' OR state_ut LIKE ?)";
        $params[] = "%{$filters['state']}%";
    }

    $whereClause = implode(' AND ', $where);

    $orderBy = match($sort) {
        'alphabetical' => 'scheme_name ASC',
        'latest' => 'last_updated DESC, scheme_name ASC',
        default => 'last_updated DESC'
    };

    $countSql = "SELECT COUNT(*) as total FROM schemes WHERE {$whereClause}";
    $total = $db->fetchOne($countSql, $params)['total'];

    $sql = "SELECT id, scheme_code, scheme_name, category, description, benefits,
                   min_age, max_age, income_limit, gender_eligibility, caste_category,
                   farmer_eligibility, student_eligibility, last_updated
            FROM schemes WHERE {$whereClause} ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}";

    $schemes = $db->fetchAll($sql, $params);

    jsonResponse([
        'success' => true,
        'schemes' => $schemes,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

function getSchemeDetails() {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'Scheme ID required'], 400);
    }

    $db = getDB();
    $scheme = $db->fetchOne("SELECT * FROM schemes WHERE id = ? AND is_active = 1", [$id]);

    if (!$scheme) {
        jsonResponse(['success' => false, 'message' => 'Scheme not found'], 404);
    }

    jsonResponse(['success' => true, 'scheme' => $scheme]);
}

function applyScheme() {
    requireLogin();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $schemeId = (int)($data['scheme_id'] ?? 0);

    if (!$schemeId) {
        jsonResponse(['success' => false, 'message' => 'Scheme ID required'], 400);
    }

    $db = getDB();
    $scheme = $db->fetchOne("SELECT * FROM schemes WHERE id = ?", [$schemeId]);
    if (!$scheme) {
        jsonResponse(['success' => false, 'message' => 'Scheme not found'], 404);
    }

    // Check if already applied
    $existing = $db->fetchOne(
        "SELECT id FROM applications WHERE user_id = ? AND scheme_id = ?",
        [$_SESSION['user_id'], $schemeId]
    );

    if ($existing) {
        jsonResponse(['success' => false, 'message' => 'You have already applied for this scheme'], 409);
    }

    $appId = $db->insert(
        "INSERT INTO applications (user_id, scheme_id, status) VALUES (?, ?, 'submitted')",
        [$_SESSION['user_id'], $schemeId]
    );

    jsonResponse([
        'success' => true,
        'message' => 'Application submitted successfully!',
        'application_id' => $appId,
        'official_link' => $scheme['official_application_link']
    ]);
}

function getCategories() {
    $db = getDB();
    $categories = $db->fetchAll(
        "SELECT category, COUNT(*) as count FROM schemes WHERE is_active = 1 GROUP BY category ORDER BY category"
    );
    jsonResponse(['success' => true, 'categories' => $categories]);
}
