<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 🔒 stabile absolute Pfade
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/helpers.php';

/**
 * Sendet eine E-Mail mit optionalem ICS-Anhang
 *
 * @return bool true bei Erfolg, false bei Fehler
 */
function send_mail(
    string $email,
    string $name,
    string $subject,
    string $body,
    ?string $icsData = null
): bool {
    if (!is_valid_email($email)) {
        return false;
    }

    try {
        $mail = new PHPMailer(true);

        // SMTP Konfiguration
        $mail->isSMTP();
        $mail->SMTPAuth   = true;
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        // Absender / Empfänger
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $name);
        $mail->addBCC(FORTY_HOURS_ADMIN_EMAIL, 'Anmeldung');

        // Inhalt
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        // ICS-Anhang
        if ($icsData !== null) {
            $mail->addStringAttachment(
                $icsData,
                'calendar.ics',
                'base64',
                'text/calendar; method=REQUEST; charset=UTF-8'
            );
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Mailer error: ' . $e->getMessage());
        return false;
    }
}
