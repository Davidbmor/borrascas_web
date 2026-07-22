<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

// Solo accesible desde el panel de administración
if (empty($_SESSION['admin_ok'])) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$archivo = basename((string)($_GET['f'] ?? ''));
$carpeta = basename((string)($_GET['carpeta'] ?? ''));

// Validar nombre del adjunto: prefijo adj_ + hex + extensión conocida
if (!preg_match('/^adj_[a-f0-9]+\.(jpg|jpeg|png|webp|pdf)$/i', $archivo)) {
    http_response_code(400);
    exit('Nombre de archivo inválido.');
}

// Validar carpeta (DNI)
if ($carpeta === '' || !preg_match('/^[0-9]{8}[A-Z]$/i', $carpeta)) {
    http_response_code(400);
    exit('Carpeta inválida.');
}

$carpeta  = strtoupper($carpeta);
$dirAdj   = INFORMES_DIR . $carpeta . '/adjuntos/';
$rutaReal = realpath($dirAdj . $archivo);
$baseReal = realpath(INFORMES_DIR);

// Protección anti path-traversal: la ruta resuelta debe quedar dentro de informes/
if ($rutaReal === false || $baseReal === false
    || !str_starts_with($rutaReal, $baseReal . DIRECTORY_SEPARATOR)) {
    http_response_code(403);
    exit('Acceso denegado.');
}

if (!is_file($rutaReal)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

// Verificar MIME real del archivo (no confiar en la extensión)
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeReal = $finfo->file($rutaReal);
if (!array_key_exists($mimeReal, TIPOS_ADJUNTO)) {
    http_response_code(403);
    exit('Tipo de archivo no permitido.');
}

header('Content-Type: ' . $mimeReal);
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Content-Length: ' . filesize($rutaReal));
header('Cache-Control: private, no-cache');
readfile($rutaReal);
exit;
