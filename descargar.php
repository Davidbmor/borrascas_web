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
$carpeta = basename((string)($_GET['carpeta'] ?? ''));
$modelo  = strtoupper(trim((string)($_GET['modelo'] ?? 'M1')));

// Validar nombre: solo caracteres seguros y extensión .pdf
if (!preg_match('/^informe_[A-Z0-9_]+\.pdf$/i', $archivo)) {
    http_response_code(400);
    exit('Nombre de archivo inválido.');
}

// Elegir directorio base según el modelo
$informesBase = $modelo === 'M2'
    ? __DIR__ . '/informes2/'
    : INFORMES_DIR;

// Validar carpeta (DNI / CIF) si se proporciona
if ($carpeta !== '' && !preg_match('/^[0-9A-Z]{7,12}$/i', $carpeta)) {
    http_response_code(400);
    exit('Carpeta inválida.');
}

// Construir ruta y verificar que queda dentro del directorio base (anti path-traversal)
$carpeta  = strtoupper($carpeta);
$dirBase  = $carpeta !== '' ? $informesBase . $carpeta . '/' : $informesBase;
$rutaReal = realpath($dirBase . $archivo);
$baseReal = realpath($informesBase);

if ($rutaReal === false || $baseReal === false
    || !str_starts_with($rutaReal, $baseReal . DIRECTORY_SEPARATOR)) {
    http_response_code(403);
    exit('Acceso denegado.');
}

if (!is_file($rutaReal)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Content-Length: ' . filesize($rutaReal));
header('Cache-Control: private, no-cache');
readfile($rutaReal);
exit;
