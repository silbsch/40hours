<?php
declare(strict_types=1);

/**
 * PDO connection + Repository for the 40hours project.
 *
 * Expected constants (e.g. from 40hours_config.php or similar):
 * - DATABASE_HOST
 * - DATABASE_NAME
 * - DATABASE_USER
 * - DATABASE_PASSWORD
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            DATABASE_HOST,
            DATABASE_NAME
        );

        try {
            self::$pdo = new PDO($dsn, DATABASE_USER, DATABASE_PASSWORD, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements (more predictable + safer)
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            return self::$pdo;
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            throw new RuntimeException('Datenbankverbindung fehlgeschlagen');
        }
    }
}

final class FortyHoursRepository
{
    public function __construct(private PDO $pdo) {}

    public function fetchAllBookings(): array
    {
        // Quote identifiers because both table name and some columns can be problematic otherwise.
        $sql = 'SELECT `start`, `end`, `title`, `public`, `completion_on`
                FROM `40hours`
                ORDER BY `start`';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /**
     * Erstellt eine Buchung, verhindert Doppelbelegung
     *
     * @throws RuntimeException bei Konflikt
     */
    public function createReservation(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $name,
        string $email,
        string $title,
        bool $isPublic,
        string $reservationToken
    ): bool {
        $now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));

        $this->pdo->beginTransaction();

        try {
            // Slot sperren
            $check = $this->pdo->prepare('SELECT id FROM `40hours` WHERE `start` = :start AND `end`   = :end FOR UPDATE');
            $check->execute([
                'start' => $start->format('Y-m-d H:i:s'),
                'end'   => $end->format('Y-m-d H:i:s'),
            ]);

            if ($check->fetch()) {
                // Zeitslot bereits belegt -> kein Einfügen möglich;
                $this->pdo->rollBack();
                return false;
            }
            else {
                // Zeitslot frei -> Einfügen
                $insert = $this->pdo->prepare('INSERT INTO `40hours`(`start`, `end`, `title`, `public`, `name`, `email`, `reservation_token`, `reservation_on`)
                    VALUES(:start, :end, :title, :public, :name, :email, :reservation_token, :reservation_on)'
                );

                $insert->execute([
                    'title' => $title,
                    'public'=> $isPublic ? 1 : 0,
                    'name'  => $name,
                    'email' => $email,
                    'start' => $start->format('Y-m-d H:i:s'),
                    'end'   => $end->format('Y-m-d H:i:s'),
                    'reservation_token' => $reservationToken,
                    'reservation_on' => $now->format('Y-m-d H:i:s'),
                ]);

                $this->pdo->commit();
                return true;
            }
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Buchung anhand Token finden (Storno / Bestätigung)
     */
    public function findByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `40hours` WHERE reservation_token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Buchung bestätigen
     */
    public function completeByToken(string $token): ?array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));

        $this->pdo->beginTransaction();
        try {
            // Slot sperren
            $stmt = $this->pdo->prepare('SELECT * FROM `40hours` WHERE reservation_token = :token LIMIT 1 FOR UPDATE');
            $stmt->execute(['token' => $token]);

            $row = $stmt->fetch();        
            if ($row === null) {
                // keine Reservierung gefunden;
                $this->pdo->rollBack();
                return null;
            }
            else if (empty($row['completion_on'])) {
                // Zeitslot frei -> Einfügen
                $now = (new DateTimeImmutable('now', new DateTimeZone(TIMEZONE)))->format('Y-m-d H:i:s');
                $update = $this->pdo->prepare('UPDATE `40hours` SET `completion_on` = :completion_on WHERE `id` = :id');
                $update->execute([
                    'id' => $row['id'],
                    'completion_on' => $now,
                ]);

                $row['completion_on'] = $now;
                $row['updated'] = true;
                $this->pdo->commit();
                return $row ?: null;
            }
            else {
                // Bereits bestätigt
                $this->pdo->rollBack();
                $row['updated'] = false;
                return $row;    
            }
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Buchung löschen (Stornierung)
     */
    public function deleteByToken(string $token): ?array
    {
        $this->pdo->beginTransaction();
        try {
            // Slot sperren
            $stmt = $this->pdo->prepare('SELECT * FROM `40hours` WHERE reservation_token = :token LIMIT 1 FOR UPDATE');
            $stmt->execute(['token' => $token]);

            $row = $stmt->fetch();        
            if ($row === null) {
                // keine Reservierung gefunden;
                $this->pdo->rollBack();
                return null;
            }
            else {
                // Zeitslot frei -> Einfügen
                $stmt = $this->pdo->prepare('DELETE FROM `40hours` WHERE `id` = :id AND reservation_token = :token');
                $stmt->execute([
                    'id' => $row['id'],
                    'token' => $token,
                ]);

                $this->pdo->commit();
                return $row ?: null;
            }
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
