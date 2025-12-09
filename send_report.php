<?php
/**
 * Režijska dela - Pošiljanje e-poročila
 * Lokacija: C:\BriPHP\bxroot\apps\overhead-tracker\send_report.php
 */

header('Content-Type: application/json; charset=utf-8');

// Vključi PHPMailer
require_once __DIR__ . '/../../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../lib/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../db_config.php';

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
$monthYear = date('F Y', strtotime('first day of previous month'));

// Sestavi HTML e-sporočilo
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; line-height: 1.6; color: #1e293b; margin: 0; padding: 0; background: #f1f5f9; }
        .container { max-width: 640px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #0f172a 0%, #1e40af 100%); color: white; padding: 32px; text-align: center; }
        .header h1 { margin: 0 0 8px 0; font-size: 28px; font-weight: 700; letter-spacing: -0.5px; }
        .header p { margin: 0; opacity: 0.9; font-size: 14px; }
        .content { padding: 32px; }
        .intro { background: #f8fafc; border-left: 4px solid #2563eb; padding: 16px 20px; margin-bottom: 28px; border-radius: 0 8px 8px 0; }
        .intro p { margin: 0; color: #475569; font-size: 14px; }
        .kpi-grid { display: table; width: 100%; margin-bottom: 28px; border-collapse: separate; border-spacing: 12px; }
        .kpi-row { display: table-row; }
        .kpi-card { display: table-cell; background: #f8fafc; border-radius: 10px; padding: 20px; text-align: center; width: 25%; border: 1px solid #e2e8f0; }
        .kpi-card.blue { border-left: 4px solid #2563eb; }
        .kpi-card.teal { border-left: 4px solid #0d9488; }
        .kpi-card.amber { border-left: 4px solid #d97706; }
        .kpi-card.violet { border-left: 4px solid #7c3aed; }
        .kpi-value { font-size: 28px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .kpi-label { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; letter-spacing: 0.5px; }
        .section { margin-bottom: 28px; }
        .section-title { font-size: 14px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; }
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table th { background: #0f172a; color: white; padding: 12px 16px; text-align: left; font-weight: 600; }
        .table th:last-child { text-align: right; }
        .table td { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
        .table td:last-child { text-align: right; font-weight: 600; color: #2563eb; }
        .table tr:nth-child(even) { background: #f8fafc; }
        .table tr:hover { background: #eff6ff; }
        .rank { display: inline-block; width: 24px; height: 24px; background: #e2e8f0; border-radius: 50%; text-align: center; line-height: 24px; font-size: 11px; font-weight: 700; color: #64748b; margin-right: 10px; }
        .rank.gold { background: #fef3c7; color: #d97706; }
        .rank.silver { background: #f1f5f9; color: #64748b; }
        .rank.bronze { background: #fed7aa; color: #c2410c; }
        .location-bar { height: 8px; background: #e2e8f0; border-radius: 4px; margin-top: 6px; overflow: hidden; }
        .location-bar-fill { height: 100%; border-radius: 4px; }
        .cta { text-align: center; margin: 32px 0; }
        .cta a { display: inline-block; background: #2563eb; color: white; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; }
        .cta a:hover { background: #1d4ed8; }
        .footer { background: #f8fafc; padding: 24px 32px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer p { margin: 0; font-size: 12px; color: #64748b; }
        .footer a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Režijska dela</h1>
            <p>Mesečno poročilo • ' . $reportDate . '</p>
        </div>
        <div class="content">
            <div class="intro">
                <p>Spoštovani,<br><br>V priponki vam pošiljamo <strong>povzetek poročila za pretekli mesec</strong> o režijskih delih v proizvodnji. Podrobnejši podatki in interaktivni pregled so na voljo na spodnji povezavi.</p>
            </div>

            <div class="section">
                <div class="section-title">Ključni kazalniki</div>
                <table class="kpi-grid">
                    <tr class="kpi-row">
                        <td class="kpi-card blue">
                            <div class="kpi-value">' . number_format($totalEntries, 0, ',', '.') . '</div>
                            <div class="kpi-label">Evidenc</div>
                        </td>
                        <td class="kpi-card teal">
                            <div class="kpi-value">' . number_format((float)$totalHours, 1, ',', '.') . '</div>
                            <div class="kpi-label">Skupaj ur</div>
                        </td>
                        <td class="kpi-card amber">
                            <div class="kpi-value">' . number_format((float)$avgHours, 2, ',', '.') . 'h</div>
                            <div class="kpi-label">Povprečje</div>
                        </td>
                        <td class="kpi-card violet">
                            <div class="kpi-value">' . $totalEmployees . '</div>
                            <div class="kpi-label">Zaposlenih</div>
                        </td>
                    </tr>
                </table>
            </div>';

// Lokacije
if (!empty($byLocation)) {
    $maxLoc = max($byLocation);
    $html .= '
            <div class="section">
                <div class="section-title">Po lokacijah</div>';
    foreach ($byLocation as $loc => $hours) {
        $pct = $maxLoc > 0 ? ($hours / $maxLoc) * 100 : 0;
        $color = $loc === 'Sora' ? '#2563eb' : '#0d9488';
        $html .= '
                <div style="margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 500;">
                        <span>' . htmlspecialchars($loc) . '</span>
                        <span style="color: #2563eb; font-weight: 600;">' . number_format($hours, 0, ',', '.') . ' ur</span>
                    </div>
                    <div class="location-bar"><div class="location-bar-fill" style="width: ' . $pct . '%; background: ' . $color . ';"></div></div>
                </div>';
    }
    $html .= '</div>';
}

// Top 10 zaposlenih
if (!empty($topEmployees)) {
    $html .= '
            <div class="section">
                <div class="section-title">Top 10 zaposlenih</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Zaposleni</th>
                            <th>Ure</th>
                        </tr>
                    </thead>
                    <tbody>';
    $i = 1;
    foreach ($topEmployees as $emp) {
        $rankClass = $i === 1 ? 'gold' : ($i === 2 ? 'silver' : ($i === 3 ? 'bronze' : ''));
        $html .= '
                        <tr>
                            <td><span class="rank ' . $rankClass . '">' . $i . '</span></td>
                            <td>' . htmlspecialchars($emp['name'] ?? 'Neznano') . '</td>
                            <td>' . number_format($emp['hours'] ?? 0, 1, ',', '.') . 'h</td>
                        </tr>';
        $i++;
    }
    $html .= '
                    </tbody>
                </table>
            </div>';
}

$html .= '
            <div class="cta">
                <a href="https://vsr-bi.brinox.si/bx/apps/overhead-tracker/report.php">Odpri podrobno poročilo →</a>
            </div>
        </div>
        <div class="footer">
            <p>To sporočilo je bilo samodejno generirano.<br>
            <a href="https://vsr-bi.brinox.si/bx/apps/overhead-tracker/report.php">Režijska dela - Brinox d.o.o.</a></p>
        </div>
    </div>
</body>
</html>';

// Pošlji e-sporočilo
try {
    global $mailConfig;

    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = $mailConfig['host'] ?? 'webmail.brinox.si';
    $mail->Port = $mailConfig['port'] ?? 25;
    $mail->SMTPAuth = $mailConfig['auth'] ?? false;

    $mail->setFrom(
        $mailConfig['from']['email'] ?? 'noreply@brinox.eu',
        $mailConfig['from']['name'] ?? 'BriNotify'
    );

    $mail->addAddress('matic.kocijancic@brinox.eu', 'Matic Kocijančič');

    $mail->addReplyTo(
        $mailConfig['replyTo']['email'] ?? 'matic.kocijancic@brinox.eu',
        $mailConfig['replyTo']['name'] ?? 'Matic Kocijančič'
    );

    $mail->isHTML(true);
    $mail->Subject = '📊 Režijska dela - Mesečno poročilo (' . date('m/Y') . ')';
    $mail->Body = $html;
    $mail->AltBody = "Režijska dela - Mesečno poročilo\n\n" .
        "Evidenc: $totalEntries\n" .
        "Skupaj ur: $totalHours\n" .
        "Povprečje: {$avgHours}h\n" .
        "Zaposlenih: $totalEmployees\n\n" .
        "Podrobnosti: https://vsr-bi.brinox.si/bx/apps/overhead-tracker/report.php";

    $mail->send();

    echo json_encode(['success' => true, 'message' => 'E-sporočilo uspešno poslano']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Napaka pri pošiljanju: ' . $mail->ErrorInfo]);
}
?>
