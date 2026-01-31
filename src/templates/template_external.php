<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= $tpl_page_title ?></title>
        <link rel="stylesheet" href="40hours.css">
        <script language="javascript" type="text/javascript" src="main.js">
			

		</script>
    </head>
    <body>
        <div class="fortyhours-container">
            <div class="fortyhours-card">
                <?= $content ?>
                <div class="fortyhours-actions"><?= $base_link ?></div>
                <div class="fortyhours-footer"><?= FORTY_HOURS_NAME ?> · <?= FORTY_HOURS_ORGANIZER ?></div>
            </div>
        </div>
    </body>
</html>