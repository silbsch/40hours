<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
?>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <title><?= FORTY_HOURS_NAME ?> bei <?= FORTY_HOURS_ORGANIZER ?></title>
  <meta charset="utf-8" />
  <link rel="stylesheet" href="40hours.css">
	<script language="javascript" type="text/javascript" src="main.js"></script>
</head>

<body>
<?php
require __DIR__ . '/intro.html';
require __DIR__ . '/main.php';
?>
</body>
</html>