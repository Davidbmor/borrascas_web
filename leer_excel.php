<?php
/**
 * leer_excel.php  –  Vuelca contenido y fórmulas del Excel en pantalla.
 * Uso: pon el Excel en la misma carpeta y abre http://localhost:8080/leer_excel.php
 * (o pasa ?archivo=ruta/al/archivo.xlsx en la URL)
 * BORRA este archivo cuando termines.
 */
require_once __DIR__ . '/vendor/autoload.php';

$archivo = isset($_GET['archivo'])
    ? realpath(__DIR__ . '/' . basename($_GET['archivo']))
    : realpath(__DIR__ . '/data/autorizados.xlsx');

if (!$archivo || !file_exists($archivo)) {
    die('<p style="color:red">Archivo no encontrado. Pon el Excel en la carpeta del proyecto '
        . 'o pasa <code>?archivo=nombre.xlsx</code> en la URL.</p>');
}

// Leer con fórmulas (no solo valores calculados)
$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($archivo);
$reader->setReadDataOnly(false); // false = preserva fórmulas
$spreadsheet = $reader->load($archivo);

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Visor Excel</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 12px; padding: 20px; }
  h2 { color: #2e6b3e; }
  table { border-collapse: collapse; margin-bottom: 30px; width: 100%; }
  th { background: #2e6b3e; color: #fff; padding: 4px 8px; text-align: left; }
  td { border: 1px solid #ccc; padding: 3px 7px; white-space: pre; }
  td.formula { background: #fffde7; color: #b71c1c; font-family: monospace; }
  td.valor   { background: #f1f8e9; }
  .hoja-titulo { font-size: 16px; font-weight: bold; color: #1b5e20;
                 border-bottom: 2px solid #2e6b3e; margin: 20px 0 8px; }
  .coord { color: #999; font-size: 10px; }
</style>
</head>
<body>
<h2>Contenido de: <?= htmlspecialchars(basename($archivo)) ?></h2>

<?php foreach ($spreadsheet->getSheetNames() as $nombreHoja): ?>
    <?php
    $hoja    = $spreadsheet->getSheetByName($nombreHoja);
    $maxFila = $hoja->getHighestDataRow();
    $maxCol  = $hoja->getHighestDataColumn();
    $maxColN = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCol);
    ?>
    <div class="hoja-titulo">Hoja: <?= htmlspecialchars($nombreHoja) ?></div>
    <table>
        <thead>
            <tr>
                <th class="coord">&nbsp;</th>
                <?php for ($c = 1; $c <= $maxColN; $c++): ?>
                    <th><?= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) ?></th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
        <?php for ($fila = 1; $fila <= $maxFila; $fila++): ?>
            <tr>
                <td class="coord"><?= $fila ?></td>
                <?php for ($col = 1; $col <= $maxColN; $col++):
                    $cell    = $hoja->getCellByColumnAndRow($col, $fila);
                    $formula = $cell->getValue();       // fórmula o valor raw
                    $calc    = '';
                    $esForm  = is_string($formula) && str_starts_with($formula, '=');
                    if ($esForm) {
                        try {
                            $calc = $hoja->getCellByColumnAndRow($col, $fila)
                                         ->getCalculatedValue();
                        } catch (\Throwable $e) {
                            $calc = '(error)';
                        }
                    }
                ?>
                    <td class="<?= $esForm ? 'formula' : 'valor' ?>">
                        <?php if ($esForm): ?>
                            <span title="Resultado: <?= htmlspecialchars((string)$calc) ?>">
                                <?= htmlspecialchars((string)$formula) ?>
                            </span>
                            <br><small style="color:#555">→ <?= htmlspecialchars((string)$calc) ?></small>
                        <?php else: ?>
                            <?= htmlspecialchars((string)$formula) ?>
                        <?php endif; ?>
                    </td>
                <?php endfor; ?>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>
<?php endforeach; ?>
</body>
</html>
