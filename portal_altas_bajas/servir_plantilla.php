<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/functions.php';

pa_require_admin();

$slug = (string)($_GET['slug'] ?? '');
if ($slug === '' || !preg_match('/^[a-z0-9_-]+$/i', $slug)) {
    http_response_code(400);
    exit('Slug inválido.');
}

$template = pa_get_template($slug);
if ($template === null || empty($template['pdf_exists'])) {
    http_response_code(404);
    exit('Plantilla no encontrada.');
}

$path = realpath((string)$template['pdf_path']);
$allowed = realpath(TEMPLATES_DIR);

if ($path === false || $allowed === false || strncmp($path, $allowed, strlen($allowed)) !== 0) {
    http_response_code(403);
    exit('Ruta no permitida.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($path);
exit;
