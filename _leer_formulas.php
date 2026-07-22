<?php
require 'vendor/autoload.php';

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile('data/AyudasLluvias.xlsx');
$reader->setReadDataOnly(false);
$ss = $reader->load('data/AyudasLluvias.xlsx');

echo "Hojas: " . implode(', ', $ss->getSheetNames()) . PHP_EOL;

// Leer TODAS las hojas
foreach ($ss->getSheetNames() as $idx => $nombreHoja) {
    $h       = $ss->getSheet($idx);
    $maxFila = $h->getHighestDataRow();
    $maxCol  = $h->getHighestDataColumn();
    $maxColN = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCol);

    echo PHP_EOL . "========== HOJA: $nombreHoja ==========" . PHP_EOL;

    for ($f = 1; $f <= $maxFila; $f++) {
        for ($c = 1; $c <= $maxColN; $c++) {
            $cell = $h->getCellByColumnAndRow($c, $f);
            $col  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $val  = $cell->getValue();
            if ($val === null || $val === '') continue;

            $calc = '';
            if (is_string($val) && str_starts_with($val, '=')) {
                try {
                    $calc = '  =>  ' . $cell->getCalculatedValue();
                } catch (\Throwable $e) {
                    $calc = '  =>  ERR: ' . $e->getMessage();
                }
            }
            echo $col . $f . ': ' . trim((string)$val) . $calc . PHP_EOL;
        }
    }
}
