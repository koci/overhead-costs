<?php
/**
 * Režijska dela - Pošiljanje e-poročila
 * Lokacija: C:\BriPHP\bxroot\apps\overhead-tracker\send_report.php
 */

// Error reporting za debug
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// PHPMailer pot - prilagodi če je drugače
$phpmailerPath = __DIR__ . '/../../lib/PHPMailer/src/';

// Preveri če obstaja src mapa, sicer probaj brez
if (!is_dir($phpmailerPath)) {
    $phpmailerPath = __DIR__ . '/../../lib/PHPMailer/';
}

// Preveri če datoteke obstajajo
$requiredFiles = [
    $phpmailerPath . 'Exception.php',
    $phpmailerPath . 'PHPMailer.php',
    $phpmailerPath . 'SMTP.php'
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        echo json_encode([
            'success' => false,
            'error' => 'PHPMailer datoteka ne obstaja: ' . basename($file),
            'path' => $file
        ]);
        exit;
    }
}

require_once $phpmailerPath . 'Exception.php';
require_once $phpmailerPath . 'PHPMailer.php';
require_once $phpmailerPath . 'SMTP.php';

// db_config.php
$dbConfigPath = __DIR__ . '/../../db_config.php';
if (file_exists($dbConfigPath)) {
    require_once $dbConfigPath;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Preberi POST podatke
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Neveljavni podatki']);
    exit;
}

// Pripravi podatke
$period = $data['period'] ?? 'pretekli mesec';
$totalEntries = $data['totalEntries'] ?? 0;
$totalHours = $data['totalHours'] ?? '0';
$avgHours = $data['avgHours'] ?? '0';
$totalEmployees = $data['totalEmployees'] ?? 0;
$byLocation = $data['byLocation'] ?? [];
$topEmployees = $data['topEmployees'] ?? [];

// Datum poročila
$reportDate = date('d.m.Y');

// Sestavi HTML e-sporočilo
$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b; margin: 0; padding: 0; background: #f1f5f9; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: #0f172a; color: white; padding: 24px; text-align: center; }
        .header h1 { margin: 0 0 4px 0; font-size: 22px; }
        .header p { margin: 0; opacity: 0.8; font-size: 13px; }
        .content { padding: 24px; }
        .kpi-table { width: 100%; margin-bottom: 24px; }
        .kpi-table td { padding: 16px; text-align: center; background: #f8fafc; border: 1px solid #e2e8f0; }
        .kpi-value { font-size: 24px; font-weight: 700; color: #0f172a; }
        .kpi-label { font-size: 11px; color: #64748b; text-transform: uppercase; }
        .section-title { font-size: 14px; font-weight: 700; color: #0f172a; margin: 24px 0 12px 0; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th { background: #0f172a; color: white; padding: 10px 12px; text-align: left; }
        .data-table td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        .data-table tr:nth-child(even) { background: #f8fafc; }
        .cta { text-align: center; margin: 24px 0; }
        .cta a { display: inline-block; background: #0f172a; color: white; padding: 12px 24px; text-decoration: none; font-weight: 600; }
        .footer { background: #f8fafc; padding: 16px 24px; text-align: center; font-size: 11px; color: #64748b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Režijska dela</h1>
            <p>Mesečno poročilo · ' . $reportDate . '</p>
        </div>
        <div class="content">
            <table class="kpi-table">
                <tr>
                    <td>
                        <div class="kpi-value">' . number_format($totalEntries, 0, ',', '.') . '</div>
                        <div class="kpi-label">Evidenc</div>
                    </td>
                    <td>
                        <div class="kpi-value">' . number_format((float)$totalHours, 1, ',', '.') . '</div>
                        <div class="kpi-label">Skupaj ur</div>
                    </td>
                    <td>
                        <div class="kpi-value">' . number_format((float)$avgHours, 2, ',', '.') . 'h</div>
                        <div class="kpi-label">Povprečje</div>
                    </td>
                    <td>
                        <div class="kpi-value">' . $totalEmployees . '</div>
                        <div class="kpi-label">Zaposlenih</div>
                    </td>
                </tr>
            </table>';

// Lokacije
if (!empty($byLocation)) {
    $html .= '<div class="section-title">Po lokacijah</div>
            <table class="data-table">
                <tr><th>Lokacija</th><th style="text-align:right">Ure</th></tr>';
    foreach ($byLocation as $loc => $hours) {
        $html .= '<tr><td>' . htmlspecialchars($loc) . '</td><td style="text-align:right;font-weight:600">' . number_format($hours, 1, ',', '.') . 'h</td></tr>';
    }
    $html .= '</table>';
}

// Top 10 zaposlenih
if (!empty($topEmployees)) {
    $html .= '<div class="section-title">Top 10 zaposlenih</div>
            <table class="data-table">
                <tr><th>#</th><th>Zaposleni</th><th style="text-align:right">Ure</th></tr>';
    $i = 1;
    foreach ($topEmployees as $emp) {
        $html .= '<tr><td>' . $i . '</td><td>' . htmlspecialchars($emp['name'] ?? 'Neznano') . '</td><td style="text-align:right;font-weight:600">' . number_format($emp['hours'] ?? 0, 1, ',', '.') . 'h</td></tr>';
        $i++;
    }
    $html .= '</table>';
}

$html .= '
            <div class="cta">
                <a href="https://vsr-bi.brinox.si/bx/apps/overhead-tracker/report.php">Odpri podrobno poročilo</a>
            </div>
        </div>
        <div class="footer">
            To sporočilo je bilo samodejno generirano.<br>
            Režijska dela - Brinox d.o.o.
        </div>
    </div>
</body>
</html>';

// Pošlji e-sporočilo
try {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();

    // SMTP nastavitve
    $mail->Host = isset($mailConfig['host']) ? $mailConfig['host'] : 'webmail.brinox.si';
    $mail->Port = isset($mailConfig['port']) ? $mailConfig['port'] : 25;
    $mail->SMTPAuth = isset($mailConfig['auth']) ? $mailConfig['auth'] : false;

    if ($mail->SMTPAuth && isset($mailConfig['username'])) {
        $mail->Username = $mailConfig['username'];
        $mail->Password = $mailConfig['password'];
    }

    $mail->setFrom(
        isset($mailConfig['from']['email']) ? $mailConfig['from']['email'] : 'noreply@brinox.eu',
        isset($mailConfig['from']['name']) ? $mailConfig['from']['name'] : 'BriNotify'
    );

    $mail->addAddress('matic.kocijancic@brinox.eu', 'Matic Kocijancic');

    $mail->isHTML(true);
    $mail->Subject = 'Rezijska dela - Mesecno porocilo (' . date('m/Y') . ')';
    $mail->Body = $html;
    $mail->AltBody = "Rezijska dela - Mesecno porocilo\n\nEvidenc: $totalEntries\nSkupaj ur: $totalHours\nPovprecje: {$avgHours}h\nZaposlenih: $totalEmployees\n\nPodrobnosti: https://vsr-bi.brinox.si/bx/apps/overhead-tracker/report.php";

    $mail->send();

    echo json_encode(['success' => true, 'message' => 'E-sporocilo uspesno poslano']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Napaka: ' . $mail->ErrorInfo]);
}
