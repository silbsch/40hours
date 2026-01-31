<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Rendert eine komplette HTML-Seite im einheitlichen Layout, unterschieden nach internem (aus iFrame heraus) oder externem (über Link) Aufruf.
 *
 * @param string $template    Filename des Templates (ohne .php)
 * @param bool   $is_external Ob die Seite extern (true) oder intern (false) gerendert werden soll
 * @param int    $status      HTTP-Statuscode der Seite
 * @param array  $data        Daten für das Template
 */
function render(string $template, bool $is_external, int $status = 200, array $data = [])
{
    extract($data, EXTR_SKIP | EXTR_PREFIX_ALL, 'tpl');

    try {
        $ok = ob_start();
    } catch (Throwable $e) {
        error_log("render ob_start EX: " . $e->getMessage());
        http_response_code(500);
        exit;
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
    }

	require __DIR__ . '/../templates/' . $template . '.php';
	$content = ob_get_clean();
	$base_link = $is_external ? get_base_link() : get_application_base_link();
    require __DIR__ . '/../templates/template_'.($is_external ? 'external' : 'internal').'.php';
}

function render_invalid_link()
{
    render('invalid_link', true, 400, ['page_title' => 'Ungültige Anfrage']);
}

function render_invalid_request()
{
    render('invalid_request', false, 400);
}

function render_already_reserved(string $name)
{
    render('already_reserved', false, 200, ['name' => $name]);
}

function render_invalid_email(string $name)
{
    render('invalid_email', false, 200, ['name' => $name]);
}

function render_invalid_date(string $name)
{
    render('invalid_date', false, 200, ['name' => $name]);
}

function render_internal_error(string $action_name)
{
    render('internal_error', true, 500, ['page_title' => 'Technischer Fehler', 'action_name' => $action_name]);
}

function render_not_found()
{
    render('not_found', true, 404, ['page_title' => 'Buchung nicht gefunden']);
}

function render_missing_link()
{
    render('missing_link', true, 400, ['page_title' => 'Buchung nicht gefunden']);
}

function render_success(array $data = [])
{
    render('user/success', false, 200, $data);
}

function render_confirmed_user(array $data = [])
{
    render('user/confirmed_user', false, 200, $data);
}
?>
