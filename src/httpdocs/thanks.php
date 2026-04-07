<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>40-Stunden-Gebet</title>
		<link rel="stylesheet" href="40hours.css">
	</head>
	<body>
<?php
require_once dirname(__DIR__).'/40hours/bootstrap.php';
require_once dirname(__DIR__).'/40hours/calendar.php';
require_once dirname(__DIR__).'/40hours/database.php';
require_once dirname(__DIR__).'/40hours/helpers.php';
require_once dirname(__DIR__).'/40hours/layout.php';
require_once dirname(__DIR__).'/40hours/mailer.php';
require_once dirname(__DIR__).'/40hours/FortyHoursRepository.php';

    function finish_session($name, $mail) {
        $mailbody ="Hallo ".$name.",<br/>";
            $mailbody.="wie schön, dass Du Teil des 40-Stunden-Gebets im Haus der LKG Hilmersdorf warst.<br/>";
            $mailbody.="Wir hoffen, Du hast diese Zeit als gesegnet erlebt und konntest Gott persönlich begegnen – und wir sind überzeugt: ER ist Dir begegnet.<br/>";
            $mailbody.="Wenn Du möchtest, kannst Du uns gern ein Feedback geben. Wir würden uns sehr darüber freuen.<br/><br/>";
            $mailbody.="Außerdem möchten wir Dich darüber informieren, dass Deine im Rahmen der Anmeldung erhobenen personenbezogenen Daten (Name und E-Mail-Adresse) mit dem Versand dieser E-Mail gelöscht wurden.<br/><br/>";
            $mailbody.="Wir wünschen Dir noch ein schönes Osterfest, denn der Herr ist wahrhaftig auferstanden.<br/><br/>";
            $mailbody.="Das Team der LKG Hilmersdorf";
            $mailsubject ="Schön, dass du dabei warst";
            //send_mail($mail, $name, $mailsubject, $mailbody);
    }

    $now = new DateTime();
    $endDatePlusOneHour = (clone FORTY_HOURS_END_DATE)->modify('+1 hour');

    if ($now <= $endDatePlusOneHour) {
        // Mindestens 1 Stunde nach END_DATE
        echo "Das 40-Stunden-Gebet läuft noch bis zum ".FORTY_HOURS_END_DATE->format('d.m.Y H:i').".<br/>Die Abschluss-E-Mails werden erst danach verschickt.";
        exit;
    }

    $pdo = Database::pdo();
    $stmt = $pdo->prepare("SELECT DISTINCT name, email FROM 40hours WHERE email IS NOT NULL ORDER BY start LIMIT 10 OFFSET 0;");
	$stmt->execute();
	$result = $stmt->fetchAll();

    foreach ($result as $record) {
		$name = $record['name'];
		$mail = $record['email'];
		finish_session($name, $mail);
        echo $name.": ".$mail."<br/>";
	}
    echo "Fertig";

?>
	</body>
</html>