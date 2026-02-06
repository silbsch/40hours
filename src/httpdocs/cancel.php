<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/40hours/bootstrap.php';
require_once dirname(__DIR__).'/40hours/calendar.php';
require_once dirname(__DIR__).'/40hours/database.php';
require_once dirname(__DIR__).'/40hours/helpers.php';
require_once dirname(__DIR__).'/40hours/layout.php';
require_once dirname(__DIR__).'/40hours/mailer.php';

$repo = new FortyHoursRepository(Database::pdo());

/* =========================================================
 * POST → Buchung löschen
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type = post_string('type') ?? '';
    $token = post_string('token') ?? '';
    $csrf  = post_string('csrf_token') ?? '';

    if ($token === '' || !validate_csrf_token($csrf)) {
        render_invalid_link();
        exit;
    }

    try {
        $booking = $repo->deleteByToken($token);
    } catch (Throwable $e) {
        error_log('40hours_cancel DB error: ' . $e->getMessage());
        render_internal_error('Stornieren');
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

        if($type === 'u') {
            render("user/cancelled_user", true, 200, [
                'name'  => $name,
                'startDate' => sanitize($start->format('d.m.Y')),
                'startTime' => sanitize($start->format('H:i')),
                'endTime'   => sanitize($end->format('H:i')),
                'page_title' => 'Stornierung bestätigt',
            ]);
        }
        else {
            render("team/cancelled_team", true, 200, [
                'name'  => $name,
                'start' => sanitize($start->format('d.m.Y H:i')),
                'end'   => sanitize($end->format('d.m.Y H:i')),
                'page_title' => 'Stornierung bestätigt',
            ]);
        }

        
        $mailbody ="Hallo ".preventAutoLinksInvisible($booking['name']).",<br/>";
        $mailbody.="schade, dass Du Deinen Gebetstermin beim ".FORTY_HOURS_NAME." am ".sanitize($start->format('d.m.Y'))." von ".sanitize($start->format('H:i'))." bis ".sanitize($end->format('H:i'))." nicht wahrnehmen kannst - aber danke, dass Du uns kurz Bescheid gegeben hast.<br/><br/>";
        $mailbody.="Deine Reservierung wurde erfolgreich storniert. Der Zeitraum steht nun wieder anderen zur Verfügung.<br/>";
        $mailbody.="Im Anhang findest Du eine Kalenderdatei, mit der der Termin auch aus Deinem Kalender entfernt wird.<br/><br/>";
        $mailbody.="Wir wünschen Dir weiterhin eine gesegnete Zeit und Gottes Nähe - ganz unabhängig von festen Zeiten und Orten.<br/>";
        $mailbody.="Das Team der ".FORTY_HOURS_ORGANIZER;
        $mailsubject ="Stornierung Deiner Terminreservierung - ".FORTY_HOURS_NAME;
         
        $ics_data = null;
        $ics_data = build_ics_event([
            'uid' => $reservationToken,
            'start' => $start,
            'end' => $end,
            'summary' => FORTY_HOURS_NAME.' in der '.FORTY_HOURS_ORGANIZER,
            'description' => 'Stornierung Deiner Terminreservierung für das '.FORTY_HOURS_NAME.' in der '.FORTY_HOURS_ORGANIZER.' am ' . $start->format('d.m.Y H:i') . ' bis ' . $end->format('H:i') . '.',
            'attendee_name' => $name,
            'attendee_email' => $mail,
        ], IcsStatus::CANCEL);
        
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
    $token_values= validate_link_params($token);

    if ($token_values === null) {
        render_invalid_link();
        exit;
    }
    
    $token = sanitize($token_values['token']);
    $type = sanitize($token_values['type']);

    if (is_null_or_empty($token) || ($type !== 'u' && $type !== 't')) {
        render_missing_link();
        exit;
    }
    
    try {
        $booking = $repo->findByToken($token);
    } catch (Throwable $e) {
        error_log('40hours_cancel DB error: ' . $e->getMessage());
        render_internal_error('Stornieren');
        exit;
    }

    if ($booking === null) {
        render_not_found();
        exit;
    }

    /* ---------- Bestätigungsformular ---------- */
    $start = new DateTimeImmutable((string)$booking['start']);
    $end   = new DateTimeImmutable((string)$booking['end']);
    if($type === 'u') {
       render("user/cancellation_form_user", true, 200, [
                'name'  => sanitize($booking['name']),
                'startDate' => sanitize($start->format('d.m.Y')),
                'startTime' => sanitize($start->format('H:i')),
                'endTime'   => sanitize($end->format('H:i')),
                'token' => $token,
                'csrf_token' => sanitize(generate_csrf_token()),
                'page_title' => 'Reservierung stornieren',
            ]);
    }
    else {
            render("team/cancellation_form_team", true, 200, [
                'name'  => sanitize($booking['name']),
                'start' => sanitize($start->format('d.m.Y H:i')),
                'end'   => sanitize($end->format('d.m.Y H:i')),
                'public'=> $booking['public'] === 1 ? 'Ja' : 'Nein',
                'title' => sanitize($booking['title']),
                'token' => $token,
                'csrf_token' => sanitize(generate_csrf_token()),
                'page_title' => 'Reservierung stornieren',
            ]);
    }
    exit;
}

header('Location: ' . FORTY_HOURS_BASE_URL);
?>