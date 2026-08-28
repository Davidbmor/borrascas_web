<?php
@set_time_limit(300);
@ini_set('memory_limit', '256M');

// 1. COMPROBACIÓN DE SEGURIDAD
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($user_agent, 'cron-job.org') === false && strpos($user_agent, 'Google-Apps-Script') === false) {
    header('HTTP/1.0 403 Forbidden');
    die("Acceso denegado.");
}

// 2. CONFIGURACIÓN
$origen = realpath(__DIR__ . '/informes'); 
$fecha = date('Y-m-d_H-i');
$zip_nombre = "backup_informes_$fecha.zip";
$zip_ruta = __DIR__ . '/' . $zip_nombre;


$webhook_url = "https://script.google.com/macros/s/AKfycbwAxmfwz1FgdesVsAJJZWUBbkY0lo0H6n2P55jTOI-k5X580eUIaGDXD4dTi26u5Yk6sA/exec";

if (!$origen || !is_dir($origen)) {
    die("Error: La carpeta /informes no existe.");
}

// 3. CREAR ARCHIVO ZIP
$zip = new ZipArchive();
if ($zip->open($zip_ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($origen, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($origen) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
    $zip->close();
} else {
    die("Error al crear el archivo ZIP.");
}

// 4. GENERAR URL PÚBLICA TEMPORAL PARA QUE GOOGLE DESCARGUE EL ARCHIVO
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$file_url = $protocolo . "://" . $_SERVER['HTTP_HOST'] . "/borrascas/" . $zip_nombre;

$payload = json_encode([
    'fileName' => $zip_nombre,
    'fileUrl' => $file_url
]);

// 5. NOTIFICAR A GOOGLE APPS SCRIPT PARA QUE DESCARGUE EL ARCHIVO
$ch = curl_init($webhook_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response_raw = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 6. LIMPIEZA LOCAL TRAS LA DESCARGA DE GOOGLE
@unlink($zip_ruta);

$response = json_decode($response_raw, true);

if ($http_code == 200 && isset($response['status']) && $response['status'] === 'success') {
    echo "Backup enviado con éxito a Google Drive. ID: " . $response['fileId'];
} else {
    echo "Error al transferir a Google Drive: " . $response_raw;
}
?>