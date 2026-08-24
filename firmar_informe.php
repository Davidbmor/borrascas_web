<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

if (empty($_SESSION['admin_ok'])) {
    http_response_code(403);
    exit('Acceso denegado.');
}

function extraerArchivoPdf(string $archivo): string
{
    $archivo = basename($archivo);
    if (!preg_match('/^[a-zA-Z0-9_\-]+\.pdf$/i', $archivo)) {
        throw new RuntimeException('Nombre de archivo inválido.');
    }
    return $archivo;
}

function leerRegistro(): array
{
    if (!file_exists(REGISTRO_JSON)) {
        return [];
    }

    $raw = file_get_contents(REGISTRO_JSON);
    return json_decode($raw, true) ?? [];
}

function guardarRegistro(array $registro): void
{
    file_put_contents(REGISTRO_JSON, json_encode($registro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function buscarInformePorArchivo(array $registro, string $archivo): ?array
{
    foreach ($registro as $idx => $item) {
        if (($item['archivo'] ?? '') === $archivo) {
            return ['indice' => $idx, 'dato' => $item];
        }
    }

    return null;
}

function crearRutaFirmada(string $archivoOriginal): string
{
    return preg_replace('/\.pdf$/i', '_firmado.pdf', $archivoOriginal);
}

function crearImagenTemporalDesdeBase64(string $dataUri): string
{
    $prefijo = 'data:image/png;base64,';
    if (!str_starts_with($dataUri, $prefijo)) {
        throw new RuntimeException('La firma no tiene el formato esperado.');
    }

    $bin = base64_decode(substr($dataUri, strlen($prefijo)), true);
    if ($bin === false) {
        throw new RuntimeException('No se pudo decodificar la firma.');
    }

    $ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'borrascas_firma_' . bin2hex(random_bytes(8)) . '.png';
    file_put_contents($ruta, $bin);
    return $ruta;
}

function firmarPdf(string $originalPdf, string $firmaPng, array $info, string $salidaPdf): void
{
    // El HTML fuente tiene el mismo nombre que el PDF pero con extensión .html
    $htmlSourcePath = preg_replace('/\.pdf$/i', '.html', $originalPdf);

    if (!file_exists($htmlSourcePath)) {
        throw new RuntimeException(
            'No se encontró el fichero HTML fuente del informe. ' .
            'Este informe fue generado antes de la actualización y no se puede firmar desde el panel. ' .
            'Pide al titular que lo vuelva a enviar desde el formulario.'
        );
    }

    $htmlFuente = file_get_contents($htmlSourcePath);
    if ($htmlFuente === false || $htmlFuente === '') {
        throw new RuntimeException('No se pudo leer el HTML fuente del informe.');
    }

    // Insertar la imagen de firma en el hueco ##FIRMA## (misma posición que en el formulario)
    $firmaImgTag  = '<img src="' . $firmaPng . '" style="max-width:220px;max-height:80px;margin:8px 0 4px auto;display:block;" />';
    $htmlConFirma = str_replace('##FIRMA##', $firmaImgTag, $htmlFuente);

    // Etiqueta del modelo según el informe (M1/M2/M3)
    $etiquetasModelo = [
        'M1' => 'Modelo 1 &ndash; Da&ntilde;os en producci&oacute;n ole&iacute;cola',
        'M2' => 'Modelo 2 &ndash; Evaluaci&oacute;n de da&ntilde;os por borrascas',
        'M3' => 'Modelo 3 &ndash; Evaluaci&oacute;n de da&ntilde;os en Esp&aacute;rrago',
    ];
    $modeloId = strtoupper(trim((string)($info['modelo_id'] ?? 'M1')));
    $etiquetaModelo = $etiquetasModelo[$modeloId] ?? $etiquetasModelo['M1'];

    // Recrear cabecera con logo (misma que procesar.php)
    $logoPath = __DIR__ . '/assets/img/Faeca.png';
    $logoHtml = file_exists($logoPath)
        ? '<img src="' . $logoPath . '" style="height:55px;width:auto;" />'
        : '';
    $headerHtml = '
<table style="width:100%;border-bottom:3px solid #2d6a4f;padding-bottom:6px;margin-bottom:0;font-family:DejaVu Sans,Arial,sans-serif;">
  <tr>
    <td style="width:70px;vertical-align:middle;">' . $logoHtml . '</td>
    <td style="vertical-align:middle;padding-left:10px;">
      <div style="font-size:14px;font-weight:bold;color:#1b4332;letter-spacing:.03em;text-transform:uppercase;">INFORME DE DA&Ntilde;OS POR BORRASCA</div>
      <div style="font-size:10px;color:#555;margin-top:2px;">' . $etiquetaModelo . '</div>
    </td>
    <td style="width:80px;text-align:right;vertical-align:top;font-size:9px;color:#888;">' . date('d/m/Y') . '</td>
  </tr>
</table>';

    // Regenerar el PDF con mPDF (mismos ajustes que procesar.php)
    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_top'    => 32,
        'margin_bottom' => 15,
        'margin_left'   => 15,
        'margin_right'  => 15,
        'default_font'  => 'dejavusans',
        'tempDir'       => sys_get_temp_dir(),
        'img_dpi'       => 96,
        'dpi'           => 96,
    ]);
    $mpdf->SetCompression(true);
    $mpdf->SetHTMLHeader($headerHtml);
    $mpdf->SetTitle('Informe Daños Borrasca (Firmado) – ' . htmlspecialchars($info['nombre'] ?? ''));
    $mpdf->SetAuthor('ACGranada');
    $mpdf->WriteHTML($htmlConFirma);
    $mpdf->Output($salidaPdf, \Mpdf\Output\Destination::FILE);
}

$archivo = '';
$error = '';
$infoInforme = null;

try {
    $archivo = extraerArchivoPdf((string)($_GET['f'] ?? ($_POST['archivo'] ?? '')));
    $registro = leerRegistro();
    $coincidencia = buscarInformePorArchivo($registro, $archivo);

    if (!$coincidencia) {
        throw new RuntimeException('No se encontró el informe en el registro.');
    }

    $infoInforme = $coincidencia['dato'];
    if (!empty($infoInforme['firmado'])) {
        throw new RuntimeException('Este informe ya está firmado y no admite una nueva firma.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $firmaData = trim((string)($_POST['firma_data'] ?? ''));
        if ($firmaData === '') {
            throw new RuntimeException('Debes dibujar la firma antes de guardar.');
        }

        // Determinar directorio del informe (subfolder por DNI/CIF/NIE si existe)
        $carpetaInforme = strtoupper(trim((string)($infoInforme['carpeta'] ?? '')));
        if ($carpetaInforme !== '' && preg_match('/^[0-9A-Z]{5,15}$/', $carpetaInforme)) {
            $dirInforme = INFORMES_DIR . $carpetaInforme . '/';
        } else {
            $dirInforme = INFORMES_DIR;
        }

        if (!is_dir($dirInforme)) {
            mkdir($dirInforme, 0755, true);
        }

        $rutaOriginal = $dirInforme . $archivo;
        if (!file_exists($rutaOriginal)) {
            throw new RuntimeException('El PDF original no existe en el servidor.');
        }

        $rutaFirmada = $dirInforme . crearRutaFirmada($archivo);
        if (file_exists($rutaFirmada)) {
            throw new RuntimeException('Ya existe una copia firmada de este informe.');
        }

        $firmaTemporal = crearImagenTemporalDesdeBase64($firmaData);
        try {
            firmarPdf($rutaOriginal, $firmaTemporal, $infoInforme, $rutaFirmada);
        } finally {
            if (file_exists($firmaTemporal)) {
                unlink($firmaTemporal);
            }
        }

        foreach ($registro as &$item) {
            if (($item['archivo'] ?? '') === $archivo) {
                $item['firmado'] = true;
                $item['archivo_firmado'] = basename($rutaFirmada);
                $item['firma_fecha'] = date('Y-m-d H:i:s');
                break;
            }
        }
        unset($item);
        guardarRegistro($registro);

        $_SESSION['firma_ok'] = 'Se ha generado la copia firmada correctamente.';
        header('Location: admin.php');
        exit;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$pageTitle  = 'Firmar informe – Borrascas';
$modelLabel = '';
$backUrl    = 'admin.php';
$assetBase  = '';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-semibold d-flex align-items-center justify-content-between py-3">
                    <span><i class="bi bi-pen-fill me-2 text-success"></i>Firmar informe</span>
                    <a href="admin.php" class="btn btn-sm btn-outline-secondary">Volver</a>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if ($infoInforme): ?>
                    <div class="mb-3 p-3 bg-light rounded">
                        <div><strong><?= (($infoInforme['modelo_id'] ?? 'M1') === 'M1') ? 'Nombre' : 'Nombre / Razón Social' ?>:</strong> <?= htmlspecialchars((string)($infoInforme['nombre'] ?? '')) ?></div>
                        <div><strong><?= (($infoInforme['modelo_id'] ?? 'M1') === 'M1') ? 'DNI' : 'DNI/CIF/NIE' ?>:</strong> <?= htmlspecialchars((string)($infoInforme['dni'] ?? '')) ?></div>
                        <div><strong>Archivo original:</strong> <?= htmlspecialchars($archivo) ?></div>
                        <?php if (!empty($infoInforme['expediente'])): ?>
                        <div><strong>Expediente:</strong> <?= htmlspecialchars((string)$infoInforme['expediente']) ?></div>
                        <?php endif; ?>
                    </div>

                    <p class="text-muted mb-2">Dibuja la firma del socio en el recuadro. Al guardar se creará una copia nueva firmada y el original permanecerá intacto.</p>

                    <form method="POST" autocomplete="off">
                        <input type="hidden" name="archivo" value="<?= htmlspecialchars($archivo) ?>">
                        <input type="hidden" name="firma_data" id="firma_data">
                        <div class="mb-3">
                            <canvas id="firma-canvas" width="800" height="220" style="width:100%; border:2px solid #cfd8d3; border-radius:8px; touch-action:none; background:#fff;"></canvas>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary" id="btn-limpiar"><i class="bi bi-eraser me-1"></i>Limpiar</button>
                            <button type="submit" class="btn btn-success" id="btn-guardar"><i class="bi bi-check2-circle me-1"></i>Guardar copia firmada</button>
                        </div>
                    </form>

                    <?php else: ?>
                        <div class="alert alert-warning">No hay datos para firmar.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($infoInforme): ?>
<script>
(() => {
    const canvas = document.getElementById('firma-canvas');
    const ctx = canvas.getContext('2d');
    const input = document.getElementById('firma_data');
    const limpiar = document.getElementById('btn-limpiar');
    const guardar = document.getElementById('btn-guardar');
    let dibujando = false;
    let xPrev = 0;
    let yPrev = 0;

    function posicion(e) {
        const rect = canvas.getBoundingClientRect();
        const punto = e.touches ? e.touches[0] : e;
        return {
            x: (punto.clientX - rect.left) * (canvas.width / rect.width),
            y: (punto.clientY - rect.top) * (canvas.height / rect.height)
        };
    }

    function iniciar(e) {
        dibujando = true;
        const p = posicion(e);
        xPrev = p.x;
        yPrev = p.y;
    }

    function mover(e) {
        if (!dibujando) return;
        e.preventDefault();
        const p = posicion(e);
        ctx.beginPath();
        ctx.moveTo(xPrev, yPrev);
        ctx.lineTo(p.x, p.y);
        ctx.strokeStyle = '#111';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
        xPrev = p.x;
        yPrev = p.y;
    }

    function parar() {
        dibujando = false;
    }

    function vaciar() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        input.value = '';
    }

    limpiar.addEventListener('click', vaciar);
    canvas.addEventListener('mousedown', iniciar);
    canvas.addEventListener('mousemove', mover);
    canvas.addEventListener('mouseup', parar);
    canvas.addEventListener('mouseleave', parar);
    canvas.addEventListener('touchstart', iniciar, { passive: true });
    canvas.addEventListener('touchmove', mover, { passive: false });
    canvas.addEventListener('touchend', parar);

    document.querySelector('form').addEventListener('submit', (e) => {
        const blank = document.createElement('canvas');
        blank.width = canvas.width;
        blank.height = canvas.height;
        if (canvas.toDataURL() === blank.toDataURL()) {
            e.preventDefault();
            alert('La firma es obligatoria para generar la copia firmada.');
            return;
        }
        input.value = canvas.toDataURL('image/png');
        guardar.disabled = true;
        guardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generando...';
    });
})();
</script>
<?php endif; ?>
</body>
</html>
