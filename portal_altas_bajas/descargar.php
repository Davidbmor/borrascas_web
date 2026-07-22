<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/functions.php';

pa_require_admin();

$id = trim((string)($_GET['id'] ?? ''));
$tipo = trim((string)($_GET['tipo'] ?? 'pdf'));
$archivo = trim((string)($_GET['archivo'] ?? ''));

if ($id === '') {
    http_response_code(400);
    exit('Falta la referencia del expediente.');
}

$registro = pa_find_registry($id);
if ($registro === null) {
    http_response_code(404);
    exit('No se encontró el expediente.');
}

$folder = realpath((string)($registro['folder'] ?? ''));
if ($folder === false) {
    http_response_code(404);
    exit('La carpeta del expediente no existe.');
}

if ($tipo === 'pdf') {
    $path = $folder . DIRECTORY_SEPARATOR . (string)($registro['pdf'] ?? 'documento_firmado.pdf');
    $downloadName = 'expediente_' . $id . '.pdf';
    $mime = 'application/pdf';
} elseif ($tipo === 'adjunto') {
    $allowed = false;
    foreach ((array)($registro['adjuntos'] ?? []) as $attachment) {
        if ((string)($attachment['archivo'] ?? '') === $archivo) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed) {
        http_response_code(403);
        exit('Adjunto no autorizado.');
    }

    $path = $folder . DIRECTORY_SEPARATOR . 'adjuntos' . DIRECTORY_SEPARATOR . basename($archivo);
    $downloadName = basename((string)($archivo ?: 'archivo'));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = file_exists($path) ? ($finfo->file($path) ?: 'application/octet-stream') : 'application/octet-stream';
} else {
    http_response_code(400);
    exit('Tipo de archivo no válido.');
}

$realPath = realpath($path);
if ($realPath === false || !is_file($realPath) || strncmp($realPath, $folder, strlen($folder)) !== 0) {
    http_response_code(404);
    exit('El archivo no existe.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($realPath));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
readfile($realPath);
exit;
