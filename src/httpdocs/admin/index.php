<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/../40hours/bootstrap.php';
require_once dirname(__DIR__).'/../40hours/helpers.php';
require_once dirname(__DIR__).'/../40hours/database.php';
require_once dirname(__DIR__).'/../40hours/FortyHoursRepository.php';
	
	function get_table_html($startdate, $enddate, $events) {
		// Erstelle einen Zeitraum (DatePeriod) für die Tage (Spalten).
		// Wir starten am Datum des Startzeitpunkts und gehen bis einschließlich des Endtages.
		$startDay = new DateTime($startdate->format('Y-m-d'));
		$endDay   = new DateTime($enddate->format('Y-m-d'));
		$endDay->modify('+1 day'); // Damit der Endtag in der DatePeriod enthalten ist
		
		$dateInterval = new DateInterval('P1D');
		$datePeriod   = new DatePeriod($startDay, $dateInterval, $endDay);
		$numDays = $datePeriod->getEndDate()->diff($datePeriod->getStartDate())->days;
		
		// Hier extrahieren wir Start- und Endstunde aus dem Start- bzw. Endzeitpunkt.
		$startHour = (int)$startdate->format('H'); // z.B. 08
		$endHour   = (int)$enddate->format('H');     // z.B. 18
		$startTableHour = $numDays > 0 ? 0 : $startHour;
		$endTableHour = $numDays > 0 ? 24 : $endHour;
		// HTML-Tabelle erzeugen
		$html = "<table>";
		// Tabellenkopf: Erste Spalte für die Stunden-Beschriftung, danach je eine Spalte pro Tag
		$html.= "<tr><th></th>";
		foreach ($datePeriod as $day) {
			$html.= "<th>".get_name_of_the_day($day)."<br/>".$day->format('d.m.Y')."</th>";
		}
		$html.= "</tr>";

		// Tabellenzeilen: Jede Zeile repräsentiert eine Stunde
		// Hier gehen wir von der Startstunde bis (exklusiv) zur Endstunde vor.

		for ($hour = $startTableHour; $hour < $endTableHour; $hour++) {
			// Erste Zelle der Zeile: Anzeige der aktuellen Stunde (z. B. "08:00")
			$html.= "<tr><th>" . sprintf("%02d:00 - %02d:00", $hour, $hour+1) ."</th>";
			
			// Für jede Spalte (Tag) eine Zelle erzeugen
			foreach ($datePeriod as $day) {
				// Erstelle einen Zeitstempel, der das jeweilige Tag und die Stunde repräsentiert
				$cellTime = new DateTime(sprintf('%s %02d:00', $day->format('Y-m-d'), $hour));
				$is_in_range = ($cellTime >= $startdate && $cellTime < $enddate);
				$key = $cellTime->format('Y-m-d H:i');
				
				if (array_key_exists($key, $events)) {
					$record = $events[$key];
					if(empty($record["completion_on"])) {
						$html.= "<td class='fortyhours-booked'><small style='font-style: italic;'>reserviert<br/>".sanitize($record["name"])."</small></td>";
					}
					else if($record["public"]) {
						$html.= "<td class='fortyhours-public'>".sanitize($record["title"])."<br/>".sanitize($record["name"])."</td>";
					}
					else {
						$html.= "<td class='fortyhours-booked'>belegt<br/>".sanitize($record["name"])."</td>";
					}
				}
				else if($is_in_range) {
					$html.= "<td class='fortyhours-free' data-value='".$key."'><small>frei</small></td>";
				}
				else {
					$html.= "<td class='fortyhours-outside'></td>"; // Bereich außerhalb der Zeit
				}
			}
			$html.= "</tr>";
		}
		$html.= "</table>";
		return $html;
	}

	$events = [];

	// Daten per Repository laden (PDO, prepared statements)
	try {
		$repo = new FortyHoursRepository(Database::pdo());
		$rows = $repo->fetchAllBookings();

		foreach ($rows as $record) {
			$sDate = new DateTime((string)$record['start']);
			$record['start'] = $sDate;
			$events[$sDate->format('Y-m-d H:i')] = $record;
		}
	} catch (Throwable $e) {
		error_log('Failed to load 40hours bookings: ' . $e->getMessage());
		// Fail-closed: show no bookings instead of leaking error details to users.
		$events = [];
	}
?>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <title><?= FORTY_HOURS_NAME ?> bei <?= FORTY_HOURS_ORGANIZER ?></title>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style/40hours.css">
</head>

<body>
<div class='main-container'>
	<div class='main-container-left'><?= get_table_html(FORTY_HOURS_START_DATE, FORTY_HOURS_END_DATE, $events) ?></div>
</div>
</body>
</html>