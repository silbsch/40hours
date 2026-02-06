<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 🔒 stabile absolute Pfade
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/FortyHoursBookingDto.php';

final class FortyHoursBookingMailer
{
    public function __construct() {}

    public function sendBookingUserMail(FortyHoursBookingDto $booking): bool
    {
        $token = rawurlencode(generate_link_params($booking->reservationToken, 'u'));

        // --- Mail user ---
        $userCancelLink = FORTY_HOURS_APPLICATION_HOST . '/cancel.php?s=' . $token;

        $mailbody  = 'Hallo ' . preventAutoLinksInvisible($booking->name) . ',<br/>';
        $mailbody .= 'schön, dass Du Dich für das <b>'.FORTY_HOURS_NAME.'</b> im Haus der '.FORTY_HOURS_ORGANIZER.' angemeldet hast.<br/>';
        $mailbody .= 'Wir müssen nur noch kurz prüfen, ob am <b>' . sanitize($booking->startdate->format('d.m.Y')) . '</b> für die Zeit von <b>' . sanitize($booking->startdate->format('H:i')) . ' bis ' . sanitize($booking->enddate->format('H:i')) . '</b> alles passt.<br/>';
        $mailbody .= 'Du erhältst bald eine Bestätigungsmail - schau ggf. bitte auch im Spam-Ordner nach.<br/><br/>';
        $mailbody .= 'Wir wünschen Dir eine gesegnete Zeit.<br/><br/>';
        $mailbody .= 'Falls Du den Termin doch nicht wahrnehmen kannst oder Dich versehentlich angemeldet hast, wäre es gut, wenn Du die Reservierung stornierst. Klicke dazu bitte folgenden Link an oder öffne ihn in einem Browser Deiner Wahl.<br/>';
        $mailbody .= "<a href='" . sanitize($userCancelLink) . "'>Reservierung stornieren</a><br/><br/>";
        $mailbody .= 'Das Team der ' .FORTY_HOURS_ORGANIZER;

        $mailsubject = 'Reservierung ' . FORTY_HOURS_NAME . ' erhalten';
        $ics_data = build_ics_event([
            'uid' => $booking->reservationToken,
            'start' => $booking->startdate,
            'end' => $booking->enddate,
            'summary' => FORTY_HOURS_NAME.' in der '.FORTY_HOURS_ORGANIZER,
            'description' => 'Reservierung für das '.FORTY_HOURS_NAME.' in der '.FORTY_HOURS_ORGANIZER.' am ' . sanitize($booking->startdate->format('d.m.Y H:i')) . ' bis ' . sanitize($booking->enddate->format('H:i')) . '.',
            'attendee_name' => sanitize($booking->name),
            'attendee_email' => $booking->email,
        ], IcsStatus::REQUEST);
        // If sending fails, we still keep the reservation; mailer should log errors.
        return $this->send_mail($booking->email, $booking->name, $mailsubject, $mailbody, $ics_data);
    }

    public function sendBookingTeamMail(FortyHoursBookingDto $booking): bool
    {
        $token = rawurlencode(generate_link_params($booking->reservationToken, 't'));

        // --- Mail team ---
        $teamCancelLink = FORTY_HOURS_APPLICATION_HOST . '/cancel.php?s=' . $token;
        $teamCompleteLink = FORTY_HOURS_APPLICATION_HOST . '/confirm.php?s=' . $token;

        $teamBody  = 'Anmeldung für das '.FORTY_HOURS_NAME.'<br/><br/>';
        $teamBody .= 'Name: <b>' . preventAutoLinksInvisible($booking->name) . '</b><br/>';
        $teamBody .= 'Start: <b>' . sanitize($booking->startdate->format('d.m.Y H:i')) . '</b><br/>';
        $teamBody .= 'Ende: <b>' . sanitize($booking->enddate->format('d.m.Y H:i')) . '</b><br/>';
        $teamBody .= 'Mail: <b>' . sanitize($booking->email) . '</b><br/>';
        $teamBody .= 'Gemeinsam: <b>' . sanitize($booking->isPublic ? 'ja' : "nein") . '</b><br/>';
        $teamBody .= 'Titel: <b>' . preventAutoLinksInvisible($booking->title) . '</b><br/><br/>';
        $teamBody .= 'Um die Reservierung zu bestätigen, klicke bitte folgenden Link an oder öffne ihn in einem Browser deiner Wahl:<br/>';
        $teamBody .= "<a href='" . sanitize($teamCompleteLink) . "'>Reservierung bestätigen</a><br/><br/>";
        $teamBody .= 'Falls Du die Reservierung stornieren musst, nutze bitte folgenden Link:<br/>';
        $teamBody .= "<a href='" . sanitize($teamCancelLink) . "'>Reservierung stornieren</a><br/><br/>";
        $mailsubject = 'Reservierung ' . FORTY_HOURS_NAME . ' erhalten';
        
        return $this->send_mail(FORTY_HOURS_TEAM_EMAIL, FORTY_HOURS_NAME, $mailsubject, $teamBody);
    }

    /**
     * Sendet eine E-Mail mit optionalem ICS-Anhang
     *
     * @return bool true bei Erfolg, false bei Fehler
     */
    private function send_mail(
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
                    FORTY_HOURS_NAME.'.ics',
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
}

