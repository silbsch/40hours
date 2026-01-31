<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/database.php';
				
	function get_combobox_html($startdate, $enddate, $events) {
		$comboHours=new DateTime($startdate->format('Y-m-d H:i'));
		$comboHtml ="<select id='fselect' name='fortyhoursdate' required><option value=''>Bitte wähle einen Zeitraum</option>";
		
		while($comboHours < $enddate) {
			$hour = (int)$comboHours->format('H');
			$booked = array_key_exists($comboHours->format('Y-m-d H:i'), $events);
			$disabled = $booked ? "disabled" : "";
			$value = $booked ? "" : $comboHours->format('Y-m-d H:i');
			$text= $booked ? "belegt" : "frei";
			$style = $booked ? "fortyhours-booked" : "fortyhours-free";
			$comboHtml.= sprintf("<option %s class='%s' value='%s'>%s  %02d:00 - %02d:00: %s</option>",$disabled, $style, $value, $comboHours->format('d.m.'),$hour, $hour+1, $text);
			$comboHours->modify('+1 hour');
		}
		return $comboHtml."</select>";	
	}

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
						$html.= "<td class='fortyhours-booked'><small>reserviert</small></td>";
					}
					else if($record["public"]) {
						$html.= "<td class='fortyhours-public'>".sanitize($record["title"])."</td>";
					}
					else {
						$html.= "<td class='fortyhours-booked'>belegt</td>";
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

    $csrf_token = generate_csrf_token();
?>

<div class='main-container'>
	<div class='main-container-left'><?= get_table_html(FORTY_HOURS_START_DATE, FORTY_HOURS_END_DATE, $events) ?></div>
	<div class='main-container-right'>
		<div class="fortyhours-registration">
			<h1 class'headline highlight'>Anmeldung</h1>
			<form action='booking.php' method='POST' id='fortyhours-registration-form'>
				<input type='hidden' name='csrf_token' value='<?= htmlspecialchars($csrf_token) ?>' autocomplete='off'/>
				<table>
					<tr><th><label for='fortyhoursselect'>Zeit:</label></th><td><?=get_combobox_html(FORTY_HOURS_START_DATE, FORTY_HOURS_END_DATE, $events)?></td></tr>
					<tr><th><label for='fname'>Name:</label></th><td><input type='text' id='fname' name='fortyhoursname' value='' autocomplete='name' minlength='3' maxlength='55' required placeholder= 'Bitte gib Deinen Namen an'/></td></tr>
					<tr><th><label for='femail'>E-Mail:</label></th><td><input type='email' id='femail' name='fortyhoursemail' value='' autocomplete="email" minlength='6' maxlength='70' required placeholder= 'Bitte gib Deine E-Mail-Adresse an' /></td></tr>
					<tr><th><label for='fpublic'>Gemeinsam:</label></th><td><label><input type='checkbox' id='fpublic' name='fortyhourspublic' onclick="togglePublic()"/><small>  Setze den Haken, wenn jeder zu dieser Gebetszeit dazu kommen darf. Ohne Haken bleibt die Reservierung nur für Dich.</small></label></td></tr>
					<tr><th><label for='ftitle'>Titel:</label></th><td><input type='text' id='ftitle' name='fortyhourstitle' minlength='3' maxlength='55' autocomplete='off' required disabled placeholder= 'Bitte gib einen Titel an.' /></td></tr>
					<tr><th><label for='fdatenschutz'>Datenschutz:</label></th><td><label><input type='checkbox' id='fdatenschutz' name='fortyhoursdatenschutz' required/><small>  Mit der Nutzung dieses Formulars erkläre ich mich mit der Speicherung und Verarbeitung meiner Daten durch diese Website einverstanden.</small></label></td></tr>
					<tr><td></td><td>
						<button type="submit" aria-busy="false" id="submitButton">
							<span class="btn-spinner" aria-hidden="true"></span>
							<span class="btn-busy" aria-hidden="true">Bitte warten ...</span>
							<span class="btn-text">Absenden</span>
						</button>
					</td></tr>
				</table>
			</form>
		</div>
	</div>
</div>