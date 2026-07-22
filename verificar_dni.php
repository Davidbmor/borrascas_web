<?php
/**
 * verificar_dni.php
 * Endpoint AJAX – verifica si un DNI está autorizado consultando Google Sheets.
 * Responde con JSON: { "valido": true/false, "nombre": "..." }
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/config.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Leer y sanear el DNI recibido
$dni = strtoupper(trim((string)($_POST['dni'] ?? '')));

// Validación básica del formato DNI español (8 dígitos + 1 letra)
if (!preg_match('/^[0-9]{8}[A-Z]$/', $dni)) {
    echo json_encode(['valido' => false, 'mensaje' => 'Formato de DNI incorrecto']);
    exit;
}

// Verificar letra del DNI (dígito de control)
$letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
$letraCorrecta = $letras[(int)substr($dni, 0, 8) % 23];
if ($letraCorrecta !== substr($dni, -1)) {
    echo json_encode(['valido' => false, 'mensaje' => 'DNI no válido (letra de control incorrecta)']);
    exit;
}

// Cargar el autoloader de Composer
$autoloader = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    http_response_code(500);
    echo json_encode(['error' => 'Dependencias no instaladas.']);
    exit;
}
require_once $autoloader;

// Verificar que el fichero de credenciales existe
if (!file_exists(GSHEETS_CREDENTIALS)) {
    http_response_code(500);
    echo json_encode(['error' => 'Credenciales de Google no disponibles.']);
    exit;
}

try {
    // ── Intento 1: Google Sheets ──────────────────────────
    $resultado = null;

    if (file_exists(GSHEETS_CREDENTIALS)) {
        try {
            $client = new Google\Client();
            $client->setAuthConfig(GSHEETS_CREDENTIALS);
            $client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
            $client->setApplicationName('BorrascasWeb');

            $service  = new Google\Service\Sheets($client);
            $response = $service->spreadsheets_values->get(
                GSHEETS_SPREADSHEET_ID,
                GSHEETS_RANGE
            );
            $filas = $response->getValues();

            if (!empty($filas)) {
                $resultado = ['valido' => false, 'mensaje' => 'DNI no autorizado para generar informes'];
                foreach (array_slice($filas, 1) as $fila) {
                    $celdas = array_map(fn($v) => strtoupper(trim((string)$v)), $fila);
                    if (in_array($dni, $celdas, true)) {
                        $nombre = '';
                        foreach ($celdas as $val) {
                            if (!empty($val) && $val !== $dni && !is_numeric($val) && !preg_match('/^[0-9]{8}[A-Z]$/', $val)) {
                                $nombre = ucwords(strtolower($val));
                                break;
                            }
                        }
                        $resultado = ['valido' => true, 'nombre' => $nombre];
                        break;
                    }
                }
            }
        } catch (\Exception $gsEx) {
            // Google Sheets falló → intentar con Excel
            $resultado = null;
        }
    }

    // ── Intento 2: Excel local (fallback) ─────────────────
    if ($resultado === null && file_exists(EXCEL_PATH)) {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile(EXCEL_PATH);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load(EXCEL_PATH);
            $hoja        = $spreadsheet->getActiveSheet();
            $maxFila     = $hoja->getHighestDataRow();

            $resultado = ['valido' => false, 'mensaje' => 'DNI no autorizado para generar informes'];
            for ($fila = 2; $fila <= $maxFila; $fila++) {
                for ($col = 1; $col <= 10; $col++) {
                    $val = strtoupper(trim((string)$hoja->getCellByColumnAndRow($col, $fila)->getValue()));
                    if ($val === $dni) {
                        // Buscar nombre en la misma fila
                        $nombre = '';
                        for ($c2 = 1; $c2 <= 10; $c2++) {
                            $v2 = trim((string)$hoja->getCellByColumnAndRow($c2, $fila)->getValue());
                            if ($v2 !== '' && strtoupper($v2) !== $dni && !is_numeric($v2) && !preg_match('/^[0-9]{8}[A-Z]$/i', $v2)) {
                                $nombre = ucwords(strtolower($v2));
                                break;
                            }
                        }
                        $resultado = ['valido' => true, 'nombre' => $nombre];
                        break 2;
                    }
                }
            }
        } catch (\Exception $excelEx) {
            $resultado = null;
        }
    }

    // ── Sin fuente disponible: permitir con aviso ─────────
    if ($resultado === null) {
        $resultado = ['valido' => true, 'nombre' => '', 'aviso' => 'Verificación no disponible temporalmente'];
    }

    echo json_encode($resultado);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al consultar la hoja de autorizados: ' . $e->getMessage()]);
}
