<?php
declare(strict_types=1);

// New helpers + PDO repository
require_once dirname(__DIR__).'/40hours/bootstrap.php';
require_once dirname(__DIR__).'/40hours/calendar.php';
require_once dirname(__DIR__).'/40hours/database.php';
require_once dirname(__DIR__).'/40hours/helpers.php';
require_once dirname(__DIR__).'/40hours/layout.php';

require_once dirname(__DIR__).'/40hours/FortyHoursBookingDto.php';
require_once dirname(__DIR__).'/40hours/FortyHoursBookingController.php';
require_once dirname(__DIR__).'/40hours/FortyHoursMailer.php';

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

$mailer = new FortyHoursBookingMailer();
$mailer->sendBookingUserMail($result->booking);
$mailer->sendBookingTeamMail($result->booking);
?>