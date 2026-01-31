<?php

// Überprüfen, ob die .env-Datei existiert
$envFile = __DIR__ . '/.env';

if (!file_exists($envFile)) {
    trigger_error("Die Datei .env wurde nicht gefunden.", E_USER_ERROR);
    exit;
}

// Typzuordnung: Gibt an, welche Keys welchen Datentyp benötigen
$typeMapping = [
    'DATABASE_PORT' => 'int',
    'SMTP_PORT' => 'int',
    'FORTY_HOURS_START_DATE' => 'DateTimeImmutable',
    'FORTY_HOURS_END_DATE' => 'DateTimeImmutable',
];

// .env-Datei einlesen
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    // Kommentare ignorieren
    if (trim($line)[0] === '#') {
        continue;
    }

    // Aufteilen in KEY=VALUE
    if (strpos($line, '=') === false) {
        continue;
    }

    list($key, $value) = explode('=', $line, 2);
    $key = trim($key);
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
                    trigger_error("Ungültiger Wert für Integer-Konstante '{$key}': '{$value}'", E_USER_WARNING);
                } else {
                    $value = (int)$value;
                }
                break;

            case 'DateTimeImmutable':
                try {
                    $value = new DateTimeImmutable($value);
                    // Wert bleibt String, nur Format validieren
                } catch (Exception $e) {
                    trigger_error("Ungültiger Datumsstring für '{$key}': {$e->getMessage()}", E_USER_WARNING);
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
        trigger_error("Konstante '{$key}' ist bereits definiert.", E_USER_NOTICE);
    }

}