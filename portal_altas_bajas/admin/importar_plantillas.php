<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/functions.php';
pa_require_admin();

// Escanea la carpeta plantillas y crea un JSON de definición por cada PDF que no tenga uno
$created = [];
$existing = [];

foreach (glob(TEMPLATES_DIR . '/*.pdf') ?: [] as $pdfPath) {
    $basename = basename($pdfPath, '.pdf');
    $slug = pa_slugify($basename);
    $defPath = DEFINITIONS_DIR . '/' . $slug . '.json';

    if (file_exists($defPath)) {
        $existing[] = $basename;
        continue;
    }

    $def = [
        'slug' => $slug,
        'title' => $basename,
        'description' => 'Rellena y firma el documento ' . $basename . '.',
        'pdf' => basename($pdfPath),
        'person_key' => 'dni',
        'fields' => [],
        'signature' => [],
    ];
    pa_save_json($defPath, $def);
    $created[] = $basename;
}

pa_flash('ok', 'Importadas: ' . (count($created) ?: 'ninguna nueva') . '. Ya existían: ' . (count($existing) ?: 'ninguna') . '. Ahora configura los campos de cada plantilla.');
header('Location: ../admin.php');
exit;
