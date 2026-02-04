<?php
declare(strict_types=1);

enum FortyHoursBookingErrorCode
{
    case NONE;
    case EMPTY_REQUEST;
    case INVALID_CSRF;
    case INVALID_EMAIL;
    case INVALID_DATE;
    case INTERNAL_ERROR;
}

final class FortyHoursBookingResult
{
    public function __construct(
        public readonly FortyHoursBookingErrorCode $error,
        public readonly ?FortyHoursBookingDto $booking = null,
    ) {}
}

final class FortyHoursBookingDto
{
    public function __construct(
        public readonly DateTimeImmutable $startdate,
        public readonly DateTimeImmutable $enddate,
        public readonly string $name,
        public readonly string $email,
        public readonly string $title,
        public readonly bool $isPublic,
        public readonly string $reservationToken,
    ) {}
}