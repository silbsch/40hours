<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/40hours/bootstrap.php';
require_once dirname(__DIR__).'/40hours/calendar.php';
require_once dirname(__DIR__).'/40hours/database.php';
require_once dirname(__DIR__).'/40hours/helpers.php';
require_once dirname(__DIR__).'/40hours/layout.php';
require_once dirname(__DIR__).'/40hours/mailer.php';
require_once dirname(__DIR__).'/40hours/FortyHoursRepository.php';



/* =========================================================
 * POST → Buchung bestätigen
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = post_string('csrf_token') ?? '';
    $token_values = validate_link_params($token);

    if ($token_values === null) {
        render_invalid_link();
        exit;
    }

    $token = sanitize($token_values['token']);
    $method = sanitize($token_values['method']);
    $action = sanitize($token_values['action']);

    if (is_null_or_empty($token) || $method !== 'post' || $action !== 'confirm') {
        render_missing_link();
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
        exit;
    } 

    $start = new DateTimeImmutable((string)$booking['start']);
    $end   = new DateTimeImmutable((string)$booking['end']);
    $name = sanitize($booking['name']);

    if($booking['updated'] === false) {
        render("team/confirmed_team", true, 200, [
            'name'  => $name,
            'start' => sanitize($start->format('d.m.Y H:i')),
            'end'   => sanitize($end->format('d.m.Y H:i')),
            'completion' => sanitize((new DateTimeImmutable((string)$booking['completion_on']))->format('d.m.Y H:i')),
            'page_title' => 'Anmeldung bestätigt',
        ]);
    }
    else {
        /* ---------- Success ---------- */
        $mail = sanitize($booking['email']);
        $reservationToken = sanitize($booking['reservation_token']);

        render("team/confirmation_team", true, 200, [
            'name'  => $name,
            'start' => sanitize($start->format('d.m.Y H:i')),
            'end'   => sanitize($end->format('d.m.Y H:i')),
            'public'=> sanitize(($booking['public'] === 1 ? 'Ja' : 'Nein')),
            'title' => sanitize($booking['title']),
            'page_title' => 'Anmeldung bestätigt',
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
    $token_values = validate_link_params($token);

    if ($token_values === null) {
        render_invalid_link();
        exit;
    }
    
    $token = sanitize($token_values['token']);
    $method = sanitize($token_values['method']);
    $action = sanitize($token_values['action']);

    if (is_null_or_empty($token) || $method !== 'get' || $action !== 'confirm') {
        render_missing_link();
        exit;
    }
    
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

    $csrf_token = generate_link_params(['token' => $booking['reservation_token'], 'method' => 'post', 'action' => 'confirm']);
    
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
            'page_title' => 'Anmeldung bestätigt',
        ]);
    }
    else {
        render("team/confirmation_form_team", true, 200, [
            'name'  => sanitize($booking['name']),
            'start' => sanitize($start->format('d.m.Y H:i')),
            'end'   => sanitize($end->format('d.m.Y H:i')),
            'public'=> $booking['public'] === 1 ? 'Ja' : 'Nein',
            'title' => sanitize($booking['title']),
            'csrf_token' => $csrf_token,
            'page_title' => 'Anmeldung bestätigen',
        ]);
    }
    exit;
}

header('Location: ' . FORTY_HOURS_BASE_URL);
?>
