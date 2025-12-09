<?php
/**
 * Režijska dela - Mesečno poročilo po emailu
 * Pošlje podatke za PREJŠNJI mesec
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vključi query.php za uporabo obstoječih funkcij
require_once __DIR__ . '/../../db_config.php';

// PHPMailer
$phpmailerPath = __DIR__ . '/../../lib/PHPMailer/';
if (!file_exists($phpmailerPath . 'PHPMailer.php')) {
    die(json_encode(['success' => false, 'error' => 'PHPMailer ne obstaja']));
}
require_once $phpmailerPath . 'Exception.php';
require_once $phpmailerPath . 'PHPMailer.php';
require_once $phpmailerPath . 'SMTP.php';

// Datum za prejšnji mesec
$prevMonth = new DateTime('first day of previous month');
$prevMonthStart = $prevMonth->format('Y-m-01');
$prevMonthEnd = $prevMonth->format('Y-m-t');

$meseci = [1=>'Januar',2=>'Februar',3=>'Marec',4=>'April',5=>'Maj',6=>'Junij',
           7=>'Julij',8=>'Avgust',9=>'September',10=>'Oktober',11=>'November',12=>'December'];
$mesec = $meseci[(int)$prevMonth->format('n')];
$leto = $prevMonth->format('Y');
$obdobje = "$mesec $leto";

// Povezava na bazo
$conn = connectHana();
if (!$conn) {
    die(json_encode(['success' => false, 'error' => 'Povezava na bazo ni uspela']));
}

// ISTI query kot v query.php - DOKAZANO DELUJE
$orderIds = ['1167362','1167363','1167364','1167365','1167366','1167367','1167368','1167369','1167370','1167371','1167372','1167373','1167374'];
$orderIdsStr = "'" . implode("','", $orderIds) . "'";
$dateCondition = "AND act.\"Date Posting\" BETWEEN TO_DATE('" . $prevMonthStart . "', 'YYYY-MM-DD') AND TO_DATE('" . $prevMonthEnd . "', 'YYYY-MM-DD')";

$sql = "
SELECT
    TO_VARCHAR(act.\"Date Created\", 'DD.MM.YYYY') AS \"Date Created\",
    TO_VARCHAR(act.\"Date Posting\", 'YYYY-MM-DD') AS \"Date Posting\",
    act.\"Actual Start Time\",
    act.\"Actual End Time\",
    act.\"Order ID\",
    ordd.\"Order Name\",
    act.\"Person ID\",
    per.\"Person Name\",
    per.\"Person Cost Center ID Name\",
    CASE
        WHEN per.\"Person Cost Center ID Name\" LIKE '%MP%' THEN 'Mirna Pec'
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
WHERE act.\"Order ID\" IN(" . $orderIdsStr . ")
    AND act.\"Report Data Version\" = 'Actual'
    " . $dateCondition . "
GROUP BY
    TO_VARCHAR(act.\"Date Created\", 'DD.MM.YYYY'),
    TO_VARCHAR(act.\"Date Posting\", 'YYYY-MM-DD'),
    act.\"Order ID\",
    act.\"Person ID\",
    per.\"Person Name\",
    per.\"Person Cost Center ID Name\",
    ordd.\"Order Name\",
    act.\"Actual Start Time\",
    act.\"Actual End Time\"
ORDER BY TO_VARCHAR(act.\"Date Posting\", 'YYYY-MM-DD') DESC
";

$data = executeQuery($conn, $sql);
odbc_close($conn);

if ($data === false || empty($data)) {
    die(json_encode(['success' => false, 'error' => 'Ni podatkov za ' . $obdobje, 'sql' => $sql]));
}

// Statistike
$totalEntries = count($data);
$totalHours = 0;
$employees = [];
$locations = ['Sora' => 0, 'Mirna Pec' => 0];
$workOrders = [];

foreach ($data as $row) {
    $hours = floatval(str_replace(',', '.', $row['M Hours'] ?? 0));
    $totalHours += $hours;

    $empName = $row['Person Name'] ?? 'Neznano';
    $empId = $row['Person ID'] ?? 'unknown';
    if (!isset($employees[$empId])) {
        $employees[$empId] = ['name' => $empName, 'hours' => 0, 'count' => 0, 'location' => $row['Location'] ?? 'Sora'];
    }
    $employees[$empId]['hours'] += $hours;
    $employees[$empId]['count']++;

    $loc = ($row['Location'] ?? 'Sora') === 'Mirna Pec' ? 'Mirna Pec' : 'Sora';
    $locations[$loc] += $hours;

    $dnName = $row['Order Name'] ?? 'Neznano';
    if (!isset($workOrders[$dnName])) {
        $workOrders[$dnName] = ['hours' => 0, 'count' => 0];
    }
    $workOrders[$dnName]['hours'] += $hours;
    $workOrders[$dnName]['count']++;
}

$uniqueEmployees = count($employees);
uasort($employees, function($a, $b) { return $b['hours'] <=> $a['hours']; });
uasort($workOrders, function($a, $b) { return $b['hours'] <=> $a['hours']; });
$top10 = array_slice($employees, 0, 10, true);

// HTML email
$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1);">

<div style="background:#1a1a1a;color:#fff;padding:30px;text-align:center;">
<h1 style="margin:0;font-size:24px;">Režijska dela</h1>
<p style="margin:10px 0 0;opacity:0.8;">Mesečno poročilo &middot; ' . htmlspecialchars($obdobje) . '</p>
</div>

<div style="padding:30px;">
<p>Spoštovani,</p>
<p>V nadaljevanju vam pošiljamo pregled beleženih ur režijskih del za obdobje <strong>' . htmlspecialchars($obdobje) . '</strong>.</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr>
<td style="padding:15px;background:#f8f8f8;border-radius:8px;text-align:center;width:25%;">
<div style="font-size:28px;font-weight:bold;color:#1a1a1a;">' . number_format($totalEntries, 0, ',', '.') . '</div>
<div style="font-size:12px;color:#666;margin-top:5px;">EVIDENC</div>
</td>
<td style="padding:15px;background:#f8f8f8;border-radius:8px;text-align:center;width:25%;">
<div style="font-size:28px;font-weight:bold;color:#1a1a1a;">' . number_format($totalHours, 1, ',', '.') . '</div>
<div style="font-size:12px;color:#666;margin-top:5px;">UR SKUPAJ</div>
</td>
<td style="padding:15px;background:#f8f8f8;border-radius:8px;text-align:center;width:25%;">
<div style="font-size:28px;font-weight:bold;color:#1a1a1a;">' . $uniqueEmployees . '</div>
<div style="font-size:12px;color:#666;margin-top:5px;">ZAPOSLENIH</div>
</td>
</tr>
</table>

<h3 style="border-bottom:2px solid #1a1a1a;padding-bottom:10px;">Po lokacijah</h3>
<table style="width:100%;margin:15px 0;">
<tr>
<td style="padding:15px;background:#1a1a1a;color:#fff;border-radius:8px;width:48%;">
<strong>ŠOŠTANJ</strong><br><span style="font-size:24px;">' . number_format($locations['Sora'], 1, ',', '.') . ' ur</span>
</td>
<td style="width:4%"></td>
<td style="padding:15px;background:#0ea5e9;color:#fff;border-radius:8px;width:48%;">
<strong>MIRNA PEČ</strong><br><span style="font-size:24px;">' . number_format($locations['Mirna Pec'], 1, ',', '.') . ' ur</span>
</td>
</tr>
</table>

<h3 style="border-bottom:2px solid #1a1a1a;padding-bottom:10px;">Top 10 zaposlenih</h3>
<table style="width:100%;border-collapse:collapse;">';

$rank = 1;
foreach ($top10 as $emp) {
    $bg = $rank % 2 === 0 ? '#f8f8f8' : '#fff';
    $html .= '<tr style="background:' . $bg . ';">
    <td style="padding:10px;border-bottom:1px solid #eee;">' . $rank . '.</td>
    <td style="padding:10px;border-bottom:1px solid #eee;"><strong>' . htmlspecialchars($emp['name']) . '</strong></td>
    <td style="padding:10px;border-bottom:1px solid #eee;">' . htmlspecialchars($emp['location'] === 'Mirna Pec' ? 'Mirna Peč' : 'Šoštanj') . '</td>
    <td style="padding:10px;border-bottom:1px solid #eee;text-align:right;"><strong>' . number_format($emp['hours'], 1, ',', '.') . ' ur</strong></td>
    </tr>';
    $rank++;
}

$html .= '</table>

<div style="margin-top:30px;text-align:center;">
<a href="https://vsr-bi.brinox.si/bx/apps/overhead-tracker/report.php" style="display:inline-block;padding:15px 30px;background:#1a1a1a;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Odpri podrobno poročilo</a>
</div>

</div>

<div style="background:#f8f8f8;padding:20px;text-align:center;font-size:12px;color:#666;">
Brinox d.o.o. &middot; Poročilo generirano: ' . date('d.m.Y H:i') . '
</div>

</div>
</body></html>';

// Pošlji email
try {
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    } else {
        $mail = new PHPMailer(true);
    }

    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'webmail.brinox.si';
    $mail->Port = 25;
    $mail->SMTPAuth = false;

    $mail->setFrom('noreply@brinox.eu', 'Brinox Porocila');
    $mail->addAddress('matic.kocijancic@brinox.eu');

    $mail->isHTML(true);
    $mail->Subject = 'Rezijska dela - ' . $obdobje;
    $mail->Body = $html;
    $mail->AltBody = "Rezijska dela - $obdobje\nEvidenc: $totalEntries\nUr: " . number_format($totalHours, 1);

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Porocilo za ' . $obdobje . ' poslano',
        'stats' => ['entries' => $totalEntries, 'hours' => round($totalHours, 1), 'employees' => $uniqueEmployees]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Email napaka: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
