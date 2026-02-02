<?php
declare(strict_types=1);

enum IcsStatus
{
    case REQUEST;
    case CONFIRMED;
    case CANCEL;
}

/**
 * Base64URL (für Tokens in Links)
 */
function base64url_encode(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

/**
 * iCalendar TEXT escapen (RFC-konform genug für gängige Clients)
 */
function ics_escape_text(string $text): string {
    $text = str_replace("\\", "\\\\", $text);
    $text = str_replace(["\r\n", "\n", "\r"], "\\n", $text);
    $text = str_replace([",", ";"], ["\\,", "\\;"], $text);
    return $text;
}

/**
 * Parameterwert escapen (z.B. CN="Max Mustermann")
 */
function ics_escape_param(string $text): string {
    $text = str_replace(["\\", "\""], ["\\\\", "\\\""], $text);
    return "\"{$text}\"";
}

/**
 * Zeilenfaltung (max ~75 Bytes; für UTF-8 nicht perfekt bytegenau,
 * aber in der Praxis in den meisten Fällen ok. Wenn du sehr viel Unicode hast,
 * kann man das bytegenau machen.)
 */
function ics_fold_line(string $line, int $limit = 75): string {
    $out = '';
    while (strlen($line) > $limit) {
        $out .= substr($line, 0, $limit) . "\r\n ";
        $line = substr($line, $limit);
    }
    return $out . $line;
}

/**
 * DateTimeInterface => UTC im iCalendar Format
 */
function ics_format_utc(DateTimeInterface $dt): string {
    return gmdate('Ymd\THis\Z', $dt->setTimeZone(new DateTimeZone('UTC'))->getTimestamp());
}

/**
 * ICS bauen (METHOD REQUEST oder CANCEL)
 */
function build_ics_event(array $e, IcsStatus $status): string {
    // erwartet Keys:
    // uid, start (DateTimeInterface), end (DateTimeInterface),
    // summary, description, attendee_email, attendee_name

    $method = match ($status) {
        IcsStatus::REQUEST, IcsStatus::CONFIRMED => 'REQUEST',
        IcsStatus::CANCEL => 'CANCEL',
    };
    $status_line = match ($status) {
        IcsStatus::REQUEST => 'CONFIRMED',
        IcsStatus::CONFIRMED => 'CONFIRMED',
        IcsStatus::CANCEL => 'CANCELLED',
    };
    $sequence = match ($status) {
        IcsStatus::REQUEST => 0,
        IcsStatus::CONFIRMED => 1,
        IcsStatus::CANCEL => 2,
    };

    $dtstamp = gmdate('Ymd\THis\Z');

    $lines = [
        'BEGIN:VCALENDAR',
        'PRODID:-//'.FORTY_HOURS_DOMAIN.'//'.FORTY_HOURS_NAME.'//DE',
        'VERSION:2.0',
        'CALSCALE:GREGORIAN',
        'METHOD:' . $method,
        'BEGIN:VEVENT',
        'UID:' . $e['uid'] . '@'.FORTY_HOURS_DOMAIN,
        'DTSTAMP:' . ics_format_utc(new DateTimeImmutable('now', new DateTimeZone(TIMEZONE))),
        'DTSTART:' . ics_format_utc($e['start']),
        'DTEND:' . ics_format_utc($e['end']),
        'SEQUENCE:' . (int)$sequence,
        'SUMMARY:' . ics_escape_text($e['summary'] ?? ''),
        'DESCRIPTION:' . ics_escape_text($e['description'] ?? ''),
        'LOCATION:'.FORTY_HOURS_LOCATION,
        'ORGANIZER;CN='.FORTY_HOURS_ORGANIZER.':mailto:'.FORTY_HOURS_TEAM_EMAIL,
        // ATTENDEE ist hilfreich, damit REQUEST/CANCEL sauber zuordenbar ist:
        'ATTENDEE;CN=' . ics_escape_param($e['attendee_name'] ?? '') .';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:' . $e['attendee_email'],
        'STATUS:' . $status_line,
        'END:VEVENT',
        'END:VCALENDAR'
    ];

    $lines = array_map('ics_fold_line', $lines);
    return implode("\r\n", $lines) . "\r\n";
}
