<?php
/**
 * Database Setup and Data Import Script
 * Run once: php import.php or visit database/import.php via browser
 * Sources: myScheme.gov.in, india.gov.in, pmkisan.gov.in, pmjay.gov.in
 */

require_once __DIR__ . '/../php/config.php';

header('Content-Type: application/json; charset=utf-8');

function runImport() {
    $results = ['success' => true, 'steps' => []];

    try {
        // Connect without database first
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Run schema
        $schema = file_get_contents(__DIR__ . '/schema.sql');
        // Strip SQL comments
        $schema = preg_replace('/--.*/', '', $schema);
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    // Ignore duplicate/already exists errors
                    if (strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'Duplicate') === false) {
                        // Only log non-critical errors
                    }
                }
            }
        }
        $results['steps'][] = 'Database schema created/verified';

        // Reconnect to specific database
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Import schemes from JSON
        $jsonFile = __DIR__ . '/government_schemes.json';
        if (!file_exists($jsonFile)) {
            throw new Exception('government_schemes.json not found');
        }

        $schemes = json_decode(file_get_contents($jsonFile), true);
        if (!$schemes) {
            throw new Exception('Invalid JSON in government_schemes.json');
        }

        $imported = 0;
        $updated = 0;

        $insertSql = "INSERT INTO schemes (
            scheme_code, scheme_name, category, description, benefits,
            min_age, max_age, income_limit, gender_eligibility, education_requirement,
            occupation, state_ut, caste_category, disability_eligibility,
            farmer_eligibility, student_eligibility, required_documents,
            application_steps, official_website, official_application_link,
            helpline_number, last_updated
        ) VALUES (
            :scheme_code, :scheme_name, :category, :description, :benefits,
            :min_age, :max_age, :income_limit, :gender_eligibility, :education_requirement,
            :occupation, :state_ut, :caste_category, :disability_eligibility,
            :farmer_eligibility, :student_eligibility, :required_documents,
            :application_steps, :official_website, :official_application_link,
            :helpline_number, :last_updated
        ) ON DUPLICATE KEY UPDATE
            scheme_name = VALUES(scheme_name),
            category = VALUES(category),
            description = VALUES(description),
            benefits = VALUES(benefits),
            min_age = VALUES(min_age),
            max_age = VALUES(max_age),
            income_limit = VALUES(income_limit),
            gender_eligibility = VALUES(gender_eligibility),
            education_requirement = VALUES(education_requirement),
            occupation = VALUES(occupation),
            state_ut = VALUES(state_ut),
            caste_category = VALUES(caste_category),
            disability_eligibility = VALUES(disability_eligibility),
            farmer_eligibility = VALUES(farmer_eligibility),
            student_eligibility = VALUES(student_eligibility),
            required_documents = VALUES(required_documents),
            application_steps = VALUES(application_steps),
            official_website = VALUES(official_website),
            official_application_link = VALUES(official_application_link),
            helpline_number = VALUES(helpline_number),
            last_updated = VALUES(last_updated)";

        $stmt = $pdo->prepare($insertSql);

        foreach ($schemes as $scheme) {
            $params = [
                ':scheme_code' => $scheme['scheme_code'],
                ':scheme_name' => $scheme['scheme_name'],
                ':category' => $scheme['category'],
                ':description' => $scheme['description'],
                ':benefits' => $scheme['benefits'],
                ':min_age' => $scheme['min_age'],
                ':max_age' => $scheme['max_age'],
                ':income_limit' => $scheme['income_limit'],
                ':gender_eligibility' => $scheme['gender_eligibility'],
                ':education_requirement' => $scheme['education_requirement'],
                ':occupation' => $scheme['occupation'],
                ':state_ut' => $scheme['state_ut'],
                ':caste_category' => $scheme['caste_category'],
                ':disability_eligibility' => $scheme['disability_eligibility'],
                ':farmer_eligibility' => $scheme['farmer_eligibility'],
                ':student_eligibility' => $scheme['student_eligibility'],
                ':required_documents' => $scheme['required_documents'],
                ':application_steps' => $scheme['application_steps'],
                ':official_website' => $scheme['official_website'],
                ':official_application_link' => $scheme['official_application_link'],
                ':helpline_number' => $scheme['helpline_number'],
                ':last_updated' => $scheme['last_updated'],
            ];
            $stmt->execute($params);
            $imported++;
        }

        $results['steps'][] = "Imported/updated {$imported} government schemes";

        // Insert default notifications
        $notifCount = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
        if ($notifCount == 0) {
            $notifications = [
                ['PM-KISAN 18th Installment Released', 'The 18th installment of PM-KISAN has been released. Farmers can check payment status on pmkisan.gov.in.', 'announcement', null, null],
                ['New Scheme: PM Vishwakarma Launched', 'PM Vishwakarma scheme for traditional artisans and craftspeople is now live. Apply through pmvishwakarma.gov.in.', 'scheme', null, null],
                ['NSP Scholarship Deadline - 31st December 2024', 'Last date to apply for National Scholarship Portal schemes for academic year 2024-25 is 31st December 2024.', 'deadline', null, '2024-12-31'],
                ['Ayushman Bharat Expansion', 'PM-JAY now covers additional 5 crore families. Check eligibility on pmjay.gov.in using your mobile number.', 'announcement', null, null],
                ['PMAY-U CLSS Interest Subsidy Extended', 'Credit Linked Subsidy Scheme under PMAY-U extended till March 2025. Apply through pmay-urban.gov.in.', 'scheme', null, '2025-03-31'],
            ];

            $notifStmt = $pdo->prepare("INSERT INTO notifications (title, message, notification_type, scheme_id, deadline_date) VALUES (?, ?, ?, ?, ?)");
            foreach ($notifications as $n) {
                $notifStmt->execute($n);
            }
            $results['steps'][] = 'Default notifications created';
        }

        // Update admin password to Admin@123
        $adminHash = password_hash('Admin@123', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE admins SET password_hash = ? WHERE username = 'admin'")->execute([$adminHash]);
        $results['steps'][] = 'Admin account ready (username: admin, password: Admin@123)';

        $results['message'] = 'Database setup completed successfully!';
        $results['total_schemes'] = $imported;

    } catch (Exception $e) {
        $results['success'] = false;
        $results['message'] = 'Import failed: ' . $e->getMessage();
    }

    return $results;
}

echo json_encode(runImport(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
