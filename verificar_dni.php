<?php
/**
 * verificar_dni.php
 * Endpoint AJAX – verifica si un DNI está en los Excel de la carpeta data/.
 * Devuelve 'valido': true siempre para no bloquear envíos, y 'exito': true + 'nombre' si lo encuentra.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

$cfg = __DIR__ . '/config.php';
if (file_exists($cfg)) {
    require_once $cfg;
}

$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

$dniRaw = strtoupper(trim((string)($_REQUEST['dni'] ?? $_POST['dni'] ?? '')));
$dni = preg_replace('/[^A-Z0-9]/', '', $dniRaw);

if (empty($dni)) {
    echo json_encode(['valido' => true, 'exito' => false, 'mensaje' => 'DNI no proporcionado']);
    exit;
}

$rutas = [
    __DIR__ . '/data/autorizados.xlsx',
    __DIR__ . '/data/AyudasLluvias.xlsx'
];

$encontrado = false;
$nombreEncontrado = '';

foreach ($rutas as $rutaExcel) {
    if (!file_exists($rutaExcel)) continue;

    try {
        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($rutaExcel);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($rutaExcel);

            foreach ($spreadsheet->getSheetNames() as $sheetName) {
                $hoja = $spreadsheet->getSheetByName($sheetName);
                $filas = $hoja->toArray();

                foreach ($filas as $fila) {
                    if (empty($fila)) continue;

                    foreach ($fila as $idx => $celda) {
                        if (!$celda) continue;
                        $celdaLimpia = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string)$celda)));

                        if ($celdaLimpia === $dni) {
                            $encontrado = true;
                            foreach ($fila as $cIdx => $cVal) {
                                if ($cIdx !== $idx && !empty($cVal)) {
                                    $cValStr = trim((string)$cVal);
                                    $cValClean = preg_replace('/[^A-Z0-9]/', '', strtoupper($cValStr));
                                    if ($cValClean !== $dni && !is_numeric($cValStr) && strlen($cValStr) > 2) {
                                        $nombreEncontrado = ucwords(mb_strtolower($cValStr, 'UTF-8'));
                                        break;
                                    }
                                }
                            }
                            if ($encontrado) break 3;
                        }
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // Continuar
    }
}

if ($encontrado) {
    echo json_encode([
        'valido' => true,
        'exito'  => true,
        'nombre' => $nombreEncontrado
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'valido'  => true,
        'exito'   => false,
        'mensaje' => 'DNI no encontrado en el listado de autorizados'
    ], JSON_UNESCAPED_UNICODE);
}
