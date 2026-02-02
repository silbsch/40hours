<?php
declare(strict_types=1);

// New helpers + PDO repository
require_once dirname(__DIR__).'/40hours/bootstrap.php';
require_once dirname(__DIR__).'/40hours/calendar.php';
require_once dirname(__DIR__).'/40hours/database.php';
require_once dirname(__DIR__).'/40hours/helpers.php';
require_once dirname(__DIR__).'/40hours/layout.php';
require_once dirname(__DIR__).'/40hours/mailer.php';

ensure_session_started();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . FORTY_HOURS_BASE_URL);
    exit;
}

// CSRF (expects your main form to submit csrf_token)
$csrf = post_string('csrf_token') ?? '';
if ($csrf === '' || !validate_csrf_token($csrf)) {
    render_invalid_request();
    exit;
}

// Required fields
$name = post_string('fortyhoursname');
$dateRaw = post_string('fortyhoursdate');
$mail = post_string('fortyhoursemail');

if (is_null_or_empty($name) || is_null_or_empty($dateRaw) || is_null_or_empty($mail)) {
    render_invalid_request();
    exit;
}

$mail = sanitize($mail);
if (!is_valid_email($mail)) {
    render_invalid_email(sanitize($name));
    exit;
}

try {
    $startdate = new DateTimeImmutable($dateRaw, new DateTimeZone(TIMEZONE));
} catch (Throwable $e) {
    render_invalid_date($name);
    exit;
}

$enddate = $startdate->modify('+1 hour');

// Optional fields
$title = post_string('fortyhourstitle') ?? '';
$isPublic = isset($_POST['fortyhourspublic']);

// Prevent accidental double submit in same session
$postKey = hash('sha256', $mail . '|' . $startdate->format('Y-m-d H:i:s'));

if (isset($_SESSION['wppb']) && is_string($_SESSION['wppb']) && hash_equals($_SESSION['wppb'], $postKey)) {
    render_confirmed_user([
        'name'  => sanitize($name),
        'startDate' => sanitize($startdate->format('d.m.Y')),
        'startTime' => sanitize($startdate->format('H:i')),
        'endTime'   => sanitize($enddate->format('H:i')),
    ]);
    exit;
}
$_SESSION['wppb'] = $postKey;

// Reservation token (unguessable)
$reservationToken = bin2hex(random_bytes(16));

$repo = new FortyHoursRepository(Database::pdo());

try {
    $ok = $repo->createReservation(
        $startdate,
        $enddate,
        $name,
        $mail,
        $title,
        $isPublic,
        $reservationToken
    );
} catch (Throwable $e) {
    // Do not leak details; log happens in repository
    $ok = null;
}

if ($ok !== true) {
    // Slot already taken OR internal error
    render_already_reserved(sanitize($name));
    exit;
}

// Success HTML
render_success([
    'name'      => sanitize($name),
    'startDate' => sanitize($startdate->format('d.m.Y')),
    'startTime' => sanitize($startdate->format('H:i')),
    'endTime'   => sanitize($enddate->format('H:i')),
    'email'     => sanitize($mail),
]);

// --- Mail user ---
$userCancelLink = FORTY_HOURS_APPLICATION_HOST . '/cancel.php?s=' . rawurlencode($reservationToken) . '&t=u';
$teamCancelLink = FORTY_HOURS_APPLICATION_HOST . '/cancel.php?s=' . rawurlencode($reservationToken) . '&t=t';
$teamCompleteLink = FORTY_HOURS_APPLICATION_HOST . '/confirm.php?s=' . rawurlencode($reservationToken);

$mailbody  = 'Hallo ' . preventAutoLinksInvisible($name) . ',<br/>';
$mailbody .= 'schön, dass Du Dich für das <b>'.FORTY_HOURS_NAME.'</b> im Haus der '.FORTY_HOURS_ORGANIZER.' angemeldet hast.<br/>';
$mailbody .= 'Wir müssen nur noch kurz prüfen, ob am <b>' . sanitize($startdate->format('d.m.Y')) . '</b> für die Zeit von <b>' . sanitize($startdate->format('H:i')) . ' bis ' . sanitize($enddate->format('H:i')) . '</b> alles passt.<br/>';
$mailbody .= 'Du erhältst bald eine Bestätigungsmail - schau ggf. bitte auch im Spam-Ordner nach.<br/><br/>';
$mailbody .= 'Wir wünschen Dir eine gesegnete Zeit.<br/><br/>';
$mailbody .= 'Falls Du den Termin doch nicht wahrnehmen kannst oder Dich versehentlich angemeldet hast, wäre es gut, wenn Du die Reservierung stornierst. Klicke dazu bitte folgenden Link an oder öffne ihn in einem Browser Deiner Wahl.<br/>';
$mailbody .= "<a href='" . sanitize($userCancelLink) . "'>Reservierung stornieren</a><br/><br/>";
$mailbody .= 'Das Team der ' .FORTY_HOURS_ORGANIZER;

$mailsubject = 'Reservierung ' . FORTY_HOURS_NAME . ' erhalten';
$ics_data = build_ics_event([
    'uid' => $reservationToken,
    'start' => $startdate,
    'end' => $enddate,
    'summary' => FORTY_HOURS_NAME.' in der '.FORTY_HOURS_ORGANIZER,
    'description' => 'Reservierung für das '.FORTY_HOURS_NAME.' in der '.FORTY_HOURS_ORGANIZER.' am ' . sanitize($startdate->format('d.m.Y H:i')) . ' bis ' . sanitize($enddate->format('H:i')) . '.',
    'attendee_name' => sanitize($name),
    'attendee_email' => $mail,
], IcsStatus::REQUEST);
// If sending fails, we still keep the reservation; mailer should log errors.
send_mail($mail, $name, $mailsubject, $mailbody, $ics_data);

// --- Mail team ---

$teamBody  = 'Anmeldung für das '.FORTY_HOURS_NAME.'<br/><br/>';
$teamBody .= 'Name: <b>' . preventAutoLinksInvisible($name) . '</b><br/>';
$teamBody .= 'Start: <b>' . sanitize($startdate->format('d.m.Y H:i')) . '</b><br/>';
$teamBody .= 'Ende: <b>' . sanitize($enddate->format('d.m.Y H:i')) . '</b><br/>';
$teamBody .= 'Mail: <b>' . sanitize($mail) . '</b><br/>';
$teamBody .= 'Gemeinsam: <b>' . sanitize($isPublic ? 'ja' : "nein") . '</b><br/>';
$teamBody .= 'Titel: <b>' . preventAutoLinksInvisible($title) . '</b><br/><br/>';
$teamBody .= 'Um die Reservierung zu bestätigen, klicke bitte folgenden Link an oder öffne ihn in einem Browser deiner Wahl:<br/>';
$teamBody .= "<a href='" . sanitize($teamCompleteLink) . "'>Reservierung bestätigen</a><br/><br/>";
$teamBody .= 'Falls Du die Reservierung stornieren musst, nutze bitte folgenden Link:<br/>';
$teamBody .= "<a href='" . sanitize($teamCancelLink) . "'>Reservierung stornieren</a><br/><br/>";

send_mail(FORTY_HOURS_TEAM_EMAIL, FORTY_HOURS_NAME, $mailsubject, $teamBody);
?>