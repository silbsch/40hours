<h1 class="headline">Gebetszeit-Anmeldung bestätigen</h1>
<p class='fortyhours-info'>Die Reservierung für folgende Anmeldung bestätigen:</p>
<div class='box'>
    <div class='row'><div class='label'>Name</div><div class='value'><?= $tpl_name ?></div></div>
    <div class='row'><div class='label'>Beginn</div><div class='value'><?= $tpl_start ?></div></div>
    <div class='row'><div class='label'>Ende</div><div class='value'><?= $tpl_end ?></div></div>
    <div class='row'><div class='label'>Gemeinsam</div><div class='value'><?= $tpl_public ?></div></div>
    <div class='row'><div class='label'>Titel</div><div class='value'><?= $tpl_title ?></div></div>
</div>
<form method="post">
    <input type="hidden" name="token" value="<?= $tpl_token ?>">
    <input type="hidden" name="csrf_token" value="<?= $tpl_csrf_token ?>">
    <div class="actions">
        <button type="submit" aria-busy="false" id="submitButton">
            <span class="btn-spinner" aria-hidden="true"></span>
            <span class="btn-busy" aria-hidden="true">Bitte warten ...</span>
            <span class="btn-text">Ja, Anmeldung bestätigen</span>
        </button>
    </div>
</form>