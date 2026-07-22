<?php
/**
 * procesar.php
 * Recibe el formulario, valida, calcula y genera el PDF con Dompdf.
 */
declare(strict_types=1);
ini_set('memory_limit', '1024M');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

// ──────────────────────────────────────────────────────────────
// HELPERS
// ──────────────────────────────────────────────────────────────
function eur(float $v): string
{
    return number_format($v, 2, ',', '.') . ' €';
}
function kg(float $v): string
{
    return number_format($v, 2, ',', '.') . ' Kg';
}
function redirect(string $msg): never
{
    $_SESSION['form_error'] = $msg;
    header('Location: index.php');
    exit;
}

// ──────────────────────────────────────────────────────────────
// COMPROBACIONES BÁSICAS
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// CSRF
$tokenEnviado = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $tokenEnviado)) {
    redirect('Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
}

// ──────────────────────────────────────────────────────────────
// SANITIZACIÓN DE ENTRADAS
// ──────────────────────────────────────────────────────────────
$dni          = strtoupper(trim((string)($_POST['dni']           ?? '')));
$nombre       = trim((string)($_POST['nombre']       ?? ''));
$calle        = trim((string)($_POST['calle']         ?? ''));
$numero       = trim((string)($_POST['numero']        ?? ''));
$bloque       = trim((string)($_POST['bloque']        ?? ''));
$piso         = trim((string)($_POST['piso']          ?? ''));
$municipio    = trim((string)($_POST['municipio']     ?? ''));
$provincia    = trim((string)($_POST['provincia']     ?? ''));
$codigoPostal = trim((string)($_POST['codigo_postal'] ?? ''));
$telefono     = trim((string)($_POST['telefono']      ?? ''));
$email       = trim((string)($_POST['email']       ?? ''));
$cooperativa = trim((string)($_POST['cooperativa'] ?? ''));
$tipoInforme = (int)($_POST['tipo_informe']        ?? 0);

// Numéricos del modelo 1
$prevInicialKg  = isset($_POST['prev_inicial_kg'])  && $_POST['prev_inicial_kg'] !== ''
                    ? abs((float)$_POST['prev_inicial_kg'])
                    : null;
$prodRealKg     = abs((float)($_POST['prod_real_kg']    ?? 0));
$recoleccionKg  = abs((float)($_POST['recoleccion_kg']  ?? 0));
$variosEur      = abs((float)($_POST['varios_eur']      ?? 0));

// Firma digital (data URI base64 PNG)
$firmaDataUri = trim((string)($_POST['firma_data'] ?? ''));
$firmaInicial = $firmaDataUri !== '' && str_starts_with($firmaDataUri, 'data:image/png;base64,');

// ──────────────────────────────────────────────────────────────
// VALIDACIÓN
// ──────────────────────────────────────────────────────────────
$errores = [];

if (!preg_match('/^[0-9]{8}[A-Z]$/', $dni)) {
    $errores[] = 'DNI con formato incorrecto.';
}
if (empty($nombre))       $errores[] = 'El nombre es obligatorio.';
if (empty($calle))        $errores[] = 'La calle es obligatoria.';
if (empty($numero))       $errores[] = 'El número es obligatorio.';
if (empty($municipio))    $errores[] = 'El municipio es obligatorio.';
if (empty($provincia))    $errores[] = 'La provincia es obligatoria.';
if (!preg_match('/^[0-9]{5}$/', $codigoPostal)) $errores[] = 'El código postal debe tener 5 dígitos.';
if (empty($telefono))     $errores[] = 'El teléfono es obligatorio.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'El correo electrónico no es válido.';
if (empty($cooperativa) || !in_array($cooperativa, COOPERATIVAS, true)) {
    $errores[] = 'Selecciona una cooperativa válida.';
}
if ($tipoInforme !== 1) $errores[] = 'Tipo de informe no válido.';
if ($prodRealKg  <= 0) $errores[] = 'La producción real es obligatoria.';
if ($recoleccionKg <= 0) $errores[] = 'La recolección de aceituna es obligatoria.';

if (!empty($errores)) {
    redirect(implode(' ', $errores));
}

// ──────────────────────────────────────────────────────────────
// VERIFICAR DNI EN EL EXCEL
// ──────────────────────────────────────────────────────────────
$dniAutorizado = false;
if (file_exists(EXCEL_PATH)) {
    try {
        $reader      = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile(EXCEL_PATH);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load(EXCEL_PATH);
        $hoja        = $spreadsheet->getActiveSheet();
        $maxFila     = $hoja->getHighestDataRow();

        for ($fila = 2; $fila <= $maxFila; $fila++) {
            $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                $hoja->getHighestDataColumn()
            );
            for ($col = 1; $col <= $maxCol; $col++) {
                $val = strtoupper(trim((string)$hoja->getCellByColumnAndRow($col, $fila)->getValue()));
                if ($val === $dni) {
                    $dniAutorizado = true;
                    break 2;
                }
            }
        }
    } catch (\Exception $e) {
        redirect('Error al verificar el DNI. Inténtalo más tarde.');
    }
} else {
    redirect('El archivo de personas autorizadas no está disponible. Contacta con el administrador.');
}

if (!$dniAutorizado) {
    redirect('Tu DNI no está en la lista de personas autorizadas para generar este informe.');
}

// ──────────────────────────────────────────────────────────────
// CÁLCULOS MODELO 1
// ──────────────────────────────────────────────────────────────

// Previsión inicial — si está vacía el Excel usa 0 (D14 vacío = 0)
// Excel: G14 = D14*D11, I14 = G14*D12
$prevInicialKgCalc  = $prevInicialKg ?? 0.0;
$prevInicialAceite  = $prevInicialKgCalc * RENDIMIENTO_MEDIO;   // G14
$prevInicialEur     = $prevInicialAceite * PRECIO_KG_AOVE;      // I14

// Producción real
// Excel: G15 = D15*D11, I15 = G15*D12
$prodRealAceite = $prodRealKg * RENDIMIENTO_MEDIO;  // G15
$prodRealEur    = $prodRealAceite * PRECIO_KG_AOVE; // I15

// Pérdidas — Excel: G16=G14-G15, I16=I14-I15
// Siempre se calcula; si no hay previsión, D14=0 → resultado negativo
$perdidasAceiteKg = $prevInicialAceite - $prodRealAceite;  // G16
$perdidasEur      = $prevInicialEur    - $prodRealEur;     // I16

// Recolección
$recoleccionAceite = $recoleccionKg * RENDIMIENTO_MEDIO;

// Calidad de aceite
$calidadAceiteKgAceite = $recoleccionAceite;
$calidadAceiteEur      = $calidadAceiteKgAceite * PRECIO_CALIDAD_ACEITE;

// Sobrecoste recolección
$sobrecosteRecEur = $recoleccionKg * SOBRECOSTE_RECOLECCION;

// Sobrecoste producción
$sobrecosteProdEur = $recoleccionAceite * SOBRECOSTE_PRODUCCION;

// TOTAL
$totalEur = ($perdidasEur ?? 0)
          + $calidadAceiteEur
          + $sobrecosteRecEur
          + $sobrecosteProdEur
          + $variosEur;

// Crear carpeta del usuario (y subcarpeta de imágenes) antes de procesar archivos
$carpetaUsuario      = INFORMES_DIR . $dni . '/';
$carpetaImagenesPerm = $carpetaUsuario . 'imagenes/';
if (!is_dir($carpetaImagenesPerm)) {
    mkdir($carpetaImagenesPerm, 0755, true);
}

// ──────────────────────────────────────────────────────────────
// GESTIÓN DE IMÁGENES SUBIDAS
// ──────────────────────────────────────────────────────────────
$imagenesBase64 = [];

if (!is_dir(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0755, true);
}

if (!empty($_FILES['imagenes']['name'][0])) {
    $archivos = $_FILES['imagenes'];
    $total    = count($archivos['name']);

    if ($total > MAX_IMAGENES) {
        redirect('Se permiten como máximo ' . MAX_IMAGENES . ' imágenes.');
    }

    for ($i = 0; $i < $total; $i++) {
        if ($archivos['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($archivos['size'][$i]  > MAX_TAMANO_IMG) {
            redirect('Una o más imágenes superan el tamaño máximo de 8 MB.');
        }

        // Verificar MIME real (no confiar en la extensión)
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($archivos['tmp_name'][$i]);
        if (!in_array($mimeReal, TIPOS_IMAGEN, true)) {
            redirect('Solo se aceptan imágenes en formato JPG, PNG o WebP.');
        }

        // Guardar en disco temporal redimensionada a máx. 1800px (evita OOM en mPDF)
        $ext     = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mimeReal];
        $tmpRuta = $carpetaImagenesPerm . 'img_' . bin2hex(random_bytes(8)) . '.jpg';

        $srcPath = $archivos['tmp_name'][$i];
        $img = match($mimeReal) {
            'image/jpeg' => @imagecreatefromjpeg($srcPath),
            'image/png'  => @imagecreatefrompng($srcPath),
            'image/webp' => @imagecreatefromwebp($srcPath),
            default      => false,
        };

        if ($img === false) {
            // Si GD falla simplemente mueve el archivo original
            move_uploaded_file($srcPath, $tmpRuta);
        } else {
            $maxDim = 1200;
            $w = imagesx($img);
            $h = imagesy($img);
            if ($w > $maxDim || $h > $maxDim) {
                $ratio  = $w / $h;
                if ($w >= $h) {
                    $nw = $maxDim;
                    $nh = (int)round($maxDim / $ratio);
                } else {
                    $nh = $maxDim;
                    $nw = (int)round($maxDim * $ratio);
                }
                $resized = imagecreatetruecolor($nw, $nh);
                // Fondo blanco para transparencias PNG/WebP
                imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img);
                $img = $resized;
            }
            imagejpeg($img, $tmpRuta, 70);
            imagedestroy($img);
        }

        if (file_exists($tmpRuta)) {
            $imagenesBase64[] = [
                'ruta'   => $tmpRuta,
                'nombre' => 'Imagen ' . ($i + 1),
            ];
        }
    }
}

// ──────────────────────────────────────────────────────────────
// GESTIÓN DE DOCUMENTOS ADJUNTOS
// ──────────────────────────────────────────────────────────────
$adjuntosTmp = [];

if (!empty($_FILES['adjuntos']['name'][0])) {
    $archivosAdj = $_FILES['adjuntos'];
    $totalAdj    = count(array_filter($archivosAdj['name'], fn($n) => $n !== ''));

    if ($totalAdj > MAX_ADJUNTOS) {
        redirect('Se permiten como máximo ' . MAX_ADJUNTOS . ' documentos adjuntos.');
    }

    for ($i = 0; $i < count($archivosAdj['name']); $i++) {
        if ($archivosAdj['error'][$i] !== UPLOAD_ERR_OK || $archivosAdj['name'][$i] === '') {
            continue;
        }
        if ($archivosAdj['size'][$i] > MAX_TAMANO_ADJUNTO) {
            redirect('Uno o más adjuntos superan el tamaño máximo de 8 MB.');
        }

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($archivosAdj['tmp_name'][$i]);
        if (!array_key_exists($mimeReal, TIPOS_ADJUNTO)) {
            redirect('Tipo de archivo no permitido en adjuntos. Solo PDF e imágenes (JPG, PNG, WebP).');
        }

        $ext            = TIPOS_ADJUNTO[$mimeReal];
        $nombreOriginal = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($archivosAdj['name'][$i]));
        $nombreOriginal = substr($nombreOriginal, 0, 100);

        $adjuntosTmp[] = [
            'nombre' => $nombreOriginal,
            'mime'   => $mimeReal,
            'ext'    => $ext,
            'tmp'    => $archivosAdj['tmp_name'][$i],
        ];
    }
}

// ──────────────────────────────────────────────────────────────
// CONSTRUCCIÓN DEL HTML DEL PDF
// ──────────────────────────────────────────────────────────────
$fechaHoy = date('d/m/Y');

// Fila de tabla auxiliar
function fila(string $concepto, ?string $kgAceituna, ?string $kgAceite, ?string $precioKg, ?string $total, string $clase = ''): string
{
    $dash = '<span style="color:#aaa">—</span>';
    return sprintf(
        '<tr class="%s"><td>%s</td><td class="num">%s</td><td class="num">%s</td><td class="num">%s</td><td class="num total-col">%s</td></tr>',
        htmlspecialchars($clase),
        htmlspecialchars($concepto),
        $kgAceituna ?? $dash,
        $kgAceite   ?? $dash,
        $precioKg   ?? $dash,
        $total      ?? $dash
    );
}

$filasCalculo = '';

// Previsión inicial — siempre aparece (0 si no se rellenó, como en el Excel)
$filasCalculo .= fila(
    'Previsión inicial de producción',
    kg($prevInicialKgCalc),
    kg($prevInicialAceite),
    eur(PRECIO_KG_AOVE),
    eur($prevInicialEur)
);

// Producción real
$filasCalculo .= fila(
    'Producción real',
    kg($prodRealKg),
    kg($prodRealAceite),
    eur(PRECIO_KG_AOVE),
    eur($prodRealEur)
);

// Pérdidas en producción — siempre calculada (Excel: I16=I14-I15)
$filasCalculo .= fila(
    'Pérdidas en producción',
    null,
    kg($perdidasAceiteKg),
    null,
    eur($perdidasEur),
    'fila-destacada'
);

// Recolección
$filasCalculo .= fila(
    'Recolección aceituna (desde ' . FECHA_RECOLECCION . ')',
    kg($recoleccionKg),
    kg($recoleccionAceite),
    null,
    null
);

// Calidad de aceite
$filasCalculo .= fila(
    'Calidad de aceite (diferencia precio Virgen Extra / Lampante)',
    null,
    kg($calidadAceiteKgAceite),
    eur(PRECIO_CALIDAD_ACEITE),
    eur($calidadAceiteEur)
);

// Sobrecoste recolección
$filasCalculo .= fila(
    'Sobrecoste de recolección',
    kg($recoleccionKg),
    null,
    eur(SOBRECOSTE_RECOLECCION),
    eur($sobrecosteRecEur)
);

// Sobrecoste producción
$filasCalculo .= fila(
    'Sobrecoste de producción',
    null,
    kg($recoleccionAceite),
    eur(SOBRECOSTE_PRODUCCION),
    eur($sobrecosteProdEur)
);

// Varios
$filasCalculo .= fila(
    'Varios',
    null,
    null,
    null,
    eur($variosEur)
);

// HTML imágenes – masonry real en 2 columnas calculado en PHP.
// Se mide la altura real de cada imagen y se asigna a la columna
// más corta en ese momento, igual que Pinterest/masonry.js.
$htmlImagenes = '';
if (!empty($imagenesBase64)) {

    // Anchura de columna en mm (A4 = 210 mm, márgenes 15+15, hueco entre cols 6 → ~87 mm cada una)
    $colWidthMm  = 87.0;
    $marginBottom = 5.0; // mm entre imágenes

    $colLeft   = [];
    $colRight  = [];
    $altLeft   = 0.0;
    $altRight  = 0.0;

    foreach ($imagenesBase64 as $img) {
        $info = @getimagesize($img['ruta']);
        if ($info && $info[0] > 0) {
            $ratio         = $info[1] / $info[0];   // alto/ancho
            $displayHeight = $colWidthMm * $ratio;
        } else {
            $displayHeight = 60.0; // estimación si falla
        }

        if ($altLeft <= $altRight) {
            $colLeft[]  = $img;
            $altLeft   += $displayHeight + $marginBottom;
        } else {
            $colRight[] = $img;
            $altRight  += $displayHeight + $marginBottom;
        }
    }

    // Función auxiliar para renderizar las imágenes de una columna
    $renderCol = static function (array $imgs): string {
        $html = '';
        foreach ($imgs as $img) {
            $html .=
                '<div style="margin-bottom:5px;text-align:center;">'
                . '<img src="' . htmlspecialchars($img['ruta']) . '" '
                .     'style="max-width:100%;border:1px solid #ccc;border-radius:3px;" />'
                . '</div>';
        }
        return $html;
    };

    $htmlImagenes .= '<pagebreak />';
    $htmlImagenes .= '<h3 style="color:#2e6b3e;border-bottom:2px solid #2e6b3e;'
        . 'padding-bottom:4px;margin-bottom:10px;">'
        . 'Imágenes aportadas como prueba de daños</h3>';

    // Columna izquierda
    $htmlImagenes .=
        '<div style="float:left;width:48%;margin-right:2%;">'
        . $renderCol($colLeft)
        . '</div>';

    // Columna derecha
    $htmlImagenes .=
        '<div style="float:left;width:48%;margin-left:2%;">'
        . $renderCol($colRight)
        . '</div>';

    $htmlImagenes .= '<div style="clear:both;"></div>';
}

$logoPath = __DIR__ . '/../assets/img/Faeca.png';
$logoImgPath = '';
if (file_exists($logoPath)) {
    // Convertir logo PNG a JPEG temporal para reducir peso en el PDF
    $logoSrc = @imagecreatefrompng($logoPath);
    if ($logoSrc) {
        // Fondo blanco (PNG puede tener transparencia)
        $lw = imagesx($logoSrc);
        $lh = imagesy($logoSrc);
        // Redimensionar si es muy grande (máx 400px ancho)
        if ($lw > 400) {
            $nlw = 400;
            $nlh = (int)round($lh * 400 / $lw);
            $logoResized = imagecreatetruecolor($nlw, $nlh);
            imagefill($logoResized, 0, 0, imagecolorallocate($logoResized, 255, 255, 255));
            imagecopyresampled($logoResized, $logoSrc, 0, 0, 0, 0, $nlw, $nlh, $lw, $lh);
            imagedestroy($logoSrc);
            $logoSrc = $logoResized;
        } else {
            $bg = imagecreatetruecolor($lw, $lh);
            imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
            imagecopy($bg, $logoSrc, 0, 0, 0, 0, $lw, $lh);
            imagedestroy($logoSrc);
            $logoSrc = $bg;
        }
        $logoImgPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'borrascas_logo_' . bin2hex(random_bytes(4)) . '.jpg';
        imagejpeg($logoSrc, $logoImgPath, 85);
        imagedestroy($logoSrc);
    }
}
$logoHtml = $logoImgPath
    ? '<img src="' . $logoImgPath . '" style="height:55px;width:auto;" />'
    : '';

// Guardar firma en archivo temporal PNG
$firmaImgPath = null;
if (!empty($firmaDataUri)) {
    $firmaBase64  = substr($firmaDataUri, strlen('data:image/png;base64,'));
    $firmaDecoded = base64_decode($firmaBase64, true);
    if ($firmaDecoded !== false) {
        $firmaImgPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'borrascas_firma_' . bin2hex(random_bytes(4)) . '.png';
        file_put_contents($firmaImgPath, $firmaDecoded);
    }
}

// ──────────────────────────────────────────────────────────────
// CABECERA REPETIDA EN TODAS LAS PÁGINAS (mPDF SetHTMLHeader)
// ──────────────────────────────────────────────────────────────
$headerHtml = '
<table style="width:100%;border-bottom:3px solid #2d6a4f;padding-bottom:6px;margin-bottom:0;font-family:DejaVu Sans,Arial,sans-serif;">
  <tr>
    <td style="width:70px;vertical-align:middle;">' . $logoHtml . '</td>
    <td style="vertical-align:middle;padding-left:10px;">
      <div style="font-size:14px;font-weight:bold;color:#1b4332;letter-spacing:.03em;text-transform:uppercase;">INFORME DE DA&Ntilde;OS POR BORRASCA</div>
      <div style="font-size:10px;color:#555;margin-top:2px;">Modelo 1 &ndash; Da&ntilde;os en producci&oacute;n ole&iacute;cola &middot; ' . htmlspecialchars(TITULO_CAMPANA) . '</div>
    </td>
    <td style="width:80px;text-align:right;vertical-align:top;font-size:9px;color:#888;">' . $fechaHoy . '</td>
  </tr>
</table>';

$htmlPDF = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; }
  body {
    font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
    font-size: 11px;
    color: #2c2c2c;
    margin: 0;
    padding: 0;
  }

  /* ── Cabecera con logo ─────────────────────────────────── */
  .cabecera {
    border-bottom: 3px solid #2d6a4f;
    padding-bottom: 8px;
    margin-bottom: 14px;
    overflow: hidden;
  }
  .cabecera-logo {
    float: left;
    margin-right: 14px;
    margin-top: 6px;
    margin-bottom: 6px;
  }
  .cabecera-texto {
    overflow: hidden;
  }
  .cabecera h1 {
    font-size: 17px;
    color: #1b4332;
    margin: 4px 0 2px 0;
    letter-spacing: .03em;
  }
  .cabecera h2 {
    font-size: 12px;
    color: #555;
    margin: 0;
    font-weight: normal;
  }
  .fecha {
    font-size: 10px;
    color: #888;
    float: right;
    margin-top: 6px;
  }

  /* ── Títulos de sección ────────────────────────────────── */
  .seccion-titulo {
    background: none;
    color: #1a1a1a;
    border-bottom: 2px solid #1a1a1a;
    padding: 3px 0;
    font-size: 12px;
    font-weight: bold;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin: 15px 0 5px 0;
  }

  /* ── Tablas generales ──────────────────────────────────── */
  table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 5px;
    font-size: 11px;
  }
  td, th {
    border: none;
    border-bottom: 1px solid #d0d0d0;
    padding: 5px 8px;
    vertical-align: middle;
  }
  thead tr th {
    border-bottom: 2px solid #333;
    border-top: 1px solid #333;
    background: none;
    color: #1a1a1a;
    font-weight: bold;
    font-size: 10px;
    letter-spacing: .04em;
    text-transform: uppercase;
  }
  tbody tr:last-child td {
    border-bottom: 1px solid #aaa;
  }
  /* Filas alternas muy sutiles solo en tablas de datos */
  .tabla-alt tr:nth-child(even) td { background-color: #f7f7f7; }
  .tabla-alt tr:nth-child(odd)  td { background-color: #ffffff; }

  /* ── Tabla de cálculos ─────────────────────────────────── */
  .tabla-calc thead th {
    background: none;
    color: #1a1a1a;
    text-align: right;
    border-bottom: 2px solid #333;
    border-top: 1px solid #333;
  }
  .tabla-calc thead th:first-child { text-align: left; }
  .tabla-calc tbody td {
    border-bottom: 1px solid #e0e0e0;
  }
  .num { text-align: right; white-space: nowrap; }

  .fila-destacada td {
    background-color: #f9f9f9 !important;
    font-weight: bold;
    font-style: italic;
    border-top: 1px dashed #bbb !important;
    border-bottom: 1px dashed #bbb !important;
    color: #333;
  }
  .total-col { font-weight: bold; }
  .fila-total td {
    background: #ffffff !important;
    font-weight: bold;
    font-size: 10.5px;
    border-top: 2px solid #333 !important;
    border-bottom: 2px solid #333 !important;
    color: #000;
  }

  /* ── Introducción del informe ─────────────────────────── */
  .intro-bloque {
    margin-bottom: 14px;
    border-top: 2px solid #333;
    border-bottom: 1px solid #aaa;
    padding: 10px 0 12px 0;
    page-break-after: always;
  }
  .intro-titulo-principal {
    font-size: 13px;
    font-weight: bold;
    color: #111;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: .05em;
    margin: 0 0 4px 0;
    line-height: 1.4;
  }
  .intro-subtitulo {
    font-size: 10.5px;
    color: #555;
    text-align: center;
    margin: 0 0 12px 0;
    border-bottom: 1px solid #ccc;
    padding-bottom: 8px;
  }
  .intro-seccion {
    font-size: 11px;
    font-weight: bold;
    color: #111;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin: 10px 0 3px 0;
    border-bottom: 1px solid #bbb;
    padding-bottom: 2px;
  }
  .intro-parrafo {
    font-size: 10.5px;
    color: #222;
    margin: 0 0 5px 0;
    line-height: 1.5;
    text-align: justify;
  }
  .intro-lista {
    margin: 2px 0 5px 18px;
    padding: 0;
    font-size: 10.5px;
    color: #222;
    line-height: 1.5;
  }
  .intro-tabla {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
    margin: 4px 0 6px 0;
  }
  .intro-tabla th {
    background: #ebebeb;
    color: #111;
    font-weight: bold;
    padding: 4px 6px;
    border: 1px solid #bbb;
    text-align: left;
  }
  .intro-tabla td {
    padding: 3px 6px;
    border: 1px solid #ccc;
    vertical-align: top;
    color: #222;
  }
  .intro-tabla tr:nth-child(even) td { background: #f7f7f7; }
  .intro-tabla .num { text-align: right; }
  .intro-tabla tfoot td {
    font-weight: bold;
    background: #e0e0e0;
    border-top: 2px solid #999;
    color: #111;
  }
  .intro-nota {
    font-size: 9.5px;
    color: #777;
    font-style: italic;
    margin: 3px 0 0 0;
  }

  /* ── Bloque firma ──────────────────────────────────────── */
  .firma-bloque {
    margin-top: 28px;
    text-align: right;
    font-size: 11px;
    color: #555;
  }
  .firma-linea {
    border-top: 1px solid #888;
    width: 200px;
    margin: 28px 0 4px auto;
  }
</style>
</head>
<body>

<!-- INTRODUCCIÓN DEL INFORME -->
<div class="intro-bloque">

  <p class="intro-titulo-principal">INFORME T&Eacute;CNICO: EVALUACI&Oacute;N PRELIMINAR DE DA&Ntilde;OS<br>ocasionados por las recientes borrascas en las comarcas agrarias de Baza, Hu&eacute;scar, Guadix,<br>Montes Orientales, Alpujarra y Valle de Lecr&iacute;n (Provincia de Granada)</p>
  <p class="intro-subtitulo">Dirigido al Excmo. Sr. D. Jos&eacute; Antonio Montilla, Subdelegado del Gobierno de Espa&ntilde;a en Granada &middot; Marzo 2026 &middot; Cooperativas Agro-alimentarias de Granada</p>

  <p class="intro-seccion">1. Objeto del informe</p>
  <p class="intro-parrafo">El objeto del presente informe es cuantificar, con una base objetiva y t&eacute;cnica, los da&ntilde;os ocasionados por las borrascas de enero y febrero en las comarcas agrarias de Baza, Hu&eacute;scar, Guadix, Montes Orientales, Alpujarra y Valle de Lecr&iacute;n, pertenecientes a la provincia de Granada.</p>

  <p class="intro-seccion">2. Introducci&oacute;n y contexto meteorol&oacute;gico</p>
  <p class="intro-parrafo">Durante las &uacute;ltimas semanas se han producido diversos episodios meteorol&oacute;gicos adversos en la provincia de Granada, caracterizados por precipitaciones intensas y persistentes, acompa&ntilde;adas en algunos momentos de fuertes rachas de viento. Estos fen&oacute;menos han provocado incidencias significativas sobre la actividad agraria en diferentes zonas de la provincia, especialmente en aqu&eacute;llas &aacute;reas con mayor presencia de cultivos le&ntilde;osos y superficies agr&iacute;colas situadas en zonas de pendiente o pr&oacute;ximas a cauces y ramblas.</p>
  <p class="intro-parrafo">El presente informe tiene como objetivo realizar una evaluaci&oacute;n preliminar del impacto agrario ocasionado por estos temporales en diversas comarcas agr&iacute;colas de la provincia, aportando una estimaci&oacute;n del alcance territorial de la actividad agraria y una aproximaci&oacute;n inicial al impacto econ&oacute;mico derivado de los da&ntilde;os.</p>

  <p class="intro-seccion">3. Metodolog&iacute;a y fuentes de informaci&oacute;n</p>
  <p class="intro-parrafo">Para la elaboraci&oacute;n del presente informe se han utilizado diversas fuentes de informaci&oacute;n agraria sectorial e institucional:</p>
  <ul class="intro-lista">
    <li>Estad&iacute;sticas Agrarias Municipales de la Consejer&iacute;a de Agricultura de la Junta de Andaluc&iacute;a.</li>
    <li>Datos del Ministerio de Agricultura, Pesca y Alimentaci&oacute;n.</li>
    <li>Informaci&oacute;n de producci&oacute;n ole&iacute;cola de la Agencia de Informaci&oacute;n y Control Alimentarios (AICA).</li>
    <li>Informaci&oacute;n sectorial procedente de 34 cooperativas agrarias implantadas en las comarcas objeto del estudio.</li>
  </ul>
  <p class="intro-parrafo">A partir de estos datos se han realizado estimaciones relativas al n&uacute;mero total de agricultores y ganaderos existentes en el territorio, la renta media aproximada de los productores agrarios y el impacto econ&oacute;mico estimado derivado de los da&ntilde;os ocasionados por los temporales.</p>

  <p class="intro-seccion">4. Caracterizaci&oacute;n agraria del territorio</p>
  <p class="intro-parrafo">Las comarcas objeto de estudio presentan una estructura agraria dominada por cultivos le&ntilde;osos, principalmente olivar y almendro. La superficie agraria existente asciende aproximadamente a <strong>277.182 hect&aacute;reas</strong>:</p>
  <table class="intro-tabla">
    <thead><tr><th>Comarca</th><th class="num">Herb&aacute;ceos (ha)</th><th class="num">Le&ntilde;osos (ha)</th><th class="num">Total (ha)</th></tr></thead>
    <tbody>
      <tr><td>Baza</td><td class="num">12.759</td><td class="num">44.619</td><td class="num">57.378</td></tr>
      <tr><td>Hu&eacute;scar</td><td class="num">30.454</td><td class="num">30.764</td><td class="num">61.218</td></tr>
      <tr><td>Guadix</td><td class="num">12.512</td><td class="num">34.008</td><td class="num">46.520</td></tr>
      <tr><td>Montes Orientales</td><td class="num">22.197</td><td class="num">54.702</td><td class="num">76.899</td></tr>
      <tr><td>Alpujarra</td><td class="num">1.433</td><td class="num">22.256</td><td class="num">23.689</td></tr>
      <tr><td>Valle de Lecr&iacute;n</td><td class="num">1.026</td><td class="num">10.452</td><td class="num">11.478</td></tr>
    </tbody>
    <tfoot><tr><td><strong>TOTAL</strong></td><td class="num">80.381</td><td class="num">196.801</td><td class="num">277.182</td></tr></tfoot>
  </table>
  <p class="intro-nota">Fuente: Consejer&iacute;a de Agricultura, Pesca, Agua y Desarrollo Rural de la Junta de Andaluc&iacute;a.</p>

  <p class="intro-seccion">5. Estimaci&oacute;n de productores y renta agraria</p>
  <p class="intro-parrafo">Las 34 cooperativas consideradas agrupan un total de <strong>17.625 socios</strong>, representando aproximadamente el 75% de los productores agrarios de la zona. Se estima mediante extrapolaci&oacute;n que el n&uacute;mero total de agricultores y ganaderos asciende a <strong>23.500</strong>. La facturaci&oacute;n agregada de las cooperativas es de <strong>221.970.833 &euro;</strong> (ejercicio 2024), lo que arroja una renta media estimada por agricultor de <strong>12.594 &euro;/a&ntilde;o</strong>.</p>

  <p class="intro-seccion">6. Diagn&oacute;stico general de da&ntilde;os y mecanismos de afecci&oacute;n</p>
  <p class="intro-parrafo">Se ha estimado un impacto medio aproximado del <strong>40%</strong> sobre la producci&oacute;n agraria potencial. Las lluvias persistentes han generado: encharcamientos prolongados, arrastres de suelo, da&ntilde;os en caminos rurales, p&eacute;rdidas en cultivos herb&aacute;ceos, ca&iacute;da de fruto y afecci&oacute;n radicular en cultivos le&ntilde;osos, p&eacute;rdida de calidad del fruto y sobrecostes operativos en cooperativas. El impacto econ&oacute;mico estimado en el entorno cooperativo es de <strong>87.750.000 &euro;</strong>, y extrapolado al conjunto de comarcas asciende a <strong>117.000.000 &euro;</strong>.</p>

  <p class="intro-seccion">7. Evaluaci&oacute;n de da&ntilde;os en el cultivo del olivar &mdash; Impacto total: 106.925.837 &euro;</p>
  <p class="intro-parrafo">El olivar concentra <strong>84.579 ha</strong> en las comarcas analizadas (41,5% del olivar provincial de Granada &mdash; 203.633 ha). Los da&ntilde;os se desglosan en:</p>
  <table class="intro-tabla">
    <thead><tr><th>Concepto</th><th class="num">Impacto (&euro;)</th></tr></thead>
    <tbody>
      <tr><td>7.1 P&eacute;rdida de producci&oacute;n de aceite (10.790 Tm a 3,82 &euro;/kg)</td><td class="num">41.263.640</td></tr>
      <tr><td>7.2 P&eacute;rdida de calidad del aceite (depreci&oacute;n a lampante, 1,50 &euro;/kg)</td><td class="num">30.283.500</td></tr>
      <tr><td>7.3 Incremento de costes de recolecci&oacute;n (sobrecoste 1,45 &euro;/kg)</td><td class="num">29.273.050</td></tr>
      <tr><td>7.4 Sobrecoste en almazaras (campa&ntilde;a 7 meses vs. 4 normales)</td><td class="num">6.105.647</td></tr>
    </tbody>
    <tfoot><tr><td><strong>TOTAL OLIVAR</strong></td><td class="num">106.925.837</td></tr></tfoot>
  </table>

  <p class="intro-seccion">8. Evaluaci&oacute;n de da&ntilde;os en cultivos herb&aacute;ceos &mdash; Impacto total: 10.401.450 &euro;</p>
  <table class="intro-tabla">
    <thead><tr><th>Concepto</th><th class="num">Impacto (&euro;)</th></tr></thead>
    <tbody>
      <tr><td>8.1 Retraso o imposibilidad de siembra (20% de 57.787 ha a 600 &euro;/ha)</td><td class="num">6.934.200</td></tr>
      <tr><td>8.2 P&eacute;rdida de rendimiento en parcelas sembradas (50% afectadas, merma 25%)</td><td class="num">3.467.250</td></tr>
    </tbody>
    <tfoot><tr><td><strong>TOTAL HERB&Aacute;CEOS</strong></td><td class="num">10.401.450</td></tr></tfoot>
  </table>

  <p class="intro-seccion">9. Conclusiones generales del impacto econ&oacute;mico</p>
  <p class="intro-parrafo">El impacto econ&oacute;mico agrario total derivado de estos temporales se cuantifica de forma preliminar en <strong>117.327.287 &euro;</strong>:</p>
  <ul class="intro-lista">
    <li>P&eacute;rdidas totales en el sector del olivar: <strong>106.925.837 &euro;</strong></li>
    <li>P&eacute;rdidas totales en el sector herb&aacute;ceo: <strong>10.401.450 &euro;</strong></li>
  </ul>
  <p class="intro-parrafo">Este balance global afecta potencialmente a <strong>23.500 agricultores y ganaderos</strong> de la provincia de Granada, con un impacto medio aproximado de <strong>5.000 &euro; por explotaci&oacute;n</strong>, lo que compromete gravemente la viabilidad econ&oacute;mica del tejido productivo rural expuesto.</p>

</div>

<!-- DATOS SOLICITANTE -->
<div class="seccion-titulo">DATOS DEL SOLICITANTE</div>
<table class="tabla-alt">
  <tr>
    <th style="width:18%">Nombre</th>
    <td style="width:32%">{NOMBRE}</td>
    <th style="width:10%">DNI</th>
    <td>{DNI}</td>
  </tr>
  <tr>
    <th style="width:18%">Calle / V&iacute;a</th>
    <td style="width:42%">{CALLE}</td>
    <th style="width:12%">N&uacute;mero</th>
    <td>{NUMERO}</td>
  </tr>
  <tr>
    <th>Bloque / Portal</th>
    <td>{BLOQUE}</td>
    <th>Piso / Puerta</th>
    <td>{PISO}</td>
  </tr>
  <tr>
    <th>Municipio</th>
    <td>{MUNICIPIO}</td>
    <th>C&oacute;d. Postal</th>
    <td>{CP}</td>
  </tr>
  <tr>
    <th>Provincia</th>
    <td colspan="3">{PROVINCIA}</td>
  </tr>
  <tr>
    <th>Tel&eacute;fono</th>
    <td>{TELEFONO}</td>
    <th>Email</th>
    <td>{EMAIL}</td>
  </tr>
  <tr>
    <th>Cooperativa</th>
    <td colspan="3">{COOPERATIVA}</td>
  </tr>
</table>

<!-- DATOS DE CAMPAÑA -->
<div class="seccion-titulo">DATOS DE CAMPA&Ntilde;A &ndash; GRANADA</div>
<table class="tabla-alt">
  <tr>
    <th style="width:65%">Previsi&oacute;n inicial de campa&ntilde;a en Granada</th>
    <td>{PREVISION_GRANADA} Tm</td>
  </tr>
  <tr>
    <th>Previsi&oacute;n de cierre en Granada</th>
    <td>{CIERRE_GRANADA} Tm</td>
  </tr>
  <tr>
    <th>Bajada en porcentaje</th>
    <td>{BAJADA}%</td>
  </tr>
  <tr>
    <th>Rendimiento medio</th>
    <td>{RENDIMIENTO}%</td>
  </tr>
  <tr>
    <th>Precio actual del Kg. AOVE</th>
    <td>{PRECIO_AOVE} &euro;/Kg</td>
  </tr>
</table>

<!-- CÁLCULO DE DAÑOS -->
<div class="seccion-titulo">C&Aacute;LCULO DE DA&Ntilde;OS &ndash; MODELO 1</div>
<table class="tabla-calc">
  <thead>
    <tr>
      <th style="width:38%;text-align:left">Concepto</th>
      <th class="num" style="width:14%">Kgs. Aceituna</th>
      <th class="num" style="width:14%">Kgs. Aceite</th>
      <th class="num" style="width:12%">&euro;/Kg</th>
      <th class="num" style="width:14%">Total (&euro;)</th>
    </tr>
  </thead>
  <tbody>
    {FILAS_CALCULO}
  </tbody>
  <tfoot>
    <tr class="fila-total">
      <td colspan="4">TOTAL DA&Ntilde;OS ESTIMADOS</td>
      <td class="num">{TOTAL_EUR}</td>
    </tr>
  </tfoot>
</table>

<!-- FIRMA -->
<div class="firma-bloque">
  <p>Firmado en Granada, a {FECHA}</p>
  {FIRMA_IMG}
  <p>{NOMBRE}<br>{DNI}</p>
</div>

{HTML_IMAGENES}

</body>
</html>
HTML;

// Reemplazos de variables en la plantilla
$reemplazos = [
    '{TITULO_CAMPANA}'   => htmlspecialchars(TITULO_CAMPANA),
    '{NOMBRE}'           => htmlspecialchars($nombre),
    '{DNI}'              => htmlspecialchars($dni),
    '{CALLE}'            => htmlspecialchars($calle),
    '{NUMERO}'           => htmlspecialchars($numero),
    '{BLOQUE}'           => htmlspecialchars($bloque ?: '—'),
    '{PISO}'             => htmlspecialchars($piso   ?: '—'),
    '{MUNICIPIO}'        => htmlspecialchars($municipio),
    '{CP}'               => htmlspecialchars($codigoPostal),
    '{PROVINCIA}'        => htmlspecialchars($provincia),
    '{TELEFONO}'         => htmlspecialchars($telefono),
    '{EMAIL}'            => htmlspecialchars($email),
    '{COOPERATIVA}'      => htmlspecialchars($cooperativa),
    '{PREVISION_GRANADA}'=> number_format(PREVISION_GRANADA_TM, 0, ',', '.'),
    '{CIERRE_GRANADA}'   => number_format(CIERRE_GRANADA_TM, 0, ',', '.'),
    '{BAJADA}'           => BAJADA_PORCENTAJE,
    '{RENDIMIENTO}'      => round(RENDIMIENTO_MEDIO * 100, 2),
    '{PRECIO_AOVE}'      => number_format(PRECIO_KG_AOVE, 2, ',', '.'),
    '{FILAS_CALCULO}'    => $filasCalculo,
    '{TOTAL_EUR}'        => eur($totalEur),
    '{FECHA}'            => $fechaHoy,
    '{FIRMA_IMG}'        => $firmaImgPath
        ? '<img src="' . $firmaImgPath . '" style="max-width:220px;max-height:80px;margin:8px 0 4px auto;display:block;" />'
        : '<div class="firma-linea"></div>',
    '{HTML_IMAGENES}'    => '',   // las imágenes se escriben en un WriteHTML() separado
];

// HTML fuente con marcador ##FIRMA## e imágenes inline → se guarda en disco
// para poder añadir la firma posteriormente desde el panel de admin.
$reemplazosSource = $reemplazos;
$reemplazosSource['{FIRMA_IMG}']     = '##FIRMA##';
$reemplazosSource['{HTML_IMAGENES}'] = $htmlImagenes;
$htmlFuente = str_replace(array_keys($reemplazosSource), array_values($reemplazosSource), $htmlPDF);

$htmlPDF = str_replace(array_keys($reemplazos), array_values($reemplazos), $htmlPDF);

// ──────────────────────────────────────────────────────────────
// GENERAR PDF CON mPDF (soporta float, columnas CSS, paginación)
// ──────────────────────────────────────────────────────────────
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

$mpdf->SetTitle('Informe Daños Borrasca – ' . $nombre);
$mpdf->SetAuthor('ACGranada');

// Escribir el informe principal (sin imágenes → HTML pequeño)
$mpdf->WriteHTML($htmlPDF);

// Las imágenes ya están referenciadas por ruta local: un solo WriteHTML() basta.
// El HTML de imágenes es solo texto (rutas), sin base64 → no supera pcre.backtrack_limit.
if (!empty($imagenesBase64)) {
    $mpdf->WriteHTML($htmlImagenes);
}

// Nombre del archivo PDF
$nombrePDF      = 'informe_' . $dni . '_' . date('Ymd_His') . '.pdf';

// Carpeta del usuario (una por DNI) dentro de informes/
$carpetaUsuario = INFORMES_DIR . $dni . '/';
if (!is_dir($carpetaUsuario)) {
    mkdir($carpetaUsuario, 0755, true);
}

$rutaPDFServidor = $carpetaUsuario . $nombrePDF;

// Guardar PDF en servidor (carpeta protegida)
$mpdf->Output($rutaPDFServidor, \Mpdf\Output\Destination::FILE);

// Guardar HTML fuente (para firma posterior desde el panel de admin)
$nombreHtmlFuente = pathinfo($nombrePDF, PATHINFO_FILENAME) . '.html';
file_put_contents($carpetaUsuario . $nombreHtmlFuente, $htmlFuente, LOCK_EX);

// Guardar documentos adjuntos en la carpeta del usuario
$adjuntosGuardados = [];
if (!empty($adjuntosTmp)) {
    $carpetaAdj = $carpetaUsuario . 'adjuntos/';
    if (!is_dir($carpetaAdj)) {
        mkdir($carpetaAdj, 0755, true);
    }
    foreach ($adjuntosTmp as $adj) {
        $nombreServidor = 'adj_' . bin2hex(random_bytes(8)) . '.' . $adj['ext'];
        if (move_uploaded_file($adj['tmp'], $carpetaAdj . $nombreServidor)) {
            $adjuntosGuardados[] = [
                'nombre'  => $adj['nombre'],
                'archivo' => $nombreServidor,
                'mime'    => $adj['mime'],
            ];
        }
    }
}

// Registrar en JSON
$entrada = [
    'archivo'         => $nombrePDF,
    'carpeta'         => $dni,
    'adjuntos'        => $adjuntosGuardados,
    'dni'             => $dni,
    'nombre'          => $nombre,
    'cooperativa'     => $cooperativa,
    'modelo'          => $tipoInforme,
    'total_eur'       => round($totalEur, 2),
    'html_fuente'     => $nombreHtmlFuente,
    'firmado'         => $firmaInicial,
    'archivo_firmado' => '',
    'firma_fecha'     => $firmaInicial ? date('Y-m-d H:i:s') : '',
    'fecha'           => date('Y-m-d'),
    'hora'            => date('H:i:s'),
    'timestamp'       => time(),
];

$registro = [];
if (file_exists(REGISTRO_JSON)) {
    $raw = file_get_contents(REGISTRO_JSON);
    $registro = json_decode($raw, true) ?? [];
}
array_unshift($registro, $entrada); // más reciente primero
file_put_contents(REGISTRO_JSON, json_encode($registro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

// Limpiar temporales de logo y firma inicial
// (las imágenes de prueba se guardan permanentemente en informes/{DNI}/imagenes/)
if ($logoImgPath && file_exists($logoImgPath)) {
    unlink($logoImgPath);
}
if ($firmaImgPath && file_exists($firmaImgPath)) {
    unlink($firmaImgPath);
}

// Redirigir al formulario con mensaje de éxito
$_SESSION['informe_ok'] = [
    'nombre' => $nombre,
    'dni'    => $dni,
];
header('Location: index.php');
exit;
