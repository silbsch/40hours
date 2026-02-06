<div class='fortyhours-info'>
    <div><div class='fortyhours-name'><?= $tpl_name ?>,</div> Du möchtest Deine Reservierung für das <b><span class='label'><?= FORTY_HOURS_NAME ?></span></b> im Haus der <span><?= FORTY_HOURS_ORGANIZER ?></span> am <b><?= $tpl_startDate ?></b> von <b><?= $tpl_startTime ?></b> bis <b><?= $tpl_endTime ?></b> stornieren?</div>
    <div>Das ist schade - aber danke, dass Du uns kurz Bescheid gibst.</div>
</div>
<form method="post">
    <input type="hidden" name="type" value="u">
    <input type="hidden" name="csrf_token" value="<?= $tpl_csrf_token ?>">
    <div class="actions">
        <button type="submit" aria-busy="false" id="submitButton">
            <span class="btn-spinner" aria-hidden="true"></span>
            <span class="btn-busy" aria-hidden="true">Bitte warten ...</span>
            <span class="btn-text">Ja, Stornierung bestätigen</span>
        </button>
    </div>
</form>