<?php
/**
 * Režijska dela - Avtomatsko mesečno poročilo
 * Lokacija: C:\BriPHP\bxroot\apps\overhead-tracker\send_report.php
 *
 * Uporaba: Obiščite URL 1. v mesecu za pošiljanje poročila prejšnjega meseca
 * https://vsr-bi.brinox.si/bx/apps/overhead-tracker/send_report.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// Debug mode - prikaže napake
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
});

// PHPMailer
$phpmailerPath = __DIR__ . '/../../lib/PHPMailer/src/';
if (!is_dir($phpmailerPath)) {
    $phpmailerPath = __DIR__ . '/../../lib/PHPMailer/';
}

$requiredFiles = [
    $phpmailerPath . 'Exception.php',
    $phpmailerPath . 'PHPMailer.php',
    $phpmailerPath . 'SMTP.php'
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        die(json_encode(['success' => false, 'error' => 'PHPMailer manjka: ' . basename($file)]));
    }
}

require_once $phpmailerPath . 'Exception.php';
require_once $phpmailerPath . 'PHPMailer.php';
require_once $phpmailerPath . 'SMTP.php';

// Database config
$dbConfigPath = __DIR__ . '/../../db_config.php';
if (!file_exists($dbConfigPath)) {
    die(json_encode(['success' => false, 'error' => 'db_config.php manjka', 'path' => $dbConfigPath]));
}
require_once $dbConfigPath;

// PHPMailer - podpora za obe verziji (z namespace in brez)
if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    // Nova verzija (6.x) z namespace
    class_alias('PHPMailer\\PHPMailer\\PHPMailer', 'PHPMailerClass');
    class_alias('PHPMailer\\PHPMailer\\Exception', 'PHPMailerException');
} else {
    // Stara verzija (5.x) brez namespace
    class_alias('PHPMailer', 'PHPMailerClass');
}

// ============================================================================
// PRIDOBI PODATKE IZ BAZE ZA PREJŠNJI MESEC
// ============================================================================

$prevMonthStart = date('Y-m-01', strtotime('first day of previous month'));
$prevMonthEnd = date('Y-m-t', strtotime('last day of previous month'));
$prevMonthNameSI = [
    1 => 'Januar', 2 => 'Februar', 3 => 'Marec', 4 => 'April',
    5 => 'Maj', 6 => 'Junij', 7 => 'Julij', 8 => 'Avgust',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December'
];
$monthNum = (int)date('n', strtotime('first day of previous month'));
$monthSI = $prevMonthNameSI[$monthNum] ?? 'Mesec';
$yearNum = date('Y', strtotime('first day of previous month'));
$periodLabel = "$monthSI $yearNum";

try {
    $conn = connectHana();

    $sql = "
    SELECT
        TO_VARCHAR(act.\"Date Created\", 'DD.MM.YYYY') AS \"Date Created\",
        TO_VARCHAR(act.\"Date Posting\", 'YYYY-MM-DD') AS \"Date Posting\",
        act.\"Actual Start Time\",
        act.\"Actual End Time\",
        act.\"Order ID\",
        ordd.\"Order Name\",
        act.\"Person ID\",
        act.\"Operation Text\",
        act.\"Work Center ID Name\",
        per.\"Person Name\",
        per.\"Person Cost Center ID Name\",
        per.\"Person Group Name\",
        CASE
            WHEN per.\"Person Cost Center ID Name\" LIKE '%MP%' THEN 'Mirna Pec'
            ELSE 'Sora'
        END AS \"Location\",
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
        act.\"Operation Text\",
        act.\"Work Center ID Name\",
        per.\"Person Name\",
        per.\"Person Cost Center ID Name\",
        per.\"Person Group Name\"
    ORDER BY act.\"Date Posting\" DESC
    ";

    $data = executeQuery($conn, $sql);

} catch (Exception $e) {
    die(json_encode(['success' => false, 'error' => 'Napaka baze: ' . $e->getMessage()]));
}

if (empty($data)) {
    die(json_encode(['success' => false, 'error' => 'Ni podatkov za ' . $periodLabel]));
}

// ============================================================================
// IZRAČUNAJ STATISTIKE
// ============================================================================

$totalEntries = count($data);
$totalHours = 0;
$employees = [];
$locations = ['Sora' => 0, 'Mirna Pec' => 0];
$workOrders = [];
$hourCounts = array_fill(0, 24, 0);
$dayNames = ['Ned', 'Pon', 'Tor', 'Sre', 'Čet', 'Pet', 'Sob'];
$dayCounts = array_fill(0, 7, 0);

foreach ($data as $row) {
    $hours = floatval($row['M Hours'] ?? 0);
    $totalHours += $hours;

    // Po zaposlenih
    $empId = $row['Person ID'] ?? 'unknown';
    $empName = $row['Person Name'] ?? 'Neznano';
    if (!isset($employees[$empId])) {
        $employees[$empId] = ['name' => $empName, 'hours' => 0, 'count' => 0, 'location' => $row['Location'] ?? 'Sora'];
    }
    $employees[$empId]['hours'] += $hours;
    $employees[$empId]['count']++;

    // Po lokacijah
    $loc = $row['Location'] ?? 'Sora';
    $locKey = strpos($loc, 'Mirna') !== false ? 'Mirna Pec' : 'Sora';
    $locations[$locKey] += $hours;

    // Po delovnih nalogih
    $dnName = $row['Order Name'] ?? $row['Order ID'] ?? 'Neznano';
    if (!isset($workOrders[$dnName])) {
        $workOrders[$dnName] = ['hours' => 0, 'count' => 0];
    }
    $workOrders[$dnName]['hours'] += $hours;
    $workOrders[$dnName]['count']++;

    // Vrhunec ur
    $startTime = $row['Actual Start Time'] ?? '';
    if ($startTime) {
        $parts = explode(':', $startTime);
        $hour = intval($parts[0] ?? 0);
        if ($hour >= 0 && $hour < 24) {
            $hourCounts[$hour]++;
        }
    }

    // Po dnevih
    $datePosting = $row['Date Posting'] ?? '';
    if ($datePosting) {
        $dayOfWeek = date('w', strtotime($datePosting));
        $dayCounts[$dayOfWeek]++;
    }
}

// Izračuni
$uniqueEmployees = count($employees);
$avgHours = $totalEntries > 0 ? $totalHours / $totalEntries : 0;
$avgPerEmployee = $uniqueEmployees > 0 ? $totalHours / $uniqueEmployees : 0;

// Top 10 zaposlenih
uasort($employees, fn($a, $b) => $b['hours'] <=> $a['hours']);
$top10Employees = array_slice($employees, 0, 10, true);

// Sortiraj delovne naloge
uasort($workOrders, fn($a, $b) => $b['hours'] <=> $a['hours']);

// Vrhunec ura
$peakHour = array_search(max($hourCounts), $hourCounts);
$peakCount = $hourCounts[$peakHour];

// Vrhunec dan
$peakDay = array_search(max($dayCounts), $dayCounts);
$peakDayName = $dayNames[$peakDay];
$peakDayCount = $dayCounts[$peakDay];

// Lokacije - odstotki
$totalLocHours = $locations['Sora'] + $locations['Mirna Pec'];
$soraPct = $totalLocHours > 0 ? round($locations['Sora'] / $totalLocHours * 100) : 0;
$mpPct = 100 - $soraPct;

// ============================================================================
// SESTAVI HTML E-SPOROČILO (Outlook compatible)
// ============================================================================

$reportUrl = 'https://vsr-bi.brinox.si/bx/apps/overhead-tracker/report.php';
$generatedDate = date('d.m.Y H:i');

$html = '<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Režijska dela - ' . $periodLabel . '</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Segoe UI,Arial,sans-serif;">

    <!-- Wrapper -->
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f4f5;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <!-- Main Container -->
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.05);">

                    <!-- Header -->
                    <tr>
                        <td style="background-color:#18181b;padding:32px 40px;text-align:center;">
                            <h1 style="margin:0 0 8px 0;font-size:28px;font-weight:700;color:#ffffff;letter-spacing:-0.5px;">Režijska dela</h1>
                            <p style="margin:0;font-size:15px;color:#a1a1aa;">Mesečno poročilo · ' . $periodLabel . '</p>
                        </td>
                    </tr>

                    <!-- Intro -->
                    <tr>
                        <td style="padding:32px 40px 24px 40px;">
                            <p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#3f3f46;">
                                Spoštovani,
                            </p>
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#3f3f46;">
                                V nadaljevanju vam pošiljamo <strong>pregled beleženih ur režijskih del</strong> za obdobje <strong>' . $periodLabel . '</strong>.
                                Poročilo vključuje podatke o prijavah po lokacijah, delovnih nalogih in zaposlenih.
                            </p>
                        </td>
                    </tr>

                    <!-- KPI Cards -->
                    <tr>
                        <td style="padding:0 40px 32px 40px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="25%" style="padding:0 8px 0 0;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#fafafa;border-radius:8px;border-left:4px solid #18181b;">
                                            <tr>
                                                <td style="padding:20px;text-align:center;">
                                                    <div style="font-size:32px;font-weight:700;color:#18181b;line-height:1;">' . number_format($totalEntries, 0, ',', '.') . '</div>
                                                    <div style="font-size:11px;font-weight:600;color:#71717a;text-transform:uppercase;letter-spacing:0.5px;margin-top:8px;">Evidenc</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td width="25%" style="padding:0 8px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#fafafa;border-radius:8px;border-left:4px solid #0ea5e9;">
                                            <tr>
                                                <td style="padding:20px;text-align:center;">
                                                    <div style="font-size:32px;font-weight:700;color:#18181b;line-height:1;">' . number_format($totalHours, 1, ',', '.') . '</div>
                                                    <div style="font-size:11px;font-weight:600;color:#71717a;text-transform:uppercase;letter-spacing:0.5px;margin-top:8px;">Skupaj ur</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td width="25%" style="padding:0 8px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#fafafa;border-radius:8px;border-left:4px solid #f59e0b;">
                                            <tr>
                                                <td style="padding:20px;text-align:center;">
                                                    <div style="font-size:32px;font-weight:700;color:#18181b;line-height:1;">' . number_format($avgHours, 2, ',', '.') . '</div>
                                                    <div style="font-size:11px;font-weight:600;color:#71717a;text-transform:uppercase;letter-spacing:0.5px;margin-top:8px;">Povp./evidenco</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td width="25%" style="padding:0 0 0 8px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#fafafa;border-radius:8px;border-left:4px solid #8b5cf6;">
                                            <tr>
                                                <td style="padding:20px;text-align:center;">
                                                    <div style="font-size:32px;font-weight:700;color:#18181b;line-height:1;">' . $uniqueEmployees . '</div>
                                                    <div style="font-size:11px;font-weight:600;color:#71717a;text-transform:uppercase;letter-spacing:0.5px;margin-top:8px;">Zaposlenih</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding:0 40px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr><td style="border-top:1px solid #e4e4e7;"></td></tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Po lokacijah -->
                    <tr>
                        <td style="padding:32px 40px;">
                            <h2 style="margin:0 0 20px 0;font-size:16px;font-weight:700;color:#18181b;text-transform:uppercase;letter-spacing:0.5px;">Po lokacijah</h2>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="50%" style="padding-right:12px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#18181b;border-radius:8px;">
                                            <tr>
                                                <td style="padding:20px;">
                                                    <div style="font-size:13px;font-weight:600;color:#a1a1aa;margin-bottom:8px;">ŠOŠTANJ</div>
                                                    <div style="font-size:28px;font-weight:700;color:#ffffff;">' . number_format($locations['Sora'], 1, ',', '.') . ' <span style="font-size:16px;font-weight:400;">ur</span></div>
                                                    <div style="font-size:13px;color:#a1a1aa;margin-top:4px;">' . $soraPct . '% vseh ur</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td width="50%" style="padding-left:12px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#0ea5e9;border-radius:8px;">
                                            <tr>
                                                <td style="padding:20px;">
                                                    <div style="font-size:13px;font-weight:600;color:rgba(255,255,255,0.8);margin-bottom:8px;">MIRNA PEČ</div>
                                                    <div style="font-size:28px;font-weight:700;color:#ffffff;">' . number_format($locations['Mirna Pec'], 1, ',', '.') . ' <span style="font-size:16px;font-weight:400;">ur</span></div>
                                                    <div style="font-size:13px;color:rgba(255,255,255,0.8);margin-top:4px;">' . $mpPct . '% vseh ur</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Vrhunci -->
                    <tr>
                        <td style="padding:0 40px 32px 40px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#fafafa;border-radius:8px;">
                                <tr>
                                    <td width="50%" style="padding:20px;border-right:1px solid #e4e4e7;">
                                        <div style="font-size:11px;font-weight:600;color:#71717a;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Vrhunec prijav (ura)</div>
                                        <div style="font-size:24px;font-weight:700;color:#18181b;">' . str_pad($peakHour, 2, '0', STR_PAD_LEFT) . ':00</div>
                                        <div style="font-size:13px;color:#71717a;">' . $peakCount . ' evidenc ob tej uri</div>
                                    </td>
                                    <td width="50%" style="padding:20px;">
                                        <div style="font-size:11px;font-weight:600;color:#71717a;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Najbolj aktiven dan</div>
                                        <div style="font-size:24px;font-weight:700;color:#18181b;">' . $peakDayName . '</div>
                                        <div style="font-size:13px;color:#71717a;">' . $peakDayCount . ' evidenc</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding:0 40px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr><td style="border-top:1px solid #e4e4e7;"></td></tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Po delovnih nalogih -->
                    <tr>
                        <td style="padding:32px 40px;">
                            <h2 style="margin:0 0 20px 0;font-size:16px;font-weight:700;color:#18181b;text-transform:uppercase;letter-spacing:0.5px;">Po delovnih nalogih</h2>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">';

$i = 0;
foreach ($workOrders as $dnName => $wo) {
    if ($i >= 5) break;
    $woHours = number_format($wo['hours'], 1, ',', '.');
    $woCount = $wo['count'];
    $bgColor = $i % 2 === 0 ? '#ffffff' : '#fafafa';
    $html .= '
                                <tr>
                                    <td style="padding:14px 16px;background-color:' . $bgColor . ';border-bottom:1px solid #e4e4e7;">
                                        <div style="font-size:14px;font-weight:600;color:#18181b;">' . htmlspecialchars($dnName) . '</div>
                                        <div style="font-size:12px;color:#71717a;margin-top:2px;">' . $woCount . ' evidenc</div>
                                    </td>
                                    <td style="padding:14px 16px;background-color:' . $bgColor . ';border-bottom:1px solid #e4e4e7;text-align:right;">
                                        <div style="font-size:16px;font-weight:700;color:#18181b;">' . $woHours . ' <span style="font-size:12px;font-weight:400;color:#71717a;">ur</span></div>
                                    </td>
                                </tr>';
    $i++;
}

$html .= '
                            </table>
                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding:0 40px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr><td style="border-top:1px solid #e4e4e7;"></td></tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Top 10 zaposlenih -->
                    <tr>
                        <td style="padding:32px 40px;">
                            <h2 style="margin:0 0 20px 0;font-size:16px;font-weight:700;color:#18181b;text-transform:uppercase;letter-spacing:0.5px;">Top 10 zaposlenih</h2>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
                                <tr style="background-color:#18181b;">
                                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;width:40px;">#</th>
                                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;">Zaposleni</th>
                                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;">Lokacija</th>
                                    <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:600;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;">Evidenc</th>
                                    <th style="padding:12px 16px;text-align:right;font-size:11px;font-weight:600;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;">Ure</th>
                                </tr>';

$rank = 1;
foreach ($top10Employees as $empId => $emp) {
    $bgColor = $rank % 2 === 0 ? '#ffffff' : '#fafafa';
    $rankBg = '#e4e4e7';
    $rankColor = '#71717a';
    if ($rank === 1) { $rankBg = '#fef3c7'; $rankColor = '#b45309'; }
    elseif ($rank === 2) { $rankBg = '#e4e4e7'; $rankColor = '#52525b'; }
    elseif ($rank === 3) { $rankBg = '#fed7aa'; $rankColor = '#c2410c'; }

    $locDisplay = strpos($emp['location'], 'Mirna') !== false ? 'Mirna Peč' : 'Šoštanj';
    $locColor = strpos($emp['location'], 'Mirna') !== false ? '#0ea5e9' : '#18181b';

    $html .= '
                                <tr>
                                    <td style="padding:14px 16px;background-color:' . $bgColor . ';border-bottom:1px solid #e4e4e7;">
                                        <div style="width:28px;height:28px;background-color:' . $rankBg . ';border-radius:50%;text-align:center;line-height:28px;font-size:12px;font-weight:700;color:' . $rankColor . ';">' . $rank . '</div>
                                    </td>
                                    <td style="padding:14px 16px;background-color:' . $bgColor . ';border-bottom:1px solid #e4e4e7;">
                                        <div style="font-size:14px;font-weight:600;color:#18181b;">' . htmlspecialchars($emp['name']) . '</div>
                                    </td>
                                    <td style="padding:14px 16px;background-color:' . $bgColor . ';border-bottom:1px solid #e4e4e7;">
                                        <div style="display:inline-block;padding:4px 10px;background-color:' . ($locDisplay === 'Mirna Peč' ? '#e0f2fe' : '#f4f4f5') . ';border-radius:4px;font-size:12px;font-weight:600;color:' . $locColor . ';">' . $locDisplay . '</div>
                                    </td>
                                    <td style="padding:14px 16px;background-color:' . $bgColor . ';border-bottom:1px solid #e4e4e7;text-align:center;">
                                        <div style="font-size:14px;color:#71717a;">' . $emp['count'] . '</div>
                                    </td>
                                    <td style="padding:14px 16px;background-color:' . $bgColor . ';border-bottom:1px solid #e4e4e7;text-align:right;">
                                        <div style="font-size:16px;font-weight:700;color:#18181b;">' . number_format($emp['hours'], 1, ',', '.') . '</div>
                                    </td>
                                </tr>';
    $rank++;
}

$html .= '
                            </table>
                        </td>
                    </tr>

                    <!-- CTA Button -->
                    <tr>
                        <td style="padding:16px 40px 40px 40px;text-align:center;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;">
                                <tr>
                                    <td style="background-color:#18181b;border-radius:8px;">
                                        <a href="' . $reportUrl . '" target="_blank" style="display:inline-block;padding:16px 32px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">
                                            Odpri podrobno poročilo →
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:16px 0 0 0;font-size:13px;color:#71717a;">
                                Za detajlni pregled po zaposlenih, dnevih in urah obiščite zgornji link.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#fafafa;padding:24px 40px;border-top:1px solid #e4e4e7;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td>
                                        <p style="margin:0;font-size:12px;color:#71717a;">
                                            <strong>Režijska dela</strong> · Beleženje ur dela v proizvodnji<br>
                                            Brinox d.o.o. · Poročilo generirano: ' . $generatedDate . '
                                        </p>
                                    </td>
                                    <td style="text-align:right;">
                                        <p style="margin:0;font-size:11px;color:#a1a1aa;">
                                            To sporočilo je bilo samodejno generirano.<br>
                                            Kontakt: matic.kocijancic@brinox.eu
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>';

// ============================================================================
// POŠLJI E-SPOROČILO
// ============================================================================

try {
    $mail = new PHPMailerClass(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();

    $mail->Host = isset($mailConfig['host']) ? $mailConfig['host'] : 'webmail.brinox.si';
    $mail->Port = isset($mailConfig['port']) ? $mailConfig['port'] : 25;
    $mail->SMTPAuth = isset($mailConfig['auth']) ? $mailConfig['auth'] : false;

    if ($mail->SMTPAuth && isset($mailConfig['username'])) {
        $mail->Username = $mailConfig['username'];
        $mail->Password = $mailConfig['password'];
    }

    $mail->setFrom(
        isset($mailConfig['from']['email']) ? $mailConfig['from']['email'] : 'noreply@brinox.eu',
        isset($mailConfig['from']['name']) ? $mailConfig['from']['name'] : 'Brinox Porocila'
    );

    $mail->addAddress('matic.kocijancic@brinox.eu', 'Matic Kocijancic');

    $mail->isHTML(true);
    $mail->Subject = 'Rezijska dela - Porocilo za ' . $periodLabel;
    $mail->Body = $html;
    $mail->AltBody = "Rezijska dela - Porocilo za $periodLabel\n\n" .
        "Evidenc: $totalEntries\n" .
        "Skupaj ur: " . number_format($totalHours, 1) . "\n" .
        "Zaposlenih: $uniqueEmployees\n\n" .
        "Podrobnosti: $reportUrl";

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Porocilo za ' . $periodLabel . ' uspesno poslano',
        'stats' => [
            'entries' => $totalEntries,
            'hours' => round($totalHours, 1),
            'employees' => $uniqueEmployees
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Napaka pri posiljanju: ' . $mail->ErrorInfo
    ]);
}
