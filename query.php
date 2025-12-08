<?php
/**
 * Režijska dela - API za pridobivanje podatkov iz SAP HANA
 * Lokacija: C:\BriPHP\bxroot\apps\overhead-tracker\query.php
 */

// Debug mode - nastavi na true za prikaz napak
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

header('Content-Type: application/json; charset=utf-8');

// Preveri ali db_config.php obstaja
$dbConfigPath = __DIR__ . '/../../db_config.php';
if (!file_exists($dbConfigPath)) {
    echo json_encode(['error' => 'db_config.php ne obstaja na poti: ' . $dbConfigPath], JSON_UNESCAPED_UNICODE);
    exit;
}

// Vključi centralno konfiguracijo
require_once $dbConfigPath;

// Preveri ali funkcije obstajajo
if (!function_exists('connectHana')) {
    echo json_encode(['error' => 'Funkcija connectHana() ne obstaja v db_config.php'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!function_exists('executeQuery')) {
    echo json_encode(['error' => 'Funkcija executeQuery() ne obstaja v db_config.php'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Pridobi delovne naloge za režijska dela
 * @param string $period - current-month, prev-month, ytd
 * @param string $type - current ali previous (za primerjavo)
 * @return array
 */
function getOverheadWorkOrders($period = 'current-month', $type = 'current', $debug = false) {
    $conn = connectHana();

    if (!$conn) {
        $err = ['error' => 'Povezava na bazo ni uspela'];
        if ($debug) {
            $err['odbc_error'] = odbc_errormsg();
        }
        return $err;
    }

    // Določi datumsko obdobje
    $dateCondition = getDateCondition($period, $type);

    // Seznam ID-jev delovnih nalogov za režijska dela
    $orderIds = [
        '1167362', '1167363', '1167364', '1167365', '1167366',
        '1167367', '1167368', '1167369', '1167370', '1167371',
        '1167372', '1167373', '1167374'
    ];
    $orderIdsStr = "'" . implode("','", $orderIds) . "'";

    $sql = "
    SELECT
        TO_VARCHAR(act.\"Date Created\", 'DD.MM.YYYY') AS \"Date Created\",
        TO_VARCHAR(act.\"Date Posting\", 'YYYY-MM-DD') AS \"Date Posting\",
        act.\"Order ID\",
        ordd.\"Order Name\",
        ordd.\"Order Type ID Name\",
        act.\"Person ID\",
        act.\"Operation ID\",
        op.\"Operation Text\",
        op.\"Order Sequence Operation ID Text\",
        op.\"Order Operation ID Text\",
        op.\"Work Center Name\",
        op.\"Work Center ID Name\",
        per.\"Person Name\",
        per.\"Person Cost Center ID Name\",
        per.\"Person Group Name\",
        CASE
            WHEN per.\"Person Cost Center ID Name\" LIKE '%MP%' THEN 'Mirna Peč'
            ELSE 'Sora'
        END AS \"Location\",
        SUM(act.\"M Labor ACT H\") AS \"M Hours\"

    FROM \"BXBI\".\"vFT PP Order Activities\" act

    LEFT JOIN \"BXBI\".\"vMD XA Order Operation\" op
        ON act.\"Order ID\" = op.\"Order ID\"
        AND act.\"Operation ID\" = op.\"Operation ID\"

    LEFT JOIN \"BXBI\".\"vMD HR Person\" per
        ON act.\"Person ID\" = per.\"Person ID\"

    LEFT JOIN \"BXBI\".\"vMD XA Order Data\" ordd
        ON act.\"Order ID\" = ordd.\"Order ID\"

    WHERE act.\"Order ID\" IN({$orderIdsStr})
        AND act.\"Report Data Version\" = 'Actual'
        {$dateCondition}

    GROUP BY
        TO_VARCHAR(act.\"Date Created\", 'DD.MM.YYYY'),
        TO_VARCHAR(act.\"Date Posting\", 'YYYY-MM-DD'),
        act.\"Order ID\",
        act.\"Person ID\",
        act.\"Operation ID\",
        op.\"Operation Text\",
        op.\"Order Sequence Operation ID Text\",
        op.\"Order Operation ID Text\",
        op.\"Work Center Name\",
        op.\"Work Center ID Name\",
        per.\"Person Name\",
        per.\"Person Cost Center ID Name\",
        per.\"Person Group Name\",
        ordd.\"Order Name\",
        ordd.\"Order Type ID Name\"

    ORDER BY act.\"Order ID\", per.\"Person Name\", TO_VARCHAR(act.\"Date Posting\", 'YYYY-MM-DD') DESC
    ";

    $result = executeQuery($conn, $sql);

    if ($result === false) {
        $err = ['error' => 'Napaka pri izvajanju poizvedbe'];
        if ($debug) {
            $err['odbc_error'] = odbc_errormsg($conn);
            $err['sql_preview'] = substr($sql, 0, 500) . '...';
        }
        odbc_close($conn);
        return $err;
    }

    odbc_close($conn);

    // Pretvori decimalne vrednosti
    foreach ($result as &$row) {
        if (isset($row['M Hours'])) {
            $row['M Hours'] = floatval(str_replace(',', '.', $row['M Hours']));
        }
    }

    return $result;
}

/**
 * Sestavi datumski pogoj za SQL
 * @param string $period
 * @param string $type
 * @return string
 */
function getDateCondition($period, $type) {
    $now = new DateTime();

    switch ($period) {
        case 'current-month':
            if ($type === 'current') {
                $start = $now->format('Y-m-01');
                $end = $now->format('Y-m-t');
            } else {
                // Prejšnji mesec za primerjavo
                $now->modify('-1 month');
                $start = $now->format('Y-m-01');
                $end = $now->format('Y-m-t');
            }
            break;

        case 'prev-month':
            if ($type === 'current') {
                $now->modify('-1 month');
            } else {
                $now->modify('-2 months');
            }
            $start = $now->format('Y-m-01');
            $end = $now->format('Y-m-t');
            break;

        case 'ytd':
            if ($type === 'current') {
                $start = $now->format('Y-01-01');
                $end = $now->format('Y-m-d');
            } else {
                // Lani YTD
                $now->modify('-1 year');
                $start = $now->format('Y-01-01');
                $end = $now->format('Y-m-d');
            }
            break;

        case 'all':
            // Brez datumskega filtra - vsi podatki
            return "";

        default:
            $start = $now->format('Y-m-01');
            $end = $now->format('Y-m-t');
    }

    // SAP HANA format za datum
    return "AND act.\"Date Posting\" BETWEEN TO_DATE('{$start}', 'YYYY-MM-DD') AND TO_DATE('{$end}', 'YYYY-MM-DD')";
}

// Obdelava zahteve
$period = isset($_GET['period']) ? $_GET['period'] : 'current-month';
$type = isset($_GET['type']) ? $_GET['type'] : 'current';

// Validiraj vhodne parametre
$validPeriods = ['current-month', 'prev-month', 'ytd', 'all'];
$validTypes = ['current', 'previous'];

if (!in_array($period, $validPeriods)) {
    $period = 'current-month';
}
if (!in_array($type, $validTypes)) {
    $type = 'current';
}

$data = getOverheadWorkOrders($period, $type, $debug);

// Dodaj debug info
if ($debug) {
    $data = [
        'debug_info' => [
            'period' => $period,
            'type' => $type,
            'db_config_path' => $dbConfigPath,
            'record_count' => is_array($data) && !isset($data['error']) ? count($data) : 0
        ],
        'data' => $data
    ];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
