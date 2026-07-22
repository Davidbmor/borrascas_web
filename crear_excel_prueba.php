<?php
/**
 * Script de utilidad – genera data/autorizados.xlsx con DNIs de prueba.
 * Ejecútalo UNA vez desde la línea de comandos:
 *   php crear_excel_prueba.php
 * Luego puedes borrarlo.
 */
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$hoja = $spreadsheet->getActiveSheet();
$hoja->setTitle('Autorizados');

// Cabecera
$hoja->fromArray(['Nº', 'Nombre', 'DNI'], null, 'A1');

// DNIs de prueba con letra de control correcta
$datos = [
    [1, 'Juan García López',      '12345678Z'],
    [2, 'María Pérez Ruiz',       '87654321Q'],
    [3, 'Antonio Fernández Mora', '11223344H'],
    [4, 'Carmen Rodríguez Gil',   '99887766R'],
    [5, 'Francisco López Díaz',   '55443322Y'],
];

$fila = 2;
foreach ($datos as $row) {
    $hoja->fromArray($row, null, 'A' . $fila);
    $fila++;
}

// Estilo cabecera
$hoja->getStyle('A1:C1')->getFont()->setBold(true);
foreach (['A', 'B', 'C'] as $col) {
    $hoja->getColumnDimension($col)->setAutoSize(true);
}

$carpeta = __DIR__ . '/data';
if (!is_dir($carpeta)) {
    mkdir($carpeta, 0755, true);
}

$writer = new Xlsx($spreadsheet);
$writer->save($carpeta . '/autorizados.xlsx');

echo "Excel creado en data/autorizados.xlsx" . PHP_EOL;
echo "DNIs de prueba disponibles:" . PHP_EOL;
foreach ($datos as $row) {
    echo "  {$row[2]}  →  {$row[1]}" . PHP_EOL;
}
