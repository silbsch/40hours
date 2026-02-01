<?php
declare(strict_types=1);

/**
 * bootstrap.php (public-safe)
 * - Fail-fast .env loading with robust parsing
 * - Central error/exception handling (no stacktraces in browser)
 * - Security headers (iframe-friendly: no X-Frame-Options)
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    // Respect @ operator
    if (!(error_reporting() & $severity)) {
        return true;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    http_response_code(500);
    error_log((string)$e);

    $template = __DIR__ . '/templates/internal_error.php';
    if (is_file($template)) {
        require $template;
    } else {
        echo 'Internal Server Error';
    }
    exit;
});

// ---- Security headers ----
// IMPORTANT for your requirement: do NOT set X-Frame-Options, otherwise embedding in iframes breaks.
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// ---- Load .env (defines constants, as your code expects) ----
$envFile = __DIR__ . '/.env';
if (!is_file($envFile)) {
    throw new RuntimeException('Die Datei .env wurde nicht gefunden.');
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    throw new RuntimeException('Die Datei .env konnte nicht gelesen werden.');
}

// Keys that should be converted
$typeMapping = [
    'DATABASE_PORT' => 'int',
    'SMTP_PORT' => 'int',
    'FORTY_HOURS_START_DATE' => 'DateTimeImmutable',
    'FORTY_HOURS_END_DATE' => 'DateTimeImmutable',
];

foreach ($lines as $rawLine) {
    $line = trim($rawLine);

    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    // Aufteilen in KEY=VALUE
    if (strpos($line, '=') === false) {
        continue;
    }

    list($key, $value) = explode('=', $line, 2);
    $key = trim($key);
    if ($key === '') {
        continue;
    }
    
    $value = trim($value);
    // Entferne Anführungszeichen, falls vorhanden
    if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
        $value = $matches[1];
    }

    // Typ prüfen und ggf. konvertieren
    if (isset($typeMapping[$key])) {
        switch ($typeMapping[$key]) {
            case 'int':
                if (!is_numeric($value)) {
                    throw new RuntimeException("Ungültiger Integer für '{$key}': '{$value}'");
                } else {
                    $value = (int)$value;
                }
                break;

            case 'DateTimeImmutable':
                try {
                    $value = new DateTimeImmutable($value);
                    // Wert bleibt String, nur Format validieren
                } catch (Exception $e) {
                    throw new RuntimeException("Ungültiges Datum für '{$key}': '{$value}'", 0, $e);
                }
                break;
        }
    }

    // Konstante definieren, falls noch nicht vorhanden
    if (!defined($key)) {
        define($key, $value);
        if($key === 'TIMEZONE'){
            date_default_timezone_set($value);
        }
    } else {
        throw new RuntimeException("Konstante '{$key}' ist bereits definiert.");
    }
}

// ---- CSP: allow embedding only from parent origin derived from FORTY_HOURS_BASE_URL ----
$baseUrl = (string)FORTY_HOURS_BASE_URL;

// parse_url braucht scheme+host, sonst ist es nicht eindeutig
$parts = parse_url($baseUrl);
if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
    throw new RuntimeException(
        "FORTY_HOURS_BASE_URL must be a full URL incl. scheme and host, e.g. https://partner.example.com/path. Got: '{$baseUrl}'"
    );
}

$scheme = strtolower($parts['scheme']);
$host   = $parts['host'];
$port   = isset($parts['port']) ? (int)$parts['port'] : null;

// Build origin = scheme://host[:port] (only include port when non-default)
$origin = $scheme . '://' . $host;
if ($port !== null) {
    $isDefaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
    if (!$isDefaultPort) {
        $origin .= ':' . $port;
    }
}

// NOTE: frame-ancestors accepts only origins, not full URLs (no paths)
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "base-uri 'none'; " .
    "form-action 'self'; " .
    "frame-ancestors 'self' {$origin};"
);
