<?php
declare(strict_types=1);

// New helpers + PDO repository
require_once dirname(__DIR__).'/40hours/bootstrap.php';
require_once dirname(__DIR__).'/40hours/calendar.php';
require_once dirname(__DIR__).'/40hours/database.php';
require_once dirname(__DIR__).'/40hours/helpers.php';
require_once dirname(__DIR__).'/40hours/layout.php';
require_once dirname(__DIR__).'/40hours/mailer.php';

require_once dirname(__DIR__).'/40hours/FortyHoursBookingDto.php';
require_once dirname(__DIR__).'/40hours/FortyHoursBookingController.php';

ensure_session_started();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . FORTY_HOURS_BASE_URL);
    exit;
}

$repo = new FortyHoursRepository(Database::pdo());
$controller = new FortyHoursBookingController($repo);

$result = $controller->createBooking($_POST);
switch ($result->error) {
    case FortyHoursBookingErrorCode::NONE:
        $booking = $result->booking;
        render_success([
            'name'      => sanitize($booking->name),
            'startDate' => sanitize($booking->startdate->format('d.m.Y')),
            'startTime' => sanitize($booking->startdate->format('H:i')),
            'endTime'   => sanitize($booking->enddate->format('H:i')),
            'email'     => sanitize($booking->email),
        ]);
        break;
    case FortyHoursBookingErrorCode::EMPTY_REQUEST:
        render_invalid_request();
        break;
    case FortyHoursBookingErrorCode::INVALID_CSRF:
        render_invalid_request();
        break;
    case FortyHoursBookingErrorCode::INVALID_EMAIL:
        render_invalid_email(sanitize($_POST['fortyhoursname'] ?? ''));
        break;
    case FortyHoursBookingErrorCode::INVALID_DATE:
        render_invalid_date(sanitize($_POST['fortyhoursname'] ?? ''));
        break;
    case FortyHoursBookingErrorCode::INTERNAL_ERROR:
    default:
        render_already_reserved(sanitize($_POST['fortyhoursname'] ?? ''));
        break;
}

if($result->error !== FortyHoursBookingErrorCode::NONE) {
    exit;
}
$token = rawurlencode($result->booking->reservationToken);

// --- Mail user ---
$userCancelLink = FORTY_HOURS_APPLICATION_HOST . '/cancel.php?s=' . $token . '&t=u';
$teamCancelLink = FORTY_HOURS_APPLICATION_HOST . '/cancel.php?s=' . $token . '&t=t';
$teamCompleteLink = FORTY_HOURS_APPLICATION_HOST . '/confirm.php?s=' . $token;

$mailbody  = 'Hallo ' . preventAutoLinksInvisible($result->booking->name) . ',<br/>';
$mailbody .= 'schön, dass Du Dich für das <b>'.FORTY_HOURS_NAME.'</b> im Haus der '.FORTY_HOURS_ORGANIZER.' angemeldet hast.<br/>';
$mailbody .= 'Wir müssen nur noch kurz prüfen, ob am <b>' . sanitize($result->booking->startdate->format('d.m.Y')) . '</b> für die Zeit von <b>' . sanitize($result->booking->startdate->format('H:i')) . ' bis ' . sanitize($result->booking->enddate->format('H:i')) . '</b> alles passt.<br/>';
$mailbody .= 'Du erhältst bald eine Bestätigungsmail - schau ggf. bitte auch im Spam-Ordner nach.<br/><br/>';
$mailbody .= 'Wir wünschen Dir eine gesegnete Zeit.<br/><br/>';
$mailbody .= 'Falls Du den Termin doch nicht wahrnehmen kannst oder Dich versehentlich angemeldet hast, wäre es gut, wenn Du die Reservierung stornierst. Klicke dazu bitte folgenden Link an oder öffne ihn in einem Browser Deiner Wahl.<br/>';
$mailbody .= "<a href='" . sanitize($userCancelLink) . "'>Reservierung stornieren</a><br/><br/>";
$mailbody .= 'Das Team der ' .FORTY_HOURS_ORGANIZER;

$mailsubject = 'Reservierung ' . FORTY_HOURS_NAME . ' erhalten';
$ics_data = build_ics_event([
    'uid' => $result->booking->reservationToken,
    'start' => $result->booking->startdate,
    'end' => $result->booking->enddate,
    'summary' => FORTY_HOURS_NAME.' in der '.FORTY_HOURS_ORGANIZER,
    'description' => 'Reservierung für das '.FORTY_HOURS_NAME.' in der '.FORTY_HOURS_ORGANIZER.' am ' . sanitize($booking->startdate->format('d.m.Y H:i')) . ' bis ' . sanitize($booking->enddate->format('H:i')) . '.',
    'attendee_name' => sanitize($result->booking->name),
    'attendee_email' => $result->booking->email,
], IcsStatus::REQUEST);
// If sending fails, we still keep the reservation; mailer should log errors.
send_mail($result->booking->email, $result->booking->name, $mailsubject, $mailbody, $ics_data);

// --- Mail team ---

$teamBody  = 'Anmeldung für das '.FORTY_HOURS_NAME.'<br/><br/>';
$teamBody .= 'Name: <b>' . preventAutoLinksInvisible($result->booking->name) . '</b><br/>';
$teamBody .= 'Start: <b>' . sanitize($result->booking->startdate->format('d.m.Y H:i')) . '</b><br/>';
$teamBody .= 'Ende: <b>' . sanitize($result->booking->enddate->format('d.m.Y H:i')) . '</b><br/>';
$teamBody .= 'Mail: <b>' . sanitize($result->booking->email) . '</b><br/>';
$teamBody .= 'Gemeinsam: <b>' . sanitize($result->booking->isPublic ? 'ja' : "nein") . '</b><br/>';
$teamBody .= 'Titel: <b>' . preventAutoLinksInvisible($result->booking->title) . '</b><br/><br/>';
$teamBody .= 'Um die Reservierung zu bestätigen, klicke bitte folgenden Link an oder öffne ihn in einem Browser deiner Wahl:<br/>';
$teamBody .= "<a href='" . sanitize($teamCompleteLink) . "'>Reservierung bestätigen</a><br/><br/>";
$teamBody .= 'Falls Du die Reservierung stornieren musst, nutze bitte folgenden Link:<br/>';
$teamBody .= "<a href='" . sanitize($teamCancelLink) . "'>Reservierung stornieren</a><br/><br/>";

send_mail(FORTY_HOURS_TEAM_EMAIL, FORTY_HOURS_NAME, $mailsubject, $teamBody);
?>