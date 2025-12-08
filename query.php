<?php
/**
 * Režijska dela - API Endpoint
 * Vrača JSON podatke o delovnih urah v proizvodnji
 *
 * Parametri:
 * - period: current-month, prev-month, ytd
 * - type: current, previous (za primerjavo s predhodnim obdobjem)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Konfiguracija baze podatkov (SAP HANA)
$dbConfig = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '30015',
    'user' => getenv('DB_USER') ?: '',
    'password' => getenv('DB_PASSWORD') ?: '',
    'database' => getenv('DB_DATABASE') ?: 'BXBI'
];

/**
 * Vrne datumske meje glede na izbrano obdobje
 */
function getDateRange($period, $type = 'current') {
    $now = new DateTime();

    switch ($period) {
        case 'prev-month':
            $baseDate = (clone $now)->modify('-1 month');
            break;
        case 'ytd':
            $baseDate = $now;
            break;
        case 'current-month':
        default:
            $baseDate = $now;
            break;
    }

    // Za primerjavo s predhodnim obdobjem
    if ($type === 'previous') {
        switch ($period) {
            case 'prev-month':
                $baseDate->modify('-1 month');
                break;
            case 'ytd':
                $baseDate->modify('-1 year');
                break;
            case 'current-month':
            default:
                $baseDate->modify('-1 month');
                break;
        }
    }

    if ($period === 'ytd') {
        $startDate = (clone $baseDate)->modify('first day of January')->format('Y-m-d');
        $endDate = (clone $baseDate)->format('Y-m-d');
    } else {
        $startDate = (clone $baseDate)->modify('first day of this month')->format('Y-m-d');
        $endDate = (clone $baseDate)->modify('last day of this month')->format('Y-m-d');
    }

    return ['start' => $startDate, 'end' => $endDate];
}

/**
 * Zgradi SQL poizvedbo z datumskimi filtri
 */
function buildQuery($dateRange) {
    return "
        SELECT
            TO_VARCHAR(act.\"Date Created\", 'DD.MM.YYYY') AS \"Date Created\",
            TO_VARCHAR(act.\"Date Posting\", 'YYYY-MM-DD') AS \"Date Posting\",
            act.\"Actual Start Time\",
            act.\"Actual End Time\",
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

        FROM \"BXBI\".\"vFT PP V_OP_TIME_ACT\" act

        LEFT JOIN \"BXBI\".\"vMD XA Order Operation\" op
            ON act.\"Order ID\" = op.\"Order ID\"
            AND act.\"Operation ID\" = op.\"Operation ID\"

        LEFT JOIN \"BXBI\".\"vMD HR Person\" per
            ON act.\"Person ID\" = per.\"Person ID\"

        LEFT JOIN \"BXBI\".\"vMD XA Order Data\" ordd
            ON act.\"Order ID\" = ordd.\"Order ID\"

        WHERE act.\"Date Posting\" BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            AND act.\"Report Data Version\" = 'Actual'

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
            act.\"Actual Start Time\",
            act.\"Actual End Time\",
            ordd.\"Order Name\",
            ordd.\"Order Type ID Name\"

        ORDER BY act.\"Date Posting\" DESC
    ";
}

/**
 * Vzpostavi povezavo z bazo SAP HANA
 */
function connectToDatabase($config) {
    // Poskusi z ODBC (SAP HANA)
    if (function_exists('odbc_connect')) {
        $dsn = "Driver={HDBODBC};ServerNode={$config['host']}:{$config['port']};";
        $conn = @odbc_connect($dsn, $config['user'], $config['password']);
        if ($conn) {
            return ['type' => 'odbc', 'conn' => $conn];
        }
    }

    // Poskusi z PDO (za testiranje z drugimi bazami)
    if (class_exists('PDO')) {
        try {
            // SAP HANA preko PDO
            $dsn = "hana:host={$config['host']};port={$config['port']}";
            $pdo = new PDO($dsn, $config['user'], $config['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return ['type' => 'pdo', 'conn' => $pdo];
        } catch (PDOException $e) {
            // Ignoriraj in poskusi demo način
        }
    }

    return null;
}

/**
 * Izvede poizvedbo in vrne rezultate
 */
function executeQuery($connection, $query) {
    if (!$connection) {
        return null;
    }

    $results = [];

    if ($connection['type'] === 'odbc') {
        $result = odbc_exec($connection['conn'], $query);
        if ($result) {
            while ($row = odbc_fetch_array($result)) {
                $results[] = $row;
            }
            odbc_free_result($result);
        }
    } elseif ($connection['type'] === 'pdo') {
        $stmt = $connection['conn']->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $results;
}

/**
 * Generira demo podatke za testiranje
 */
function generateDemoData($dateRange) {
    $employees = [
        ['id' => 'E001', 'name' => 'Janez Novak', 'costCenter' => 'CC-MP-001', 'group' => 'Proizvodnja', 'location' => 'Mirna Peč'],
        ['id' => 'E002', 'name' => 'Marija Horvat', 'costCenter' => 'CC-MP-002', 'group' => 'Proizvodnja', 'location' => 'Mirna Peč'],
        ['id' => 'E003', 'name' => 'Peter Krajnc', 'costCenter' => 'CC-SO-001', 'group' => 'Vzdrževanje', 'location' => 'Sora'],
        ['id' => 'E004', 'name' => 'Ana Zupan', 'costCenter' => 'CC-SO-002', 'group' => 'Proizvodnja', 'location' => 'Sora'],
        ['id' => 'E005', 'name' => 'Tomaž Kos', 'costCenter' => 'CC-MP-003', 'group' => 'Logistika', 'location' => 'Mirna Peč'],
        ['id' => 'E006', 'name' => 'Maja Vidmar', 'costCenter' => 'CC-SO-003', 'group' => 'Kontrola kakovosti', 'location' => 'Sora'],
        ['id' => 'E007', 'name' => 'Rok Potočnik', 'costCenter' => 'CC-MP-001', 'group' => 'Proizvodnja', 'location' => 'Mirna Peč'],
        ['id' => 'E008', 'name' => 'Nina Kovač', 'costCenter' => 'CC-SO-001', 'group' => 'Vzdrževanje', 'location' => 'Sora'],
        ['id' => 'E009', 'name' => 'Luka Mlakar', 'costCenter' => 'CC-MP-002', 'group' => 'Proizvodnja', 'location' => 'Mirna Peč'],
        ['id' => 'E010', 'name' => 'Eva Kern', 'costCenter' => 'CC-SO-002', 'group' => 'Logistika', 'location' => 'Sora'],
        ['id' => 'E011', 'name' => 'Matej Rupnik', 'costCenter' => 'CC-MP-003', 'group' => 'Vzdrževanje', 'location' => 'Mirna Peč'],
        ['id' => 'E012', 'name' => 'Sara Oblak', 'costCenter' => 'CC-SO-003', 'group' => 'Proizvodnja', 'location' => 'Sora'],
    ];

    $orders = [
        ['id' => '1167362', 'name' => 'Režijsko delo - čiščenje', 'type' => 'Režijski nalog'],
        ['id' => '1167363', 'name' => 'Režijsko delo - vzdrževanje', 'type' => 'Režijski nalog'],
        ['id' => '1167364', 'name' => 'Režijsko delo - transport', 'type' => 'Režijski nalog'],
        ['id' => '1167365', 'name' => 'Režijsko delo - inventura', 'type' => 'Režijski nalog'],
        ['id' => '1167366', 'name' => 'Režijsko delo - usposabljanje', 'type' => 'Režijski nalog'],
        ['id' => '1167367', 'name' => 'Režijsko delo - sestanki', 'type' => 'Režijski nalog'],
        ['id' => '1167368', 'name' => 'Režijsko delo - admin', 'type' => 'Režijski nalog'],
    ];

    $workCenters = [
        ['id' => 'WC-001', 'name' => 'Montaža 1'],
        ['id' => 'WC-002', 'name' => 'Montaža 2'],
        ['id' => 'WC-003', 'name' => 'Pakirnica'],
        ['id' => 'WC-004', 'name' => 'Skladišče'],
        ['id' => 'WC-005', 'name' => 'Kontrola'],
    ];

    $operations = [
        'Čiščenje delovnega mesta',
        'Preventivno vzdrževanje',
        'Transport materiala',
        'Popis zalog',
        'Interno usposabljanje',
        'Pregled opreme',
        'Administrativna dela',
    ];

    $data = [];
    $startDate = new DateTime($dateRange['start']);
    $endDate = new DateTime($dateRange['end']);

    // Generiraj zapise za vsak delovni dan
    $currentDate = clone $startDate;
    while ($currentDate <= $endDate) {
        $dayOfWeek = (int)$currentDate->format('N');

        // Preskoči vikende
        if ($dayOfWeek >= 6) {
            $currentDate->modify('+1 day');
            continue;
        }

        // Naključno število zapisov za ta dan (8-20)
        $entriesCount = rand(8, 20);

        for ($i = 0; $i < $entriesCount; $i++) {
            $employee = $employees[array_rand($employees)];
            $order = $orders[array_rand($orders)];
            $workCenter = $workCenters[array_rand($workCenters)];
            $operation = $operations[array_rand($operations)];

            // Naključni čas začetka (6:00 - 14:00)
            $startHour = rand(6, 14);
            $startMinute = rand(0, 3) * 15;

            // Trajanje (0.5 - 4 ure)
            $durationHours = rand(1, 8) * 0.5;

            $endHour = $startHour + floor($durationHours);
            $endMinute = $startMinute + (int)(($durationHours - floor($durationHours)) * 60);
            if ($endMinute >= 60) {
                $endHour++;
                $endMinute -= 60;
            }

            $data[] = [
                'Date Created' => $currentDate->format('d.m.Y'),
                'Date Posting' => $currentDate->format('Y-m-d'),
                'Order ID' => $order['id'],
                'Order Name' => $order['name'],
                'Order Type ID Name' => $order['type'],
                'Person ID' => $employee['id'],
                'Operation ID' => 'OP' . rand(100, 999),
                'Operation Text' => $operation,
                'Order Sequence Operation ID Text' => 'SEQ-' . rand(1, 10),
                'Order Operation ID Text' => 'OP-' . rand(1, 50),
                'Work Center Name' => $workCenter['name'],
                'Work Center ID Name' => $workCenter['id'],
                'Person Name' => $employee['name'],
                'Person Cost Center ID Name' => $employee['costCenter'],
                'Person Group Name' => $employee['group'],
                'Location' => $employee['location'],
                'Actual Start Time' => sprintf('%02d:%02d:00', $startHour, $startMinute),
                'Actual End Time' => sprintf('%02d:%02d:00', min((int)$endHour, 22), (int)$endMinute),
                'M Hours' => $durationHours,
            ];
        }

        $currentDate->modify('+1 day');
    }

    return $data;
}

// Glavna logika
try {
    $period = $_GET['period'] ?? 'current-month';
    $type = $_GET['type'] ?? 'current';

    // Validiraj parametre
    $validPeriods = ['current-month', 'prev-month', 'ytd'];
    $validTypes = ['current', 'previous'];

    if (!in_array($period, $validPeriods)) {
        $period = 'current-month';
    }
    if (!in_array($type, $validTypes)) {
        $type = 'current';
    }

    // Pridobi datumski obseg
    $dateRange = getDateRange($period, $type);

    // Poskusi se povezati z bazo
    $connection = connectToDatabase($dbConfig);

    if ($connection) {
        // Izvedi poizvedbo
        $query = buildQuery($dateRange);
        $results = executeQuery($connection, $query);

        // Zapri povezavo
        if ($connection['type'] === 'odbc') {
            odbc_close($connection['conn']);
        }

        if ($results !== null) {
            echo json_encode($results, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Če ni povezave z bazo, vrni demo podatke
    $demoData = generateDemoData($dateRange);
    echo json_encode($demoData, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Napaka pri pridobivanju podatkov',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
