<?php
/**
 * Eligibility Checker API
 * Compares user profile against government scheme database
 */
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'check':
        checkEligibility();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function checkEligibility() {
    requireLogin();
    $db = getDB();

    $profile = $db->fetchOne(
        "SELECT * FROM user_profiles WHERE user_id = ?",
        [$_SESSION['user_id']]
    );

    if (!$profile || empty($profile['age'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Please complete your profile before checking eligibility.',
            'redirect' => 'profile.html'
        ], 400);
    }

    $schemes = $db->fetchAll("SELECT * FROM schemes WHERE is_active = 1");
    $results = ['eligible' => [], 'partially_eligible' => [], 'not_eligible' => []];

    foreach ($schemes as $scheme) {
        $evaluation = evaluateScheme($profile, $scheme);
        $schemeData = formatSchemeResult($scheme, $evaluation);

        switch ($evaluation['status']) {
            case 'eligible':
                $results['eligible'][] = $schemeData;
                break;
            case 'partial':
                $results['partially_eligible'][] = $schemeData;
                break;
            default:
                $results['not_eligible'][] = $schemeData;
                break;
        }
    }

    jsonResponse([
        'success' => true,
        'profile' => $profile,
        'results' => $results,
        'summary' => [
            'eligible' => count($results['eligible']),
            'partially_eligible' => count($results['partially_eligible']),
            'not_eligible' => count($results['not_eligible']),
            'total' => count($schemes)
        ]
    ]);
}

function evaluateScheme($profile, $scheme) {
    $passed = 0;
    $total = 0;
    $reasons = [];

    // Age check
    $total++;
    $age = (int)$profile['age'];
    if ($age >= $scheme['min_age'] && $age <= $scheme['max_age']) {
        $passed++;
    } else {
        $reasons[] = "Age requirement: {$scheme['min_age']}-{$scheme['max_age']} years";
    }

    // Income check
    if ($scheme['income_limit'] !== null) {
        $total++;
        $income = (float)($profile['annual_income'] ?? 0);
        if ($income <= $scheme['income_limit']) {
            $passed++;
        } else {
            $reasons[] = "Income limit: Rs. " . number_format($scheme['income_limit']);
        }
    }

    // Gender check
    if ($scheme['gender_eligibility'] !== 'Any') {
        $total++;
        if ($profile['gender'] === $scheme['gender_eligibility'] || $profile['gender'] === 'Any') {
            $passed++;
        } else {
            $reasons[] = "Gender: {$scheme['gender_eligibility']} only";
        }
    }

    // Category check
    if ($scheme['caste_category'] !== 'Any') {
        $total++;
        if ($profile['category'] === $scheme['caste_category'] || $profile['category'] === 'Any') {
            $passed++;
        } else {
            $reasons[] = "Category: {$scheme['caste_category']}";
        }
    }

    // Disability check
    if ($scheme['disability_eligibility'] === 'Yes') {
        $total++;
        if ($profile['disability_status'] === 'Yes') {
            $passed++;
        } else {
            $reasons[] = "Disability certificate required";
        }
    }

    // Farmer check
    if ($scheme['farmer_eligibility'] === 'Yes') {
        $total++;
        if ($profile['is_farmer'] === 'Yes') {
            $passed++;
        } else {
            $reasons[] = "Must be a farmer";
        }
    } elseif ($scheme['farmer_eligibility'] === 'No') {
        $total++;
        if ($profile['is_farmer'] === 'No') {
            $passed++;
        } else {
            $reasons[] = "Not applicable for farmers";
        }
    }

    // Student check
    if ($scheme['student_eligibility'] === 'Yes') {
        $total++;
        if ($profile['is_student'] === 'Yes') {
            $passed++;
        } else {
            $reasons[] = "Must be a student";
        }
    } elseif ($scheme['student_eligibility'] === 'No') {
        $total++;
        if ($profile['is_student'] === 'No') {
            $passed++;
        } else {
            $reasons[] = "Not applicable for students";
        }
    }

    // State check
    if ($scheme['state_ut'] !== 'All India' && !empty($profile['state'])) {
        $total++;
        if (stripos($scheme['state_ut'], $profile['state']) !== false) {
            $passed++;
        } else {
            $reasons[] = "State: {$scheme['state_ut']}";
        }
    }

    // Determine status
    if ($total === 0 || $passed === $total) {
        $status = 'eligible';
    } elseif ($passed >= ($total * 0.6)) {
        $status = 'partial';
    } else {
        $status = 'not_eligible';
    }

    return ['status' => $status, 'passed' => $passed, 'total' => $total, 'reasons' => $reasons];
}

function formatSchemeResult($scheme, $evaluation) {
    return [
        'id' => $scheme['id'],
        'scheme_code' => $scheme['scheme_code'],
        'scheme_name' => $scheme['scheme_name'],
        'category' => $scheme['category'],
        'description' => $scheme['description'],
        'benefits' => $scheme['benefits'],
        'eligibility_status' => $evaluation['status'],
        'match_score' => $evaluation['total'] > 0 ? round(($evaluation['passed'] / $evaluation['total']) * 100) : 100,
        'reasons' => $evaluation['reasons'],
        'official_website' => $scheme['official_website'],
        'last_updated' => $scheme['last_updated']
    ];
}
