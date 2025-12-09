<?php
/**
 * Test skripta za debugiranje
 */
header('Content-Type: application/json; charset=utf-8');

$result = ['step' => 0, 'info' => []];

try {
    // Step 1: Basic PHP works
    $result['step'] = 1;
    $result['info']['php_version'] = PHP_VERSION;

    // Step 2: Check paths
    $result['step'] = 2;
    $result['info']['dir'] = __DIR__;

    // Step 3: Check PHPMailer paths
    $result['step'] = 3;
    $phpmailerPath1 = __DIR__ . '/../../lib/PHPMailer/src/';
    $phpmailerPath2 = __DIR__ . '/../../lib/PHPMailer/';
    $result['info']['phpmailer_src_exists'] = is_dir($phpmailerPath1);
    $result['info']['phpmailer_exists'] = is_dir($phpmailerPath2);

    // Step 4: Check db_config
    $result['step'] = 4;
    $dbConfigPath = __DIR__ . '/../../db_config.php';
    $result['info']['db_config_exists'] = file_exists($dbConfigPath);
    $result['info']['db_config_path'] = $dbConfigPath;

    // Step 5: Try loading PHPMailer
    $result['step'] = 5;
    $phpmailerPath = is_dir($phpmailerPath1) ? $phpmailerPath1 : $phpmailerPath2;
    $result['info']['using_path'] = $phpmailerPath;

    $files = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
    foreach ($files as $f) {
        $fullPath = $phpmailerPath . $f;
        $result['info']['file_' . $f] = file_exists($fullPath);
    }

    // Step 6: Try require PHPMailer
    $result['step'] = 6;
    if (file_exists($phpmailerPath . 'Exception.php')) {
        require_once $phpmailerPath . 'Exception.php';
        $result['info']['loaded_exception'] = true;
    }
    if (file_exists($phpmailerPath . 'PHPMailer.php')) {
        require_once $phpmailerPath . 'PHPMailer.php';
        $result['info']['loaded_phpmailer'] = true;
    }
    if (file_exists($phpmailerPath . 'SMTP.php')) {
        require_once $phpmailerPath . 'SMTP.php';
        $result['info']['loaded_smtp'] = true;
    }

    // Step 7: Try loading db_config
    $result['step'] = 7;
    if (file_exists($dbConfigPath)) {
        require_once $dbConfigPath;
        $result['info']['loaded_db_config'] = true;
        $result['info']['connectHana_exists'] = function_exists('connectHana');
        $result['info']['executeQuery_exists'] = function_exists('executeQuery');
    }

    // Step 8: Try connecting to database
    $result['step'] = 8;
    if (function_exists('connectHana')) {
        $conn = connectHana();
        $result['info']['db_connected'] = ($conn !== false && $conn !== null);
    }

    // Step 9: Check PHPMailer class
    $result['step'] = 9;
    $result['info']['phpmailer_class_exists'] = class_exists('PHPMailer');
    $result['info']['phpmailer_ns_class_exists'] = class_exists('PHPMailer\\PHPMailer\\PHPMailer');

    // Step 10: Try creating PHPMailer instance
    $result['step'] = 10;
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $result['info']['phpmailer_version'] = '6.x (namespace)';
    } elseif (class_exists('PHPMailer')) {
        $mail = new PHPMailer(true);
        $result['info']['phpmailer_version'] = '5.x (no namespace)';
    } else {
        $result['info']['phpmailer_version'] = 'NOT FOUND';
    }
    $result['info']['mail_created'] = isset($mail);

    // Step 11: Test a simple query
    $result['step'] = 11;
    if (function_exists('executeQuery') && isset($conn)) {
        $testSql = "SELECT TOP 1 * FROM \"BXBI\".\"vDIM PP Order Details\" WHERE \"Order Name\" LIKE '%reži%'";
        $testData = executeQuery($conn, $testSql);
        $result['info']['test_query_rows'] = count($testData);
    }

    // Step 12: Test the actual query from send_report.php
    $result['step'] = 12;
    $prevMonthStart = date('Y-m-01', strtotime('first day of previous month'));
    $prevMonthEnd = date('Y-m-t', strtotime('last day of previous month'));
    $result['info']['period'] = $prevMonthStart . ' to ' . $prevMonthEnd;

    $sql = "
    SELECT TOP 10
        TO_VARCHAR(act.\"Date Created\", 'DD.MM.YYYY') AS \"Date Created\",
        TO_VARCHAR(act.\"Date Posting\", 'YYYY-MM-DD') AS \"Date Posting\",
        act.\"Actual Start Time\",
        act.\"Actual End Time\",
        act.\"Order ID\",
        ordd.\"Order Name\",
        act.\"Person ID\",
        per.\"Person Name\",
        per.\"Person Cost Center ID Name\",
        SUM(act.\"M Labor ACT H\") AS \"M Hours\"
    FROM \"BXBI\".\"vFT PP V_OP_TIME_ACT\" act
    LEFT JOIN \"BXBI\".\"vDIM PP Order Details\" ordd ON act.\"Order ID\" = ordd.\"Order ID\"
    LEFT JOIN \"BXBI\".\"vDIM Person\" per ON act.\"Person ID\" = per.\"Person ID\"
    WHERE ordd.\"Order Name\" LIKE '%reži%'
        AND act.\"Date Posting\" >= TO_DATE('$prevMonthStart', 'YYYY-MM-DD')
        AND act.\"Date Posting\" <= TO_DATE('$prevMonthEnd', 'YYYY-MM-DD')
    GROUP BY
        act.\"Date Created\",
        act.\"Date Posting\",
        act.\"Actual Start Time\",
        act.\"Actual End Time\",
        act.\"Order ID\",
        ordd.\"Order Name\",
        act.\"Person ID\",
        per.\"Person Name\",
        per.\"Person Cost Center ID Name\"
    ";

    $data = executeQuery($conn, $sql);
    $result['info']['full_query_rows'] = count($data);
    if (count($data) > 0) {
        $result['info']['sample_row'] = array_keys($data[0]);
    }

    $result['success'] = true;
    $result['message'] = 'Vsi koraki uspešni';

} catch (Exception $e) {
    $result['success'] = false;
    $result['error'] = $e->getMessage();
    $result['error_file'] = basename($e->getFile());
    $result['error_line'] = $e->getLine();
} catch (Error $e) {
    $result['success'] = false;
    $result['error'] = $e->getMessage();
    $result['error_file'] = basename($e->getFile());
    $result['error_line'] = $e->getLine();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
