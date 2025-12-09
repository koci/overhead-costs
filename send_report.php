<?php
/**
 * Režijska dela - Mesečno poročilo po emailu
 * Pošlje podatke za PREJŠNJI mesec
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Query
$orderIds = ['1167362','1167363','1167364','1167365','1167366','1167367','1167368','1167369','1167370','1167371','1167372','1167373','1167374'];
$orderIdsStr = "'" . implode("','", $orderIds) . "'";
$dateCondition = "AND act.\"Date Posting\" BETWEEN TO_DATE('" . $prevMonthStart . "', 'YYYY-MM-DD') AND TO_DATE('" . $prevMonthEnd . "', 'YYYY-MM-DD')";

$sql = "
SELECT
    TO_VARCHAR(act.\"Date Created\", 'DD.MM.YYYY') AS \"Date Created\",
    TO_VARCHAR(act.\"Date Posting\", 'YYYY-MM-DD') AS \"Date Posting\",
    act.\"Actual Start Time\",
    act.\"Order ID\",
    ordd.\"Order Name\",
    act.\"Person ID\",
    per.\"Person Name\",
    per.\"Person Cost Center ID Name\",
    CASE
        WHEN per.\"Person Cost Center ID Name\" LIKE '%MP%' THEN 'Mirna Pec'
        ELSE 'Sostanj'
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
    act.\"Actual Start Time\"
ORDER BY TO_VARCHAR(act.\"Date Posting\", 'YYYY-MM-DD') DESC
";

$data = executeQuery($conn, $sql);
odbc_close($conn);

if ($data === false || empty($data)) {
    die(json_encode(['success' => false, 'error' => 'Ni podatkov za ' . $obdobje]));
}

// Statistike
$totalEntries = count($data);
$totalHours = 0;
$employees = [];
$locations = ['Sostanj' => 0, 'Mirna Pec' => 0];
$workOrders = [];
$hourCounts = array_fill(0, 24, 0);

foreach ($data as $row) {
    $hours = floatval(str_replace(',', '.', $row['M Hours'] ?? 0));
    $totalHours += $hours;

    // Zaposleni
    $empName = $row['Person Name'] ?? 'Neznano';
    $empId = $row['Person ID'] ?? 'unknown';
    if (!isset($employees[$empId])) {
        $employees[$empId] = ['name' => $empName, 'hours' => 0, 'count' => 0, 'location' => $row['Location'] ?? 'Sostanj'];
    }
    $employees[$empId]['hours'] += $hours;
    $employees[$empId]['count']++;

    // Lokacije
    $loc = ($row['Location'] ?? 'Sostanj') === 'Mirna Pec' ? 'Mirna Pec' : 'Sostanj';
    $locations[$loc] += $hours;

    // Delovni nalogi
    $dnName = $row['Order Name'] ?? 'Neznano';
    if (!isset($workOrders[$dnName])) {
        $workOrders[$dnName] = ['hours' => 0, 'count' => 0];
    }
    $workOrders[$dnName]['hours'] += $hours;
    $workOrders[$dnName]['count']++;

    // Ure prijave
    $startTime = $row['Actual Start Time'] ?? '';
    if ($startTime && strpos($startTime, ':') !== false) {
        $hour = (int)explode(':', $startTime)[0];
        if ($hour >= 0 && $hour < 24) $hourCounts[$hour]++;
    }
}

// Izračuni
$uniqueEmployees = count($employees);
$avgPerEntry = $totalEntries > 0 ? $totalHours / $totalEntries : 0;
$totalLocHours = $locations['Sostanj'] + $locations['Mirna Pec'];
$sostanjPct = $totalLocHours > 0 ? round($locations['Sostanj'] / $totalLocHours * 100) : 0;
$mpPct = 100 - $sostanjPct;

// Sortiranje
uasort($employees, function($a, $b) { return $b['hours'] <=> $a['hours']; });
uasort($workOrders, function($a, $b) { return $b['hours'] <=> $a['hours']; });
$top10 = array_slice($employees, 0, 10, true);
$maxWoHours = max(array_column($workOrders, 'hours'));

// Peak hour
$peakHour = array_search(max($hourCounts), $hourCounts);
$peakCount = $hourCounts[$peakHour];

// Report URL
$reportUrl = 'https://vsr-bi.brinox.si/bx/apps/overhead-tracker/report.php';

// ============================================================================
// HTML EMAIL - OUTLOOK COMPATIBLE
// ============================================================================

$html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Rezijska dela - ' . $obdobje . '</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Arial, Helvetica, sans-serif;">

<!-- MAIN WRAPPER -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f4f4f4;">
<tr>
<td align="center" style="padding:20px 10px;">

<!-- CONTENT TABLE -->
<table border="0" cellpadding="0" cellspacing="0" width="650" style="background-color:#ffffff; border:1px solid #dddddd;">

<!-- HEADER -->
<tr>
<td style="background-color:#2c3e50; padding:30px 40px; text-align:center;">
<h1 style="margin:0; color:#ffffff; font-size:28px; font-weight:bold;">REŽIJSKA DELA</h1>
<p style="margin:10px 0 0 0; color:#bdc3c7; font-size:14px;">Mesečno poročilo | ' . strtoupper($obdobje) . '</p>
</td>
</tr>

<!-- INTRO -->
<tr>
<td style="padding:30px 40px 20px 40px;">
<p style="margin:0 0 15px 0; font-size:15px; line-height:1.6; color:#333333;">Pozdravljeni,</p>
<p style="margin:0 0 15px 0; font-size:15px; line-height:1.6; color:#333333;">
V nadaljevanju je pregled beleženih ur režijskih del za obdobje <strong>' . $obdobje . '</strong>.
Za več detajlnih informacij si lahko ogledate poročilo na spodnji povezavi.
</p>
<p style="margin:0; font-size:14px;">
<a href="' . $reportUrl . '" style="color:#3498db; text-decoration:underline;">&#128279; Odpri podrobno poročilo</a>
</p>
</td>
</tr>

<!-- SEPARATOR -->
<tr><td style="padding:0 40px;"><hr style="border:none; border-top:1px solid #eeeeee; margin:0;" /></td></tr>

<!-- KPI SECTION -->
<tr>
<td style="padding:25px 40px;">
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
<td width="25%" style="text-align:center; padding:15px; background-color:#f8f9fa; border:1px solid #e9ecef;">
<div style="font-size:32px; font-weight:bold; color:#2c3e50;">' . number_format($totalEntries, 0, ',', '.') . '</div>
<div style="font-size:11px; color:#7f8c8d; text-transform:uppercase; margin-top:5px;">Evidenc</div>
</td>
<td width="25%" style="text-align:center; padding:15px; background-color:#f8f9fa; border:1px solid #e9ecef;">
<div style="font-size:32px; font-weight:bold; color:#2c3e50;">' . number_format($totalHours, 1, ',', '.') . '</div>
<div style="font-size:11px; color:#7f8c8d; text-transform:uppercase; margin-top:5px;">Ur skupaj</div>
</td>
<td width="25%" style="text-align:center; padding:15px; background-color:#f8f9fa; border:1px solid #e9ecef;">
<div style="font-size:32px; font-weight:bold; color:#2c3e50;">' . $uniqueEmployees . '</div>
<div style="font-size:11px; color:#7f8c8d; text-transform:uppercase; margin-top:5px;">Zaposlenih</div>
</td>
<td width="25%" style="text-align:center; padding:15px; background-color:#f8f9fa; border:1px solid #e9ecef;">
<div style="font-size:32px; font-weight:bold; color:#2c3e50;">' . number_format($avgPerEntry, 2, ',', '.') . '</div>
<div style="font-size:11px; color:#7f8c8d; text-transform:uppercase; margin-top:5px;">Ur/evidenco</div>
</td>
</tr>
</table>
</td>
</tr>

<!-- LOKACIJE -->
<tr>
<td style="padding:10px 40px 25px 40px;">
<h3 style="margin:0 0 15px 0; font-size:16px; color:#2c3e50; border-bottom:2px solid #2c3e50; padding-bottom:8px;">&#128205; RAZDELITEV PO LOKACIJAH</h3>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
<td width="48%" style="background-color:#2c3e50; padding:20px; vertical-align:top;">
<div style="color:#ffffff; font-size:13px; font-weight:bold; margin-bottom:5px;">ŠOŠTANJ</div>
<div style="color:#ffffff; font-size:28px; font-weight:bold;">' . number_format($locations['Sostanj'], 1, ',', '.') . ' <span style="font-size:14px;">ur</span></div>
<div style="color:#bdc3c7; font-size:12px; margin-top:5px;">' . $sostanjPct . '% vseh ur</div>
<!-- PROGRESS BAR -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top:10px;">
<tr>
<td style="background-color:#1a252f; height:8px; width:100%;">
<table border="0" cellpadding="0" cellspacing="0" height="8" style="width:' . $sostanjPct . '%;"><tr><td style="background-color:#3498db;"></td></tr></table>
</td>
</tr>
</table>
</td>
<td width="4%"></td>
<td width="48%" style="background-color:#16a085; padding:20px; vertical-align:top;">
<div style="color:#ffffff; font-size:13px; font-weight:bold; margin-bottom:5px;">MIRNA PEČ</div>
<div style="color:#ffffff; font-size:28px; font-weight:bold;">' . number_format($locations['Mirna Pec'], 1, ',', '.') . ' <span style="font-size:14px;">ur</span></div>
<div style="color:#d5f5e3; font-size:12px; margin-top:5px;">' . $mpPct . '% vseh ur</div>
<!-- PROGRESS BAR -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top:10px;">
<tr>
<td style="background-color:#0e6655; height:8px; width:100%;">
<table border="0" cellpadding="0" cellspacing="0" height="8" style="width:' . $mpPct . '%;"><tr><td style="background-color:#58d68d;"></td></tr></table>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>

<!-- PEAK INFO -->
<tr>
<td style="padding:0 40px 25px 40px;">
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#fff3cd; border-left:4px solid #ffc107;">
<tr>
<td style="padding:15px 20px;">
<strong style="color:#856404;">&#9200; Vrhunec prijav:</strong>
<span style="color:#856404;">Največ prijav ob <strong>' . str_pad($peakHour, 2, '0', STR_PAD_LEFT) . ':00</strong> uri (' . $peakCount . ' evidenc)</span>
</td>
</tr>
</table>
</td>
</tr>

<!-- DELOVNI NALOGI -->
<tr>
<td style="padding:10px 40px 25px 40px;">
<h3 style="margin:0 0 15px 0; font-size:16px; color:#2c3e50; border-bottom:2px solid #2c3e50; padding-bottom:8px;">&#128196; URE PO DELOVNIH NALOGIH</h3>
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e9ecef;">';

$woRank = 1;
foreach ($workOrders as $woName => $wo) {
    $bgColor = $woRank % 2 === 0 ? '#f8f9fa' : '#ffffff';
    $barWidth = $maxWoHours > 0 ? round($wo['hours'] / $maxWoHours * 100) : 0;
    $html .= '
<tr>
<td style="padding:12px 15px; background-color:' . $bgColor . '; border-bottom:1px solid #e9ecef; vertical-align:middle;">
<div style="font-size:14px; font-weight:bold; color:#2c3e50; margin-bottom:8px;">' . htmlspecialchars($woName) . '</div>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
<td style="background-color:#ecf0f1; height:12px; width:100%;">
<table border="0" cellpadding="0" cellspacing="0" height="12" style="width:' . $barWidth . '%;"><tr><td style="background-color:#3498db;"></td></tr></table>
</td>
</tr>
</table>
</td>
<td style="padding:12px 15px; background-color:' . $bgColor . '; border-bottom:1px solid #e9ecef; text-align:right; width:120px; vertical-align:middle;">
<div style="font-size:18px; font-weight:bold; color:#2c3e50;">' . number_format($wo['hours'], 1, ',', '.') . '</div>
<div style="font-size:11px; color:#7f8c8d;">ur (' . $wo['count'] . ' ev.)</div>
</td>
</tr>';
    $woRank++;
}

$html .= '
</table>
</td>
</tr>

<!-- TOP 10 ZAPOSLENIH -->
<tr>
<td style="padding:10px 40px 25px 40px;">
<h3 style="margin:0 0 15px 0; font-size:16px; color:#2c3e50; border-bottom:2px solid #2c3e50; padding-bottom:8px;">&#128101; TOP 10 ZAPOSLENIH</h3>
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e9ecef;">
<tr style="background-color:#2c3e50;">
<th style="padding:12px 15px; text-align:left; color:#ffffff; font-size:12px;">#</th>
<th style="padding:12px 15px; text-align:left; color:#ffffff; font-size:12px;">ZAPOSLENI</th>
<th style="padding:12px 15px; text-align:left; color:#ffffff; font-size:12px;">LOKACIJA</th>
<th style="padding:12px 15px; text-align:center; color:#ffffff; font-size:12px;">EVIDENC</th>
<th style="padding:12px 15px; text-align:right; color:#ffffff; font-size:12px;">URE</th>
</tr>';

$rank = 1;
$maxEmpHours = !empty($top10) ? max(array_column($top10, 'hours')) : 1;
foreach ($top10 as $emp) {
    $bgColor = $rank % 2 === 0 ? '#f8f9fa' : '#ffffff';
    $lokacija = $emp['location'] === 'Mirna Pec' ? 'Mirna Peč' : 'Šoštanj';
    $lokBg = $emp['location'] === 'Mirna Pec' ? '#d5f5e3' : '#d6eaf8';
    $lokColor = $emp['location'] === 'Mirna Pec' ? '#0e6655' : '#1a5276';
    $barWidth = round($emp['hours'] / $maxEmpHours * 100);

    $html .= '
<tr style="background-color:' . $bgColor . ';">
<td style="padding:10px 15px; border-bottom:1px solid #e9ecef; font-weight:bold; color:#7f8c8d; width:40px;">' . $rank . '.</td>
<td style="padding:10px 15px; border-bottom:1px solid #e9ecef;">
<div style="font-weight:bold; color:#2c3e50;">' . htmlspecialchars($emp['name']) . '</div>
<table border="0" cellpadding="0" cellspacing="0" width="80%" style="margin-top:5px;">
<tr><td style="background-color:#ecf0f1; height:6px;"><table border="0" cellpadding="0" cellspacing="0" height="6" style="width:' . $barWidth . '%;"><tr><td style="background-color:#3498db;"></td></tr></table></td></tr>
</table>
</td>
<td style="padding:10px 15px; border-bottom:1px solid #e9ecef;">
<span style="display:inline-block; padding:4px 10px; background-color:' . $lokBg . '; color:' . $lokColor . '; font-size:11px; font-weight:bold; border-radius:3px;">' . $lokacija . '</span>
</td>
<td style="padding:10px 15px; border-bottom:1px solid #e9ecef; text-align:center; color:#7f8c8d;">' . $emp['count'] . '</td>
<td style="padding:10px 15px; border-bottom:1px solid #e9ecef; text-align:right; font-weight:bold; font-size:16px; color:#2c3e50;">' . number_format($emp['hours'], 1, ',', '.') . '</td>
</tr>';
    $rank++;
}

$html .= '
</table>
</td>
</tr>

<!-- CTA BUTTON -->
<tr>
<td style="padding:20px 40px 30px 40px; text-align:center;">
<table border="0" cellpadding="0" cellspacing="0" align="center">
<tr>
<td style="background-color:#3498db; padding:15px 35px; border-radius:5px;">
<a href="' . $reportUrl . '" style="color:#ffffff; text-decoration:none; font-weight:bold; font-size:14px;">ODPRI PODROBNO POROČILO &#8594;</a>
</td>
</tr>
</table>
</td>
</tr>

<!-- FOOTER -->
<tr>
<td style="background-color:#2c3e50; padding:25px 40px; text-align:center;">
<p style="margin:0 0 5px 0; color:#bdc3c7; font-size:12px;">Brinox d.o.o. | Režijska dela - Avtomatsko poročilo</p>
<p style="margin:0; color:#7f8c8d; font-size:11px;">Generirano: ' . date('d.m.Y') . ' ob ' . date('H:i') . '</p>
</td>
</tr>

</table>
<!-- END CONTENT TABLE -->

</td>
</tr>
</table>
<!-- END MAIN WRAPPER -->

</body>
</html>';

// ============================================================================
// POŠLJI EMAIL
// ============================================================================

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
    $mail->Subject = '=?UTF-8?B?' . base64_encode('Režijska dela - Poročilo ' . $obdobje) . '?=';
    $mail->Body = $html;
    $mail->AltBody = "Rezijska dela - $obdobje\nEvidenc: $totalEntries\nUr: " . number_format($totalHours, 1) . "\n\nVec info: $reportUrl";

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Porocilo za ' . $obdobje . ' uspesno poslano',
        'stats' => ['entries' => $totalEntries, 'hours' => round($totalHours, 1), 'employees' => $uniqueEmployees]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Email napaka: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
