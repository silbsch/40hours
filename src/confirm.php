<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/functions/calender.php';
require_once __DIR__ . '/functions/database.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/layout.php';
require_once __DIR__ . '/functions/mailer.php';


/* =========================================================
 * POST → Buchung bestätigen
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = post_string('token') ?? '';
    $csrf  = post_string('csrf_token') ?? '';

    if ($token === '' || !validate_csrf_token($csrf)) {
        render_invalid_link();
        exit;
    }

    try {
        $repo = new FortyHoursRepository(Database::pdo());
        $booking = $repo->completeByToken($token);
    } catch (Throwable $e) {
        error_log('40hours_cancel DB error: ' . $e->getMessage());
        render_internal_error('Bestätigen');
        exit;
    }

    if ($booking === null) {
        render_not_found();
    } 
    else {
        /* ---------- Success ---------- */
        $start = new DateTimeImmutable((string)$booking['start']);
        $end   = new DateTimeImmutable((string)$booking['end']);
        $name = sanitize($booking['name']);
        $mail = sanitize($booking['email']);
        $reservationToken = sanitize($booking['reservation_token']);

        render("team/confirmation_team", true, 200, [
            'name'  => $name,
            'start' => sanitize($start->format('d.m.Y H:i')),
            'end'   => sanitize($end->format('d.m.Y H:i')),
            'public'=> sanitize(($booking['public'] === 1 ? 'Ja' : 'Nein')),
            'title' => sanitize($booking['title']),
        ]);

        $mailbody ="Hallo ".preventAutoLinksInvisible($booking['name']).",<br/>";
        $mailbody.="Deine Anmeldung für das <b>".FORTY_HOURS_NAME."</b> im Haus der ".FORTY_HOURS_ORGANIZER." wurde nun bestätigt.<br/>";
        $mailbody.="Für dich ist am <b>".sanitize($start->format('d.m.Y'))."</b> die Zeit von <b>".sanitize($start->format('H:i'))." bis ".sanitize($end->format('H:i'))."</b> reserviert.<br/><br/>";
        $mailbody.="Wir wünschen dir eine gesegnete Zeit.<br/>";
        $mailbody.="Das Team der ".FORTY_HOURS_ORGANIZER;
        $mailsubject ="Reservierung ".FORTY_HOURS_NAME." bestätigt";
         
        $ics_data = build_ics_event([
            'uid' => $reservationToken,
            'start' => $start,
            'end' => $end,
            'summary' => FORTY_HOURS_NAME.' in der '.FORTY_HOURS_ORGANIZER,
            'description' => 'Reservierung für das '.FORTY_HOURS_NAME.' in der '.FORTY_HOURS_ORGANIZER.' am ' . $start->format('d.m.Y H:i') . ' bis ' . $end->format('H:i') . '.',
            'attendee_name' => $name,
            'attendee_email' => $mail,
        ], IcsStatus::CONFIRMED);
        
        // If sending fails, we still keep the reservation; mailer should log errors.
        send_mail($mail, $name, $mailsubject, $mailbody, $ics_data);
    }
    exit;
}

/* =========================================================
 * GET → Bestätigungsseite anzeigen
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $token = get_string('s');

    if (is_null_or_empty($token)) {
        render_missing_link();
        exit;
    }

    $token = sanitize($token);

    try {
        $repo = new FortyHoursRepository(Database::pdo());
        $booking = $repo->findByToken($token);
    } catch (Throwable $e) {
        error_log('40hours_cancel DB error: ' . $e->getMessage());
        render_internal_error('Bestätigen');
        exit;
    }

    if ($booking === null) {
        render_not_found();
        exit;
    }

    /* ---------- Bestätigungsformular ---------- */
    $start = new DateTimeImmutable((string)$booking['start']);
    $end   = new DateTimeImmutable((string)$booking['end']);
    $completion_on = sanitize((string)$booking['completion_on']);

    if(!is_null_or_empty($completion_on)) {

        render("team/confirmed_team", true, 200, [
            'name'  => sanitize($booking['name']),
            'start' => sanitize($start->format('d.m.Y H:i')),
            'end'   => sanitize($end->format('d.m.Y H:i')),
            'completion' => sanitize((new DateTimeImmutable($completion_on))->format('d.m.Y H:i')),
            'page_title' => 'Reservierung bestätigt',
        ]);
    }
    else {
        render("team/confirmation_form_team", true, 200, [
            'name'  => sanitize($booking['name']),
            'start' => sanitize($start->format('d.m.Y H:i')),
            'end'   => sanitize($end->format('d.m.Y H:i')),
            'public'=> $booking['public'] === 1 ? 'Ja' : 'Nein',
            'title' => sanitize($booking['title']),
            'token' => $token,
            'csrf_token' => sanitize(generate_csrf_token()),
            'page_title' => 'Reservierung bestätigen',
        ]);
    }
    exit;
}

header('Location: ' . FORTY_HOURS_BASE_URL);
?>
