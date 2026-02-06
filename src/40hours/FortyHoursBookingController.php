<?php
declare(strict_types=1);
/*
namespace 40hours;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
*/

final class FortyHoursBookingController
{
    public function __construct(
        private readonly FortyHoursRepository $repo
    ) {}

    public function createBooking(array $data=[]): FortyHoursBookingResult
    {
        // Session wird für CSRF + Double-Submit Schutz benötigt
        ensure_session_started();

        // --- CSRF ---
        $csrf = get_string_from_array($data, 'csrf_token') ?? '';
        if (is_null_or_empty($csrf) || !validate_csrf_token($csrf)) {
            return new FortyHoursBookingResult(FortyHoursBookingErrorCode::INVALID_CSRF);
        }

        // --- Required fields ---
        $name    = get_string_from_array($data, 'fortyhoursname');
        $dateRaw = get_string_from_array($data, 'fortyhoursdate');
        $mail    = get_string_from_array($data, 'fortyhoursemail');

        if (is_null_or_empty($name) || is_null_or_empty($dateRaw) || is_null_or_empty($mail)) {
            return new FortyHoursBookingResult(FortyHoursBookingErrorCode::EMPTY_REQUEST);
        }

        $mail = sanitize($mail);
        if (!is_valid_email($mail)) {
           return new FortyHoursBookingResult(FortyHoursBookingErrorCode::INVALID_EMAIL);
        }

        try {
            $startdate = new DateTimeImmutable($dateRaw, new DateTimeZone(TIMEZONE));
            $enddate = $startdate->modify('+1 hour');
        } catch (Throwable $e) {
            return new FortyHoursBookingResult(FortyHoursBookingErrorCode::INVALID_DATE);
            exit;
        }

        if($startdate < FORTY_HOURS_START_DATE || $enddate > FORTY_HOURS_END_DATE) {
            return new FortyHoursBookingResult(FortyHoursBookingErrorCode::INVALID_DATE);
        }

        // --- Optional fields ---
        $title    = get_string_from_array($data, 'fortyhourstitle') ?? '';
        $isPublic = isset($_POST['fortyhourspublic']);

        // --- Prevent accidental double submit in same session ---
        $postKey = hash('sha256', $mail . '|' . $startdate->format('Y-m-d H:i:s'));
/*
        if (isset($_SESSION['wppb']) && is_string($_SESSION['wppb']) && hash_equals($_SESSION['wppb'], $postKey)) {
            render_confirmed_user([
                'name'      => sanitize($name),
                'startDate' => sanitize($startdate->format('d.m.Y')),
                'startTime' => sanitize($startdate->format('H:i')),
                'endTime'   => sanitize($enddate->format('H:i')),
            ]);
            exit;
        }
        $_SESSION['wppb'] = $postKey;
*/
        // --- Reservation token (unguessable) ---
        $reservationToken = bin2hex(random_bytes(16));

        try {
            $ok = $this->repo->createReservation(
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
            error_log((string)$e);
            return new FortyHoursBookingResult(FortyHoursBookingErrorCode::INTERNAL_ERROR);
        }

        if ($ok !== true) {
            // Slot already taken OR internal error
            return new FortyHoursBookingResult(FortyHoursBookingErrorCode::INVALID_DATE);
        }

        return new FortyHoursBookingResult(FortyHoursBookingErrorCode::NONE, new FortyHoursBookingDto(
            $startdate,
            $enddate,
            $name,
            $mail,
            $title,
            $isPublic,
            $reservationToken
        ));
    }
}
