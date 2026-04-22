<?php
// ============================================================
// PHPMailer Wrapper Class
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private static function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);

        // Use settings from DB if available, fallback to config constants
        $host     = getSetting('smtp_host', MAIL_SMTP_HOST);
        $port     = (int) getSetting('smtp_port', (string) MAIL_SMTP_PORT);
        $user     = getSetting('smtp_username', MAIL_SMTP_USERNAME);
        $pass     = decryptValue(getSetting('smtp_password', MAIL_SMTP_PASSWORD));
        $enc      = getSetting('smtp_encryption', MAIL_SMTP_ENCRYPTION);
        $fromMail = getSetting('smtp_from_email', MAIL_FROM_EMAIL);
        $fromName = getSetting('smtp_from_name', MAIL_FROM_NAME);

        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = ($enc === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';
        $mail->SMTPDebug  = defined('MAIL_DEBUG') ? MAIL_DEBUG : 0;
        $mail->Timeout    = 5; // 5 detik timeout, agar tidak hang lama

        $mail->setFrom($fromMail ?: 'noreply@localhost', $fromName ?: 'System');

        return $mail;
    }

    /**
     * Send email with HTML body
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $body,
        ?int $bookingId = null
    ): array {
        // Check if email is enabled
        if (getSetting('email_notification_enabled', '1') !== '1') {
            return ['success' => false, 'message' => 'Email notification dinonaktifkan.'];
        }

        // Skip jika SMTP username belum dikonfigurasi (hindari hanging)
        $smtpUser = getSetting('smtp_username', MAIL_SMTP_USERNAME);
        if (empty($smtpUser)) {
            self::log($bookingId, $toEmail, $toName, $subject, $body, 'failed', 'SMTP belum dikonfigurasi');
            return ['success' => false, 'message' => 'SMTP belum dikonfigurasi.'];
        }

        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $body));

            $mail->send();

            // Log success
            self::log($bookingId, $toEmail, $toName, $subject, $body, 'sent');

            return ['success' => true, 'message' => 'Email berhasil dikirim.'];

        } catch (Exception $e) {
            $error = $mail->ErrorInfo ?? $e->getMessage();

            // Log failure
            self::log($bookingId, $toEmail, $toName, $subject, $body, 'failed', $error);

            return ['success' => false, 'message' => 'Gagal mengirim email: ' . $error];
        }
    }

    /**
     * Log email to database
     */
    private static function log(
        ?int $bookingId,
        string $email,
        string $name,
        string $subject,
        string $body,
        string $status,
        string $error = ''
    ): void {
        try {
            db()->insert('mail_logs', [
                'booking_id'      => $bookingId,
                'recipient_email' => $email,
                'recipient_name'  => $name,
                'subject'         => $subject,
                'body'            => $body,
                'status'          => $status,
                'error_message'   => $error ?: null,
            ]);
        } catch (Exception $e) {
            // Silent fail
        }
    }

    /**
     * Ambil template dari DB dan substitusi variabel {{var}}.
     * Fallback ke template default hardcoded jika tipe tidak ditemukan di DB.
     */
    private static function renderTemplate(string $type, array $vars): array
    {
        $appName      = getSetting('app_name', APP_NAME);
        $primaryColor = getSetting('app_color_primary', '#0d6efd');

        try {
            $tpl = db()->fetch(
                "SELECT subject, body_html FROM email_templates WHERE type = ? AND is_active = 1",
                [$type]
            );
        } catch (Exception $_e) {
            $tpl = null;
        }

        if ($tpl) {
            $subject  = self::substituteVars($tpl['subject'], $vars);
            $bodyInner = self::substituteVars($tpl['body_html'], $vars);
        } else {
            // Fallback: tidak ada template di DB, pakai vars langsung
            $subject   = $vars['_fallback_subject'] ?? "[$appName] Notifikasi";
            $bodyInner = $vars['_fallback_content'] ?? '';
        }

        $body = '<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);">
  <tr><td style="background:' . $primaryColor . ';padding:25px 30px;text-align:center;">
    <h1 style="margin:0;color:#fff;font-size:22px;">' . e($appName) . '</h1>
  </td></tr>
  <tr><td style="padding:30px;color:#333;font-size:15px;line-height:1.6;">' . $bodyInner . '</td></tr>
  <tr><td style="background:#f8f9fa;padding:15px 30px;text-align:center;color:#6c757d;font-size:12px;border-top:1px solid #dee2e6;">
    <p style="margin:0">Email ini dikirim otomatis oleh sistem. Jangan membalas email ini.</p>
    <p style="margin:5px 0 0;">&copy; ' . date('Y') . ' ' . e($appName) . '. All rights reserved.</p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>';

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Ganti placeholder {{var}} dengan nilai aktual
     */
    private static function substituteVars(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            if (strpos($key, '_') === 0) continue; // skip internal keys
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $template;
    }

    /**
     * Send booking confirmation email
     */
    public static function sendBookingConfirmation(array $booking, array $room = []): array
    {
        $appName    = getSetting('app_name', APP_NAME);
        $appUrl     = getSetting('app_url', BASE_URL);
        $checkinUrl = $appUrl . '/checkin.php?token=' . $booking['token'];
        $roomName   = $room['name'] ?? $booking['room_name'] ?? '-';

        if ($booking['status'] === 'confirmed') {
            $statusLabel   = '✅ DIKONFIRMASI';
            $checkinSection = '<div style="background:#d1fae5;padding:15px;border-radius:8px;margin:15px 0;text-align:center;">
  <p style="margin:0 0 10px;color:#065f46;font-weight:bold;">Link Check-in Anda:</p>
  <a href="' . $checkinUrl . '" style="background:#198754;color:#fff;padding:12px 25px;border-radius:6px;text-decoration:none;font-size:16px;display:inline-block;">Buka Link Check-in</a>
  <p style="margin:10px 0 0;color:#065f46;font-size:12px;">Link ini hanya berlaku pada hari booking. Jangan bagikan ke orang lain.</p>
</div>';
        } else {
            $statusLabel    = '⏳ MENUNGGU APPROVAL';
            $checkinSection = '<p style="color:#92400e;background:#fef3c7;padding:12px;border-radius:6px;">Booking Anda sedang menunggu persetujuan. Anda akan menerima email konfirmasi setelah disetujui.</p>';
        }

        $tpl = self::renderTemplate('booking_confirmation', [
            'app_name'        => $appName,
            'booker_name'     => e($booking['booker_name']),
            'booking_code'    => e($booking['booking_code']),
            'room_name'       => e($roomName),
            'booking_date'    => formatDateId($booking['booking_date']),
            'booking_time'    => formatTimeRange($booking['start_time'], $booking['end_time']),
            'status_label'    => $statusLabel,
            'checkin_section' => $checkinSection,
            'checkin_url'     => $checkinUrl,
        ]);

        return self::send($booking['booker_email'], $booking['booker_name'], $tpl['subject'], $tpl['body'], $booking['id']);
    }

    /**
     * Send check-in confirmation email
     */
    public static function sendCheckinConfirmation(array $booking): array
    {
        $appName     = getSetting('app_name', APP_NAME);
        $checkinTime = $booking['checkin_at'] ? date('H:i', strtotime($booking['checkin_at'])) : '-';

        $tpl = self::renderTemplate('checkin_confirmation', [
            'app_name'     => $appName,
            'booker_name'  => e($booking['booker_name']),
            'booking_code' => e($booking['booking_code']),
            'room_name'    => e($booking['room_name'] ?? '-'),
            'booking_date' => formatDateId($booking['booking_date']),
            'booking_time' => formatTimeRange($booking['start_time'], $booking['end_time']),
            'checkin_time' => $checkinTime,
        ]);

        return self::send($booking['booker_email'], $booking['booker_name'], $tpl['subject'], $tpl['body'], $booking['id']);
    }

    /**
     * Send booking approved email
     */
    public static function sendBookingApproved(array $booking, array $room = []): array
    {
        $appName    = getSetting('app_name', APP_NAME);
        $appUrl     = getSetting('app_url', BASE_URL);
        $checkinUrl = $appUrl . '/checkin.php?token=' . $booking['token'];
        $roomName   = $room['name'] ?? $booking['room_name'] ?? '-';

        $tpl = self::renderTemplate('booking_approved', [
            'app_name'     => $appName,
            'booker_name'  => e($booking['booker_name']),
            'booking_code' => e($booking['booking_code']),
            'room_name'    => e($roomName),
            'booking_date' => formatDateId($booking['booking_date']),
            'booking_time' => formatTimeRange($booking['start_time'], $booking['end_time']),
            'checkin_url'  => $checkinUrl,
        ]);

        return self::send($booking['booker_email'], $booking['booker_name'], $tpl['subject'], $tpl['body'], $booking['id']);
    }

    /**
     * Send booking rejected email
     */
    public static function sendBookingRejected(array $booking, array $room = [], string $reason = ''): array
    {
        $appName  = getSetting('app_name', APP_NAME);
        $roomName = $room['name'] ?? $booking['room_name'] ?? '-';

        $reasonSection = $reason
            ? '<p style="background:#fff3cd;padding:12px;border-radius:6px;"><strong>Alasan:</strong> ' . e($reason) . '</p>'
            : '';

        $tpl = self::renderTemplate('booking_rejected', [
            'app_name'       => $appName,
            'booker_name'    => e($booking['booker_name']),
            'booking_code'   => e($booking['booking_code']),
            'room_name'      => e($roomName),
            'booking_date'   => formatDateId($booking['booking_date']),
            'booking_time'   => formatTimeRange($booking['start_time'], $booking['end_time']),
            'reason_section' => $reasonSection,
        ]);

        return self::send($booking['booker_email'], $booking['booker_name'], $tpl['subject'], $tpl['body'], $booking['id']);
    }
}
