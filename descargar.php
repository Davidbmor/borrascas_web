<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

// Solo accesible si está logueado en el panel
if (empty($_SESSION['admin_ok'])) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$archivo = basename((string)($_GET['f'] ?? ''));
$carpeta = strtoupper(basename((string)($_GET['carpeta'] ?? '')));

// Validar nombre: solo caracteres seguros y extensión .pdf
if (!preg_match('/^[a-zA-Z0-9_\-]+\.pdf$/i', $archivo)) {
    http_response_code(400);
    exit('Nombre de archivo inválido.');
}

// Validar carpeta (DNI / CIF) si se proporciona
if ($carpeta !== '' && !preg_match('/^[0-9A-Z]{7,12}$/i', $carpeta)) {
    http_response_code(400);
    exit('Carpeta inválida.');
}

// Buscar en INFORMES_DIR y en informes2/
$directoriosBase = [
    INFORMES_DIR,
    __DIR__ . '/informes2/'
];

$rutaReal = null;

foreach ($directoriosBase as $baseDir) {
    if (!is_dir($baseDir)) continue;
    $baseReal = realpath($baseDir);
    if ($baseReal === false) continue;

    $targetFile = $carpeta !== '' ? $baseDir . $carpeta . '/' . $archivo : $baseDir . $archivo;
    $rp = realpath($targetFile);

    if ($rp !== false && is_file($rp) && str_starts_with($rp, $baseReal . DIRECTORY_SEPARATOR)) {
        $rutaReal = $rp;
        break;
    }
}

if (!$rutaReal) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Content-Length: ' . filesize($rutaReal));
header('Cache-Control: private, no-cache');
readfile($rutaReal);
exit;
