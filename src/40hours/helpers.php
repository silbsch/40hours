<?php
declare(strict_types=1);

/**
 * helpers.php
 * Kleine, sichere Helper-Funktionen für Eingaben, Ausgabe, UUID und CSRF.
 */

/**
 * Startet eine Session, falls noch keine aktiv ist.
 * Wichtig: Vor jeglicher Ausgabe (echo/HTML) aufrufen.
 */
function ensure_session_started(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * RFC 4122 UUID v4 Generator (cryptographically secure).
 */
function generate_uuid_v4(): string
{
    $data = random_bytes(16);

    // Version 4
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    // Variant RFC 4122
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * HTML-Escaping für sichere Ausgabe in HTML (Text-Kontext).
 * Nicht für Attribute/JS/URLs missbrauchen -> dort kontextgerecht escapen.
 */
function sanitize(string $value, bool $trim = true): string
{
    $value = $trim ? trim($value) : $value;
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Einfache E-Mail-Validierung.
 */
function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Erstellt (oder liefert) ein CSRF-Token pro Session.
 */
function generate_csrf_token(): string
{
    $timestamp = time();
    $data = $timestamp . ':' . bin2hex(random_bytes(16));
    $signature = hash_hmac('sha256', $data, CSRF_KEY);
    $token = base64_encode($data . ':' . $signature);
    return $token;
}

function generate_csrf_token_session(): string
{
    ensure_session_started();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Prüft CSRF-Token sicher (timing-safe).
 * Optional: Token nach erfolgreicher Prüfung rotieren.
 */
function validate_csrf_token(string $token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }

    $token = base64_decode($token);
    [$timestamp, $random, $signature] = explode(':', $token, 3);

    $data = $timestamp . ':' . $random;
    $expected = hash_hmac('sha256', $data, CSRF_KEY);

    if (!hash_equals($expected, $signature)) {
        return false;
    }

    if ($timestamp < time() - 3600) { // 1 Stunde gültig
        return false;
    }
    return true;
}

/**
 * Prüft CSRF-Token sicher (timing-safe).
 * Optional: Token nach erfolgreicher Prüfung rotieren.
 */
function validate_csrf_token_session(string $token, bool $rotateOnSuccess = true): bool
{
    ensure_session_started();

    $stored = $_SESSION['csrf_token'] ?? '';
    if (!is_string($stored) || $stored === '') {
        return false;
    }

    $ok = hash_equals($stored, $token);

    if ($ok && $rotateOnSuccess) {
        unset($_SESSION['csrf_token']);
    }

    return $ok;
}

function is_null_or_empty(string $value): bool
{
    return $value === null || trim($value) === '';
}

/**
 * Liest einen String aus $_POST (optional trim) – null wenn nicht vorhanden/kein string.
 */
function post_string(string $key, bool $trim = true): ?string
{
    $val = $_POST[$key] ?? null;
    if (!is_string($val)) {
        return null;
    }
    return $trim ? trim($val) : $val;
}

/**
 * Liest einen String aus $_GET (optional trim) – null wenn nicht vorhanden/kein string.
 */
function get_string(string $key, bool $trim = true): ?string
{
    $val = $_GET[$key] ?? null;

    if (!is_string($val)) {
        return null;
    }
    return $trim ? trim($val) : $val;
}

function get_name_of_the_day($date): string 
{
    $year = (int)$date->format('Y');
    $easter = new DateTime(date('Y-m-d',easter_date($year)));
    $x = new DateTime($easter->format('Y-m-d'));
    
    if($x->modify('-2 day') == $date) return "Gründonnerstag, ";
    if($x->modify('+1 day') == $date) return "Karfreitag, ";
    if($x->modify('+1 day') == $date) return "Karsamstag, ";
    if($x->modify('+1 day') == $date) return "Ostersonntag, ";
    if($x->modify('+1 day') == $date) return "Ostermontag, ";
    
    return "";
}

function get_base_link() {
    return "<a href='".FORTY_HOURS_BASE_URL."'>zurück zur Übersicht</a>";
}

function get_application_base_link() {
        return "<a href='".FORTY_HOURS_APPLICATION_BASE_URL."'>zurück zur Übersichtsseite</a>";
}

function preventAutoLinksInvisible(string $text): string
{
    $zwsp = "\u{200B}"; // zero width space
    $text = sanitize($text);
    $text = str_replace('://', ":$zwsp//$zwsp", $text);
    $text = str_replace('.', ".$zwsp", $text);

    return $text;
}

?>