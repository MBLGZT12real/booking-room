<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requirePermission('settings.manage');

$db  = db();
$id  = (int) ($_GET['id'] ?? 0);
$tpl = $db->fetch("SELECT * FROM email_templates WHERE id = ?", [$id]);

if (!$tpl) {
    die('Template tidak ditemukan.');
}

$appName      = getSetting('app_name', APP_NAME);
$primaryColor = getSetting('app_color_primary', '#0d6efd');

// Sample data untuk preview
$sampleVars = [
    'app_name'        => $appName,
    'booker_name'     => 'Budi Santoso',
    'booking_code'    => 'BK-' . strtoupper(substr(md5('preview'), 0, 8)),
    'room_name'       => 'Ruang Rapat Utama',
    'booking_date'    => formatDateId(date('Y-m-d')),
    'booking_time'    => '09:00 - 11:00',
    'status_label'    => '✅ DIKONFIRMASI',
    'checkin_url'     => BASE_URL . '/checkin.php?token=sample-token',
    'checkin_section' => '<div style="background:#d1fae5;padding:15px;border-radius:8px;margin:15px 0;text-align:center;">
  <p style="margin:0 0 10px;color:#065f46;font-weight:bold;">Link Check-in Anda:</p>
  <a href="#" style="background:#198754;color:#fff;padding:12px 25px;border-radius:6px;text-decoration:none;font-size:16px;display:inline-block;">Buka Link Check-in</a>
  <p style="margin:10px 0 0;color:#065f46;font-size:12px;">Ini adalah preview — link tidak aktif.</p>
</div>',
    'reason_section'  => '<p style="background:#fff3cd;padding:12px;border-radius:6px;"><strong>Alasan:</strong> Ruangan tidak tersedia untuk waktu yang diminta.</p>',
    'checkin_time'    => '09:05',
];

// Substitusi variabel
$subject = $tpl['subject'];
$body    = $tpl['body_html'];
foreach ($sampleVars as $k => $v) {
    $subject = str_replace('{{' . $k . '}}', $v, $subject);
    $body    = str_replace('{{' . $k . '}}', $v, $body);
}

$fullHtml = '<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Preview: ' . e($tpl['name']) . '</title></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif;">
<div style="background:#1e2a3b;color:#fff;padding:10px 20px;font-size:13px;display:flex;align-items:center;gap:10px;">
  <span>📧 Preview Template: <strong>' . e($tpl['name']) . '</strong></span>
  <span style="margin-left:auto;opacity:0.7;">Subject: ' . e($subject) . '</span>
</div>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);">
  <tr><td style="background:' . $primaryColor . ';padding:25px 30px;text-align:center;">
    <h1 style="margin:0;color:#fff;font-size:22px;">' . e($appName) . '</h1>
  </td></tr>
  <tr><td style="padding:30px;color:#333;font-size:15px;line-height:1.6;">' . $body . '</td></tr>
  <tr><td style="background:#f8f9fa;padding:15px 30px;text-align:center;color:#6c757d;font-size:12px;border-top:1px solid #dee2e6;">
    <p style="margin:0">Email ini dikirim otomatis oleh sistem. Jangan membalas email ini.</p>
    <p style="margin:5px 0 0;">&copy; ' . date('Y') . ' ' . e($appName) . '. All rights reserved.</p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>';

header('Content-Type: text/html; charset=utf-8');
echo $fullHtml;
