<?php

declare(strict_types=1);
ini_set('memory_limit', '2048M');
session_start();
require_once __DIR__ . '/config_m3.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/informe_estructura.php';

// ─── Helpers ──────────────────────────────────────────────────────
function m3_eur(float $v): string
{
  return number_format($v, 2, ',', '.') . ' €';
}
function m3_ha(float $v): string
{
  return number_format($v, 2, ',', '.') . ' ha';
}
function m3_redirect(string $msg): never
{
  $_SESSION['form_error'] = $msg;
  $_SESSION['form_data']  = $_POST;
  header('Location: index.php');
  exit;
}
function m3_h(?string $v): string
{
  return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function resolver_expediente_m3(array $registro, string $cifNif): array
{
  $año = date('Y');
  $cifLimpio = strtoupper(trim($cifNif));
  $expedienteBase = null;
  $maxRevision = -1;

  foreach ($registro as $item) {
    $itemDni = strtoupper(trim((string)($item['cif_nif'] ?? $item['dni'] ?? '')));
    if ($itemDni === $cifLimpio) {
      if (!empty($item['expediente_base'])) {
        $expedienteBase = $item['expediente_base'];
      } elseif (!empty($item['expediente'])) {
        $parts = explode('_', $item['expediente']);
        if (count($parts) >= 4 && is_numeric(end($parts)) && strlen(end($parts)) === 2) {
          array_pop($parts);
          $expedienteBase = implode('_', $parts);
        } else {
          $expedienteBase = $item['expediente'];
        }
      }
      $rev = (int)($item['revision'] ?? 0);
      if ($rev > $maxRevision) {
        $maxRevision = $rev;
      }
    }
  }

  if ($expedienteBase !== null) {
    $nuevaRev = $maxRevision + 1;
    $revStr   = str_pad((string)$nuevaRev, 2, '0', STR_PAD_LEFT);
    $expCompleto = $expedienteBase . '_' . $revStr;
    return [
      'expediente_base'     => $expedienteBase,
      'revision'            => $nuevaRev,
      'expediente_completo' => $expCompleto,
      'es_revision'         => true,
    ];
  } else {
    $expedientesUnicos = [];
    foreach ($registro as $item) {
      $base = $item['expediente_base'] ?? ($item['expediente'] ?? '');
      if ($base) {
        $parts = explode('_', $base);
        if (count($parts) >= 4 && is_numeric(end($parts)) && strlen(end($parts)) === 2) {
          array_pop($parts);
          $base = implode('_', $parts);
        }
        $expedientesUnicos[$base] = true;
      }
    }
    $seq = count($expedientesUnicos) + 1;
    $expedienteBase = 'Md3_' . $año . '_' . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
    return [
      'expediente_base'     => $expedienteBase,
      'revision'            => 0,
      'expediente_completo' => $expedienteBase,
      'es_revision'         => false,
    ];
  }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: index.php');
  exit;
}

// ─── CSRF ─────────────────────────────────────────────────────────
$tokenEnviado = $_POST['csrf_token'] ?? '';
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $tokenEnviado)) {
  m3_redirect('Token de seguridad inválido. Recarga la página.');
}

// ─── SANITIZACIÓN ─────────────────────────────────────────────────
$razonSocial      = trim((string)($_POST['razon_social']         ?? ''));
$tipoDoc          = strtoupper(trim((string)($_POST['tipo_doc']  ?? 'DNI')));
$cifNif           = strtoupper(trim((string)($_POST['cif_nif']   ?? '')));
$repNombre        = trim((string)($_POST['representante_nombre'] ?? ''));
$repDni           = strtoupper(trim((string)($_POST['representante_dni'] ?? '')));
$calle            = trim((string)($_POST['calle']                ?? ''));
$numero           = trim((string)($_POST['numero']              ?? ''));
$bloque           = trim((string)($_POST['bloque']              ?? ''));
$piso             = trim((string)($_POST['piso']                ?? ''));
$codigoPostal     = trim((string)($_POST['codigo_postal']       ?? ''));
$provincia        = trim((string)($_POST['provincia']           ?? ''));
$municipio        = trim((string)($_POST['municipio']           ?? ''));
$telefono         = trim((string)($_POST['telefono']            ?? ''));
$email            = trim((string)($_POST['email']               ?? ''));
$localidadExp     = trim((string)($_POST['localidad_exp']       ?? ''));
$comarca          = trim((string)($_POST['comarca']             ?? ''));
$provinciaExp     = trim((string)($_POST['provincia_exp']       ?? 'Granada'));
$reafa            = trim((string)($_POST['reafa']               ?? ''));
$expProvincia     = trim((string)($_POST['exp_provincia']       ?? ''));
$expMunicipio     = trim((string)($_POST['exp_municipio']       ?? ''));
$expLocalidad     = trim((string)($_POST['exp_localidad']       ?? ''));
$cultivo          = trim((string)($_POST['cultivo']             ?? 'Espárrago Verde'));
$variedad         = trim((string)($_POST['variedad']            ?? ''));
$edadCultivo      = (int)($_POST['edad_cultivo']               ?? 0);
$supSecano        = abs((float)($_POST['sup_secano']            ?? 0));
$supSecanoTipo    = trim((string)($_POST['sup_secano_tipo']     ?? ''));
$supRegadio       = abs((float)($_POST['sup_regadio']           ?? 0));
$supRegadioTipo   = trim((string)($_POST['sup_regadio_tipo']    ?? ''));
$sistCultivo      = trim((string)($_POST['sistema_cultivo']     ?? ''));

// Parámetros de Campaña y Rendimiento Industrial

$prodEstimadaKg      = abs((float)($_POST['prod_estimada_kg']      ?? 0));
$prodRealKg          = abs((float)($_POST['prod_real_m3_kg']       ?? 0));
$menorCalidadVal = abs((float)($_POST['menor_calidad_valor'] ?? 0));
$menorCalidadTipo = trim((string)($_POST['menor_calidad_tipo'] ?? 'kg'));
$prodPrevistaProxKg  = abs((float)($_POST['prod_prevista_prox_kg'] ?? 0));
$sobrecostesExtraEur = abs((float)($_POST['sobrecostes_extra_eur'] ?? 0));

$nivelAfeccion    = trim((string)($_POST['nivel_afeccion']      ?? 'Alta'));
$drenajeParcelas  = trim((string)($_POST['drenaje_parcelas']    ?? 'Malo'));
$firmaDataUri     = trim((string)($_POST['firma_data']          ?? ''));

// Firma digital (opcional)
$firmaInicial = $firmaDataUri !== '' && str_starts_with($firmaDataUri, 'data:image/png;base64,');

// ─── Calculados ───────────────────────────────────────────────────
$supTotal = $supSecano + $supRegadio;
if ($supSecano > 0 && $supRegadio === 0.0) {
  $sistExplotacion = 'Secano';
} elseif ($supRegadio > 0 && $supSecano === 0.0) {
  $sistExplotacion = 'Regadío';
} elseif ($supSecano > 0 && $supRegadio > 0) {
  $sistExplotacion = 'Mixto';
} else {
  $sistExplotacion = '—';
}

$sistCultivoLabel = M3_SISTEMAS_CULTIVO[$sistCultivo] ?? $sistCultivo;
$supSecanoTipoLabel = M3_SISTEMAS_CULTIVO[$supSecanoTipo] ?? $supSecanoTipo;
$supRegadioTipoLabel = M3_SISTEMAS_CULTIVO[$supRegadioTipo] ?? $supRegadioTipo;

// ─── VALIDACIÓN ───────────────────────────────────────────────────
$errores = [];
if (empty($razonSocial))   $errores[] = 'El nombre o razón social es obligatorio.';
if (empty($cifNif))        $errores[] = 'El número de documento (DNI/CIF/NIE) es obligatorio.';

// REGLA OBLIGATORIA: Si se elige CIF (Empresa), los datos del Representante son obligatorios
if ($tipoDoc === 'CIF' || preg_match('/^[ABCDEFGHJNPQRSUVW]/i', $cifNif)) {
  if (empty($repNombre)) $errores[] = 'El nombre del representante legal es obligatorio para empresas (CIF).';
  if (empty($repDni))    $errores[] = 'El DNI/NIE del representante legal es obligatorio para empresas (CIF).';
}

if (empty($calle))         $errores[] = 'La calle es obligatoria.';
if (empty($numero))        $errores[] = 'El número de calle es obligatorio.';
if (!preg_match('/^\d{5}$/', $codigoPostal)) $errores[] = 'El código postal debe tener 5 dígitos.';
if (empty($provincia))     $errores[] = 'La provincia del domicilio es obligatoria.';
if (empty($municipio))     $errores[] = 'El municipio del domicilio es obligatorio.';
if (empty($telefono))      $errores[] = 'El teléfono es obligatorio.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'El correo electrónico no es válido.';
if (empty($localidadExp))  $errores[] = 'La localidad de la explotación es obligatoria.';
if (empty($comarca))       $errores[] = 'La comarca es obligatoria.';
if (empty($reafa))         $errores[] = 'El código REAFA es obligatorio.';
if (empty($expProvincia))  $errores[] = 'La provincia de la explotación es obligatoria.';
if (empty($expMunicipio))  $errores[] = 'El municipio de la explotación es obligatorio.';
if (empty($expLocalidad))  $errores[] = 'La localidad de la explotación es obligatoria.';
if (empty($variedad))      $errores[] = 'La variedad del cultivo es obligatoria.';
if ($supTotal <= 0)        $errores[] = 'La superficie total debe ser mayor que cero.';
if (empty($sistCultivo))   $errores[] = 'El sistema de cultivo es obligatorio.';

// Datos de valoración económica: obligatorios, sin ellos no hay informe que generar
if ($prodEstimadaKg <= 0)      $errores[] = 'La producción estimada de la campaña afectada es obligatoria.';
if ($prodRealKg <= 0)          $errores[] = 'La producción real recolectada es obligatoria.';
if (!isset($_POST['menor_calidad_valor']) || trim((string)$_POST['menor_calidad_valor']) === '') {
  $errores[] = 'La producción de menor calidad / destrío es obligatoria (indica 0 si no hubo).';
}
if ($prodPrevistaProxKg <= 0)  $errores[] = 'La producción prevista de la próxima campaña es obligatoria.';
if (!isset($_POST['sobrecostes_extra_eur']) || trim((string)$_POST['sobrecostes_extra_eur']) === '') {
  $errores[] = 'Los sobrecostes extraordinarios son obligatorios (indica 0 si no hubo).';
}

if (!empty($errores)) {
  m3_redirect(implode(' ', $errores));
}
unset($_SESSION['form_data']);

// ─── Directorio de informes unificado ──────────────────────────────
if (!is_dir(INFORMES_DIR)) {
  mkdir(INFORMES_DIR, 0755, true);
}

$registro = [];
if (file_exists(REGISTRO_JSON)) {
  $raw = file_get_contents(REGISTRO_JSON);
  $registro = json_decode($raw, true) ?? [];
}

// Resolver Expediente Base y Revisión
$expInfo = resolver_expediente_m3($registro, $cifNif);
$numExpediente = $expInfo['expediente_completo'];
$expedienteBase = $expInfo['expediente_base'];
$revision = $expInfo['revision'];

// ─── ESTRUCTURA DE CARPETAS POR DNI / CIF ─────────────────────────
$carpetaUsuario = INFORMES_DIR . $cifNif . '/';
$subfolderNombre = $expInfo['es_revision'] ? 'imagenes_' . str_pad((string)$revision, 2, '0', STR_PAD_LEFT) : 'imagenes';
$subfolderAdjNombre = $expInfo['es_revision'] ? 'adjuntos_' . str_pad((string)$revision, 2, '0', STR_PAD_LEFT) : 'adjuntos';

$carpetaImagenesPerm = $carpetaUsuario . $subfolderNombre . '/';
if (!is_dir($carpetaImagenesPerm)) {
  mkdir($carpetaImagenesPerm, 0755, true);
}

$imagenesBase = [];
if (!empty($_FILES['imagenes']['name'][0])) {
  $archivos = $_FILES['imagenes'];
  $total    = count($archivos['name']);
  if ($total > MAX_IMAGENES) {
    m3_redirect('Se permiten como máximo ' . MAX_IMAGENES . ' imágenes.');
  }
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  for ($i = 0; $i < $total; $i++) {
    if ($archivos['error'][$i] !== UPLOAD_ERR_OK) continue;
    if ($archivos['size'][$i]  > MAX_TAMANO_IMG) {
      m3_redirect('Una o más imágenes superan el tamaño máximo de 8 MB.');
    }
    $mimeReal = $finfo->file($archivos['tmp_name'][$i]);
    if (!in_array($mimeReal, TIPOS_IMAGEN, true)) {
      m3_redirect('Solo se aceptan imágenes JPG, PNG o WebP.');
    }
    $permRuta = $carpetaImagenesPerm . 'img_' . bin2hex(random_bytes(8)) . '.jpg';
    $srcPath  = $archivos['tmp_name'][$i];
    $img = match ($mimeReal) {
      'image/jpeg' => @imagecreatefromjpeg($srcPath),
      'image/png'  => @imagecreatefrompng($srcPath),
      'image/webp' => @imagecreatefromwebp($srcPath),
      default      => false,
    };
    if ($img === false) {
      move_uploaded_file($srcPath, $permRuta);
    } else {
      $maxDim = 1200;
      $w = imagesx($img);
      $h = imagesy($img);
      if ($w > $maxDim || $h > $maxDim) {
        $ratio = $w / $h;
        [$nw, $nh] = $w >= $h ? [$maxDim, (int)round($maxDim / $ratio)] : [(int)round($maxDim * $ratio), $maxDim];
        $res = imagecreatetruecolor($nw, $nh);
        imagefill($res, 0, 0, imagecolorallocate($res, 255, 255, 255));
        imagecopyresampled($res, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $res;
      }
      imagejpeg($img, $permRuta, 70);
      imagedestroy($img);
    }
    if (file_exists($permRuta)) {
      $imagenesBase[] = ['ruta' => $permRuta, 'nombre' => 'Imagen ' . ($i + 1)];
    }
  }
}

// ─── FIRMA ────────────────────────────────────────────────────────
$firmaImgPath = null;
if ($firmaInicial) {
  $firmaBase64  = substr($firmaDataUri, strlen('data:image/png;base64,'));
  $firmaDecoded = base64_decode($firmaBase64, true);
  if ($firmaDecoded !== false) {
    $firmaImgPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'borrascas_m3_firma_' . bin2hex(random_bytes(4)) . '.png';
    file_put_contents($firmaImgPath, $firmaDecoded);
  }
}

// ─── LOGO ─────────────────────────────────────────────────────────
$logoPath    = __DIR__ . '/../assets/img/Faeca.png';
$logoImgPath = '';
if (file_exists($logoPath)) {
  $logoSrc = @imagecreatefrompng($logoPath);
  if ($logoSrc) {
    $lw = imagesx($logoSrc);
    $lh = imagesy($logoSrc);
    if ($lw > 400) {
      $nlh = (int)round($lh * 400 / $lw);
      $logoRes = imagecreatetruecolor(400, $nlh);
      imagefill($logoRes, 0, 0, imagecolorallocate($logoRes, 255, 255, 255));
      imagecopyresampled($logoRes, $logoSrc, 0, 0, 0, 0, 400, $nlh, $lw, $lh);
      imagedestroy($logoSrc);
      $logoSrc = $logoRes;
    } else {
      $bg = imagecreatetruecolor($lw, $lh);
      imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
      imagecopy($bg, $logoSrc, 0, 0, 0, 0, $lw, $lh);
      imagedestroy($logoSrc);
      $logoSrc = $bg;
    }
    $logoImgPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'borrascas_m3_logo_' . bin2hex(random_bytes(4)) . '.jpg';
    imagejpeg($logoSrc, $logoImgPath, 85);
    imagedestroy($logoSrc);
  }
}
$logoHtml = $logoImgPath ? '<img src="' . $logoImgPath . '" style="height:48px;width:auto;" />' : '';

$fechaHoy = date('d/m/Y');

// ─── CABECERA CON Nº EXPEDIENTE, NOMBRE Y DNI ─────────────────────
$headerHtml = '
<table style="width:100%;border-bottom:2px solid #2d6a4f;padding-bottom:5px;font-family:DejaVu Sans,Arial,sans-serif;font-size:10.5px;color:#333;">
  <tr>
    <td style="width:65px;vertical-align:middle;">' . $logoHtml . '</td>
    <td style="vertical-align:middle;padding-left:8px;">
      <div style="font-size:13px;font-weight:bold;color:#1b4332;text-transform:uppercase;">INFORME DE DA&Ntilde;OS POR BORRASCA &middot; MODELO 3</div>
      <div style="font-size:11px;color:#2d6a4f;font-weight:bold;margin-top:2px;">Expediente: ' . m3_h($numExpediente) . '</div>
      <div style="font-size:10.5px;color:#444;margin-top:1px;">Solicitante: <strong>' . m3_h($razonSocial) . '</strong> (' . m3_h($cifNif) . ')</div>
    </td>
    <td style="width:90px;text-align:right;vertical-align:top;font-size:10.5px;color:#666;">
      <div>Fecha: ' . $fechaHoy . '</div>
    </td>
  </tr>
</table>';

// ─── PIE DE PÁGINA CON PAGINACIÓN DINÁMICA ────────────────────────
$footerHtml = '
<table style="width:100%;border-top:1px solid #ccc;padding-top:4px;font-family:DejaVu Sans,Arial,sans-serif;font-size:10px;color:#666;">
  <tr>
    <td style="text-align:left;">' . htmlspecialchars(M3_TITULO_CAMPANA) . ' &middot; ACGranada</td>
    <td style="text-align:right;">P&aacute;gina {PAGENO} de {nbpg}</td>
  </tr>
</table>';

$htmlImagenes = '';
if (!empty($imagenesBase)) {
  $colWidthMm = 87.0;
  $marginBt = 5.0;
  $colLeft = [];
  $colRight = [];
  $altL = 0.0;
  $altR = 0.0;
  foreach ($imagenesBase as $img) {
    $info = @getimagesize($img['ruta']);
    $dh   = ($info && $info[0] > 0) ? ($colWidthMm * $info[1] / $info[0]) : 60.0;
    if ($altL <= $altR) {
      $colLeft[]  = $img;
      $altL += $dh + $marginBt;
    } else {
      $colRight[] = $img;
      $altR += $dh + $marginBt;
    }
  }
  $renderCol = static fn(array $imgs): string => implode('', array_map(
    fn($i) => '<div style="margin-bottom:5px;text-align:center;"><img src="' . htmlspecialchars($i['ruta']) . '" style="max-width:100%;border:1px solid #ccc;border-radius:3px;" /></div>',
    $imgs
  ));
  $htmlImagenes  = '<pagebreak />';
  $htmlImagenes .= '<h3 style="color:#2e6b3e;border-bottom:2px solid #2e6b3e;padding-bottom:4px;margin-bottom:10px;">9. Anexo: Fotograf&iacute;as del cultivo afectado</h3>';
  $htmlImagenes .= '<div style="float:left;width:48%;margin-right:2%;">' . $renderCol($colLeft)  . '</div>';
  $htmlImagenes .= '<div style="float:left;width:48%;margin-left:2%;">'  . $renderCol($colRight) . '</div>';
  $htmlImagenes .= '<div style="clear:both;"></div>';
}

$textoObjeto = 'El objeto del presente informe es cuantificar, con una base objetiva y t&eacute;cnica, los da&ntilde;os econ&oacute;micos ocasionados en el contexto de un tren de borrascas atl&aacute;nticas de gran intensidad que incidi&oacute; de forma reiterada sobre Andaluc&iacute;a durante enero y febrero de 2026 y que incidi&oacute; en la explotaci&oacute;n agr&iacute;cola cuyo titular es <strong>' . m3_h($razonSocial) . '</strong>, ubicada en la localidad de <strong>' . m3_h($localidadExp) . '</strong> en la comarca <strong>' . m3_h($comarca) . '</strong> de ' . m3_h($provinciaExp) . '. Dicha explotaci&oacute;n se encuentra ubicada dentro de la relaci&oacute;n de municipios afectados por las borrascas.<br><br>
Se proceder&aacute; a la descripci&oacute;n de los da&ntilde;os, su diagn&oacute;stico y la valoraci&oacute;n econ&oacute;mica asociada, con motivo del evento adverso acontecido. Todo ello con base en la informaci&oacute;n proporcionada por la persona titular o representante de la explotaci&oacute;n.<br><br>
Este informe se enmarca en el ' . m3_h(M3_REAL_DECRETO) . ', por el que se adoptan medidas urgentes en respuesta a los da&ntilde;os causados por diversos fen&oacute;menos meteorol&oacute;gicos adversos, con especial afectaci&oacute;n en las comunidades aut&oacute;nomas de Andaluc&iacute;a y Extremadura.';

$textoIntro = 'Durante el mes de enero y primeros d&iacute;as de febrero del a&ntilde;o 2026 se produjeron diversos episodios meteorol&oacute;gicos adversos en la provincia de Granada, caracterizados por precipitaciones intensas y persistentes, acompa&ntilde;adas en algunos momentos de fuertes rachas de viento.<br><br>
En el Informe Meteorol&oacute;gico del mes de febrero de 2026 de la Red de Alerta de Informaci&oacute;n Fitosanitaria (RAIF) se puede apreciar la excepcionalidad de las precipitaciones comparando en la siguiente gr&aacute;fica el climatograma del a&ntilde;o 2026 con los datos hist&oacute;ricos en la provincia de Granada.<br><br>
<div style="text-align:center;margin:6px 0;"><img src="' . __DIR__ . '/../assets/img/m2_doc/doc_img_1.png" style="height:150px;width:auto;max-width:95%;border:1px solid #c8ddd3;border-radius:4px;" /><div style="font-size:10px;color:#555;font-style:italic;margin-top:2px;">Figura 1. Climatograma del a&ntilde;o agr&iacute;cola actual e hist&oacute;rico en la provincia de Granada (Fuente: RAIF).</div></div><br>
En Andaluc&iacute;a, las precipitaciones fueron muy excepcionales. A mediados del mes de enero se form&oacute; la profunda borrasca Harry en el Mediterr&aacute;neo, que dej&oacute; chubascos intensos y tormentas principalmente en la zona de Albor&aacute;n. Durante el resto del mes siguieron pasando una sucesi&oacute;n de borrascas (Ingrid, Joseph y Kristin), que dejaron fuertes precipitaciones, generalizadas y persistentes, acompa&ntilde;adas de rachas de viento muy fuertes, nevadas importantes en el interior oriental y zonas de monta&ntilde;a, y el desbordamiento de algunos r&iacute;os en nuestra Comunidad.<br><br>
En el avance climatol&oacute;gico de AEMET para Andaluc&iacute;a, Ceuta y Melilla, enero de 2026 fue un mes muy h&uacute;medo seg&uacute;n la estaci&oacute;n clim&aacute;tica GRANADA (APTO.), con una media de 132,6 mm de lluvia, lo que representa un 320 % de la cantidad habitual para ese mes.<br><br>
<table style="width:100%;border:none;margin:6px 0;">
  <tr>
    <td style="width:50%;text-align:center;vertical-align:top;border:none;">
      <img src="' . __DIR__ . '/../assets/img/m2_doc/doc_img_2.png" style="height:140px;width:auto;max-width:98%;border:1px solid #c8ddd3;border-radius:4px;" />
      <div style="font-size:9.5px;color:#555;font-style:italic;margin-top:2px;">Figura 2. Precipitación acumulada - Enero 2026 (AEMET).</div>
    </td>
    <td style="width:50%;text-align:center;vertical-align:top;border:none;">
      <img src="' . __DIR__ . '/../assets/img/m2_doc/doc_img_3.png" style="height:140px;width:auto;max-width:98%;border:1px solid #c8ddd3;border-radius:4px;" />
      <div style="font-size:9.5px;color:#555;font-style:italic;margin-top:2px;">Figura 3. Car&aacute;cter precipitación - Enero 2026 (AEMET).</div>
    </td>
  </tr>
</table><br>
En cuanto al viento, el d&iacute;a 28 de enero, se registraron rachas de viento muy fuertes, y se llegaron a rachas de 115 km/h en la estaci&oacute;n de D&Iacute;LAR o incluso 121 km/h en SIERRA NEVADA.<br><br>
Febrero comenz&oacute; con el paso de varias borrascas atl&aacute;nticas, encabezadas por Leonardo, que provoc&oacute; lluvias muy intensas y persistentes, fuertes vientos, granizadas y nevadas en Andaluc&iacute;a. Las precipitaciones causaron importantes inundaciones, desbordamientos de r&iacute;os y numerosos da&ntilde;os, especialmente en C&aacute;diz, M&aacute;laga y Granada, siendo Hu&eacute;tor T&aacute;jar una de las localidades m&aacute;s afectadas. Tras el paso de otras borrascas, como Marta, Nils y Oriana, la segunda mitad del mes estuvo marcada por un tiempo m&aacute;s estable, aunque con episodios de viento fuerte.<br><br>
En el avance climatol&oacute;gico de AEMET para Andaluc&iacute;a, Ceuta y Melilla, febrero de 2026 fue un mes extremadamente h&uacute;medo seg&uacute;n la estaci&oacute;n clim&aacute;tica GRANADA (APTO.), con una media de 158.2 mm de lluvia, lo que representa un 436 % de la cantidad habitual para ese mes.<br><br>
<table style="width:100%;border:none;margin:6px 0;">
  <tr>
    <td style="width:50%;text-align:center;vertical-align:top;border:none;">
      <img src="' . __DIR__ . '/../assets/img/m2_doc/doc_img_4.png" style="height:140px;width:auto;max-width:98%;border:1px solid #c8ddd3;border-radius:4px;" />
      <div style="font-size:9.5px;color:#555;font-style:italic;margin-top:2px;">Figura 4. Precipitación acumulada - Febrero 2026 (AEMET).</div>
    </td>
    <td style="width:50%;text-align:center;vertical-align:top;border:none;">
      <img src="' . __DIR__ . '/../assets/img/m2_doc/doc_img_5.png" style="height:140px;width:auto;max-width:98%;border:1px solid #c8ddd3;border-radius:4px;" />
      <div style="font-size:9.5px;color:#555;font-style:italic;margin-top:2px;">Figura 5. Car&aacute;cter precipitación - Febrero 2026 (AEMET).</div>
    </td>
  </tr>
</table><br>
Los mayores acumulados se concentraron en el sector occidental de la provincia, donde la estaci&oacute;n meteorol&oacute;gica de la RIA de Iznalloz alcanz&oacute; los 654 mm durante el periodo analizado. Las persistentes lluvias ocasionaron igualmente un aumento considerable del caudal de los r&iacute;os y arroyos provocando la inundaci&oacute;n y encharcamiento de los cultivos. Sirva de ejemplo la siguiente ilustraci&oacute;n con el hidrograma en el punto SAIH R&Iacute;O GENIL-PTE. TOC&Oacute;N.<br><br>
<div style="text-align:center;margin:6px 0;"><img src="' . __DIR__ . '/../assets/img/m2_doc/doc_img_6.png" style="height:150px;width:auto;max-width:95%;border:1px solid #c8ddd3;border-radius:4px;" /><div style="font-size:10px;color:#555;font-style:italic;margin-top:2px;">Figura 6. Hidrograma del punto SAIH R&iacute;o Genil - Pte. Toc&oacute;n (05/02/2026).</div></div><br>
Adem&aacute;s de la gran cantidad de lluvia acumulada, el elevado n&uacute;mero de d&iacute;as consecutivos con precipitaciones ha dificultado el trabajo en el campo. Esta situaci&oacute;n ha impedido acceder con normalidad a las explotaciones y realizar labores esenciales, como la recolecci&oacute;n, la fertilizaci&oacute;n o los tratamientos fitosanitarios, aumentando as&iacute; el impacto del exceso de lluvia sobre la actividad agr&iacute;cola.<br><br>
Estos fen&oacute;menos provocaron incidencias significativas sobre la actividad agraria en diferentes zonas de la provincia, especialmente en aquellas &aacute;reas con mayor presencia de cultivos le&ntilde;osos y superficies agr&iacute;colas situadas en zonas de pendiente o pr&oacute;ximas a cauces y ramblas con incidencia debido a la p&eacute;rdida de cosechas y mayores costes en la recolecci&oacute;n, adem&aacute;s de la p&eacute;rdida de calidad.<br><br>
En cultivos hort&iacute;colas al aire libre, la saturaci&oacute;n h&iacute;drica prolongada caus&oacute; efectos devastadores:<br>
<strong>P&eacute;rdida directa de producci&oacute;n (Esp&aacute;rrago):</strong> Anegamiento de caballones, brotaci&oacute;n prematura con turiones deformes, asfixia de la garra y retraso/p&eacute;rdida del primer corte de primavera.<br>
<strong>Impacto en la pr&oacute;xima cosecha / rendimiento futuro:</strong> La asfixia en las garras de esp&aacute;rrago pudre reservas acumuladas, lo que reduce dr&aacute;sticamente el brote de garras en campa&ntilde;as posteriores y causa la muerte de plantas.<br>
<strong>Depreciaci&oacute;n de calidad:</strong> P&eacute;rdida de categor&iacute;a comercial por hifas f&uacute;ngicas, manchas t&eacute;rmicas/h&iacute;dricas y sobremaduraci&oacute;n.<br>
<strong>Sobrecostes operativos y de recuperaci&oacute;n:</strong> Gastos extras de recolecci&oacute;n, transporte o sobrecostes en cooperativa. Limpieza manual de fangos, rehacer caballones deshechos por la corriente, pases extraordinarios de fungicidas y bioestimulantes radiculares, y reposici&oacute;n de marras.';

$textoMetodologia = 'Este apartado tiene por objeto incorporar al presente informe un an&aacute;lisis objetivo del comportamiento de la campa&ntilde;a agr&iacute;cola en la explotaci&oacute;n a partir de los datos sectoriales. La finalidad de este an&aacute;lisis es aportar una base com&uacute;n de car&aacute;cter t&eacute;cnico que permita acreditar a escala de la explotaci&oacute;n agr&iacute;cola la existencia de una afecci&oacute;n general sobre la actividad agr&iacute;cola y su incidencia sobre la renta agraria.<br><br>
El da&ntilde;o econ&oacute;mico de una explotaci&oacute;n agr&iacute;cola no depende exclusivamente de la producci&oacute;n finalmente recolectada, sino del conjunto de consecuencias derivadas del episodio meteorol&oacute;gico sobre el sistema productivo. En consecuencia, la valoraci&oacute;n incorpora tanto las p&eacute;rdidas directas como los da&ntilde;os diferidos y los incrementos de costes ocasionados por la adversidad clim&aacute;tica.<br><br>
Se distinguen cinco componentes fundamentales del da&ntilde;o econ&oacute;mico:
<ul style="margin:5px 0 10px 18px;padding:0;font-size:11.5px;">
  <li>P&eacute;rdidas de producci&oacute;n de la campa&ntilde;a afectada;</li>
  <li>P&eacute;rdidas de producci&oacute;n de la campa&ntilde;a siguiente;</li>
  <li>Depreciaci&oacute;n de la calidad comercial;</li>
  <li>Incremento de costes de recolecci&oacute;n y producci&oacute;n;</li>
  <li>Costes extraordinarios de recuperaci&oacute;n de la explotaci&oacute;n.</li>
</ul>
La valoraci&oacute;n final del da&ntilde;o se obtiene mediante la suma de todas estas partidas.<br><br>

<h4 style="font-size:12.5px;color:#1b4332;margin-top:10px;margin-bottom:4px;">P&eacute;rdidas en producci&oacute;n:</h4>
La p&eacute;rdida en producci&oacute;n se debe a que el encharcamiento ha afectado al tama&ntilde;o de los esp&aacute;rragos, p&eacute;rdida de cosecha por podredumbre de turiones por exceso de humedad y encharcamiento, enterramiento de turiones por sedimentos y tambi&eacute;n a que no se ha podido cosechar porque el da&ntilde;o en las infraestructuras impidi&oacute; el paso de maquinaria y/o veh&iacute;culos o por el encharcamiento de las parcelas. Las p&eacute;rdidas de kilos comercializables reportan mermas severas de entre el 35 % y el 40 % de la producci&oacute;n en parcelas anegadas.<br><br>
La p&eacute;rdida econ&oacute;mica de producci&oacute;n se calcula mediante:<br>
<div style="background:#f0f4f0;border-left:3px solid #2d6a4f;padding:5px 10px;margin:5px 0;font-size:11px;font-weight:bold;">
  Coste P&eacute;rdida producci&oacute;n = (Producci&oacute;n estimada &minus; Producci&oacute;n real) &times; Precio medio de campa&ntilde;a
</div><br>

<h4 style="font-size:12.5px;color:#1b4332;margin-top:10px;margin-bottom:4px;">P&eacute;rdidas en producci&oacute;n pr&oacute;xima cosecha:</h4>
El encharcamiento y exceso de humedad durante largos per&iacute;odos de tiempo influye en la p&eacute;rdida en producci&oacute;n en la pr&oacute;xima cosecha debido a:
<ul style="margin:5px 0 10px 18px;padding:0;font-size:11.5px;">
  <li>Asfixia radicular por falta de ox&iacute;geno;</li>
  <li>Reducci&oacute;n del crecimiento radicular;</li>
  <li>Incremento de enfermedades radiculares (<em>Fusarium spp.</em>);</li>
  <li>Dificultades para realizar abonados y tratamientos en los momentos &oacute;ptimos.</li>
</ul>
Tambi&eacute;n se producen p&eacute;rdidas por la necesidad de arrancar y reponer plantas en las zonas m&aacute;s afectadas, reduciendo la capacidad productiva. Todo ello puede traducirse en una disminuci&oacute;n significativa de la cosecha siguiente.<br><br>
El % reducci&oacute;n fisiol&oacute;gica por el encharcamiento y exceso de humedad se calcula en base al nivel de afecci&oacute;n y drenaje de la parcela:
<table class="intro-tabla" style="margin-top:6px;margin-bottom:10px;width:100%;">
  <thead>
    <tr>
      <th style="width:15%">Nivel de afecci&oacute;n</th>
      <th style="width:65%">Situaci&oacute;n observada</th>
      <th style="width:20%;text-align:right;">Reducci&oacute;n fisiol&oacute;gica estimada</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Baja</strong></td>
      <td>Encharcamiento &lt;10 d&iacute;as. Se pudieron realizar la mayor&iacute;a de labores. Sin s&iacute;ntomas radiculares.</td>
      <td class="num">5-10 %</td>
    </tr>
    <tr>
      <td><strong>Moderada</strong></td>
      <td>Encharcamientos repetidos. Retraso en abonado y tratamientos. Ligera p&eacute;rdida de hojas o vigor.</td>
      <td class="num">10-20 %</td>
    </tr>
    <tr>
      <td><strong>Alta</strong></td>
      <td>Encharcamientos &gt;20-30 d&iacute;as. Asfixia radicular. Tratamientos imposibles durante semanas. Defoliaci&oacute;n parcial.</td>
      <td class="num">20-35 %</td>
    </tr>
    <tr>
      <td><strong>Muy alta</strong></td>
      <td>Inundaci&oacute;n prolongada. Da&ntilde;os radiculares importantes. Aparici&oacute;n de Phytophthora, erosi&oacute;n y p&eacute;rdida de suelo.</td>
      <td class="num">35-50 % (Hasta 100%)</td>
    </tr>
  </tbody>
</table>

La p&eacute;rdida en la pr&oacute;xima cosecha puede estimarse como:<br>
<div style="background:#f0f4f0;border-left:3px solid #2d6a4f;padding:5px 10px;margin:5px 0;font-size:11px;font-weight:bold;">
  Coste da&ntilde;os pr&oacute;xima campa&ntilde;a = Producci&oacute;n estimada pr&oacute;xima cosecha &times; % reducci&oacute;n fisiol&oacute;gica &times; precio previsto
</div><br>

<h4 style="font-size:12.5px;color:#1b4332;margin-top:10px;margin-bottom:4px;">Depreciaci&oacute;n en calidad de la producci&oacute;n:</h4>
No toda la producci&oacute;n recolectada mantiene su valor comercial. Una parte significativa del producto experimenta p&eacute;rdidas de calidad que reducen el precio percibido por el agricultor debido a la p&eacute;rdida de categor&iacute;a comercial por reducci&oacute;n del tama&ntilde;o, deformaciones de los turiones, p&eacute;rdida de textura, hifas f&uacute;ngicas, manchas y adem&aacute;s de influir negativamente en la s&iacute;ntesis de metabolitos asociados a la calidad organol&eacute;ptica del esp&aacute;rrago.<br><br>
La depreciaci&oacute;n econ&oacute;mica se obtiene mediante:<br>
<div style="background:#f0f4f0;border-left:3px solid #2d6a4f;padding:5px 10px;margin:5px 0;font-size:11px;font-weight:bold;">
  Coste p&eacute;rdida calidad = Producci&oacute;n comercializada menor calidad &times; diferencia de precio entre esp&aacute;rrago sano y esp&aacute;rrago depreciado
</div><br>

<h4 style="font-size:12.5px;color:#1b4332;margin-top:10px;margin-bottom:4px;">Sobrecostes durante la recolecci&oacute;n:</h4>
La presencia de fango ocasion&oacute; que los pases de recolecci&oacute;n manual fueran m&aacute;s dificultosos con un menor rendimiento por hora, suponiendo un mayor n&uacute;mero de contrataci&oacute;n de peonadas. Los esp&aacute;rragos que nacen cubiertos de lodo o sumergidos sufren asfixia radicular, se pudren o crecen deformes. Aunque se recolecten para limpiar la esparraguera, van directos a desecho, lo que significa trabajar a p&eacute;rdida.<br><br>
Diversos estudios econ&oacute;micos sit&uacute;an incrementos del coste de recolecci&oacute;n comprendidos habitualmente entre el 20 % y el 40 %, elevando el coste de recogida significativamente.<br><br>
El sobrecoste se obtiene teniendo en cuenta el coste medio de recolecci&oacute;n en &euro;/ha multiplicado por la superficie y el porcentaje de sobrecoste estimado.<br>

<h4 style="font-size:12.5px;color:#1b4332;margin-top:10px;margin-bottom:4px;">Sobrecostes de producci&oacute;n:</h4>
El producto con presencia de barro y suciedad ocasiona unos costes en la cooperativa debido a la necesidad de mayor tiempo para la selecci&oacute;n y clasificaci&oacute;n de un mayor porcentaje de producto, necesidad de disponer de m&aacute;s personal para limpieza y control de maquinaria, mayor consumo de energ&iacute;a y agua, mayores tiempos de limpieza y lavado de esp&aacute;rrago, mayores gastos por gesti&oacute;n de residuos de la limpieza.<br>
El sobrecoste se obtiene comparando el coste real con el coste habitual de campa&ntilde;as normales.<br><br>

<h4 style="font-size:12.5px;color:#1b4332;margin-top:10px;margin-bottom:4px;">Otros costes (tratamientos, recuperaci&oacute;n de cultivo,&hellip;):</h4>
Se estiman otros costes posteriores a la recolecci&oacute;n y postcosecha. La RAIF se&ntilde;ala que los episodios de lluvias intensas y persistentes generan dificultades en las labores culturales, problemas de asfixia radicular, procesos erosivos y un aumento significativo de la incidencia de enfermedades en esp&aacute;rrago y otros productos hort&iacute;colas que obligan a mayores costes de tratamientos fungicidas extraordinarios, aplicaciones de bioestimulantes, labores de descompactaci&oacute;n y reposiciones de plantas.<br><br>
Las recomendaciones t&eacute;cnicas de RAIF e IFAPA indican que los episodios prolongados de saturaci&oacute;n h&iacute;drica incrementan significativamente la incidencia de enfermedades y obligan a intensificar las labores de recuperaci&oacute;n durante los meses posteriores.<br>

<h4 style="font-size:12.5px;color:#1b4332;margin-top:10px;margin-bottom:4px;">Valoraci&oacute;n Final y Estimaci&oacute;n de Da&ntilde;os:</h4>
La valoraci&oacute;n final y estimaci&oacute;n de da&ntilde;os de la explotaci&oacute;n puede obtenerse mediante la siguiente expresi&oacute;n:<br>
<div style="background:#1b4332;color:#fff;padding:6px 10px;margin:6px 0;font-size:11px;font-weight:bold;border-radius:3px;">
  Da&ntilde;o total = P&eacute;rdida de producci&oacute;n campa&ntilde;a + P&eacute;rdida campa&ntilde;a siguiente + Depreciaci&oacute;n de calidad + Sobrecoste de recolecci&oacute;n + Sobrecoste de producci&oacute;n + Costes extraordinarios de recuperaci&oacute;n.
</div>
Este modelo permite cuantificar de forma objetiva el perjuicio econ&oacute;mico real sufrido por la explotaci&oacute;n, considerando no solo la cosecha perdida, sino tambi&eacute;n las repercusiones agron&oacute;micas y econ&oacute;micas derivadas de la alteraci&oacute;n del sistema productivo. Esta metodolog&iacute;a ofrece una valoraci&oacute;n completa y ajustada al da&ntilde;o efectivamente soportado por la explotaci&oacute;n, al integrar tanto los efectos inmediatos como los diferidos del episodio clim&aacute;tico, conforme a los criterios t&eacute;cnicos empleados en la evaluaci&oacute;n de da&ntilde;os agrarios y respaldados por la evidencia cient&iacute;fica disponible.<br><br>

<h4 style="font-size:12.5px;color:#1b4332;margin-top:10px;margin-bottom:4px;">Estimaci&oacute;n de costes de referencia:</h4>
Para el c&aacute;lculo de los da&ntilde;os se han estimado los siguientes conceptos:
<table class="datos" style="margin-top:6px;margin-bottom:10px;">
  <thead>
    <tr>
      <th>Cultivo</th>
      <th>Concepto</th>
      <th style="text-align:right;">Coste asociado</th>
    </tr>
  </thead>
  <tbody>
    <tr><td><strong>Esp&aacute;rrago</strong></td><td>P&eacute;rdida de producci&oacute;n</td><td style="text-align:right;">3,16 &euro;/kg.</td></tr>
    <tr><td><strong>Esp&aacute;rrago</strong></td><td>P&eacute;rdida de producci&oacute;n pr&oacute;xima cosecha</td><td style="text-align:right;">3,18 &euro;/kg.</td></tr>
    <tr><td><strong>Esp&aacute;rrago</strong></td><td>P&eacute;rdida calidad</td><td style="text-align:right;">1,50 &euro;/kg.</td></tr>
    <tr><td><strong>Esp&aacute;rrago</strong></td><td>Incremento costes de recolecci&oacute;n (Media)</td><td style="text-align:right;">40% (sobre 6.694 &euro;/ha)</td></tr>
    <tr><td><strong>Esp&aacute;rrago</strong></td><td>Sobrecoste producci&oacute;n cooperativa</td><td style="text-align:right;">0,15 &euro;/kg.</td></tr>
  </tbody>
</table>
<div style="font-size:10px;color:#666;margin-bottom:10px;">
  <sup>1</sup> Valores estimados de referencia en la campa&ntilde;a actual del Esp&aacute;rrago Verde en la provincia de Granada.
</div><h4 style="font-size:12.5px;color:#1b4332;margin-top:14px;margin-bottom:4px;">Fuentes de informaci&oacute;n:</h4>
<ul class="intro-lista">
  <li>Avance climatol&oacute;gico mensual en Andaluc&iacute;a, Ceuta y Melilla. Enero 2026.</li>
  <li>Avance climatol&oacute;gico mensual en Andaluc&iacute;a, Ceuta y Melilla. Febrero 2026.</li>
  <li>Datos del Ministerio de Agricultura, Pesca y Alimentaci&oacute;n.</li>
  <li>Estad&iacute;sticas Agrarias Municipales de la Consejer&iacute;a de Agricultura de la Junta de Andaluc&iacute;a.</li>
  <li>Informaci&oacute;n sectorial procedente cooperativas agrarias implantadas en las comarcas objeto del estudio.</li>
  <li>Informe Meteorol&oacute;gico del mes de febrero de 2026 de la Red de Alerta de Informaci&oacute;n Fitosanitaria (RAIF).</li>
  <li>Kozlowski, T.T. (1984). Plant responses to flooding. BioScience, 34(3), 162&ndash;167.</li>
  <li>Lorite IJ, Alza J, Cabezas J.M.(2026) C&oacute;rdoba. An&aacute;lisis de los eventos de lluvias extremas ocurridos en Andaluc&iacute;a en 2026. Instituto de Investigaci&oacute;n y Formaci&oacute;n Agraria y Pesquera, 2026. 1-19 p. Consejer&iacute;a de Agricultura, Pesca, Agua y Desarrollo Rural. Formato digital (e-book) - (Recursos Naturales y Forestales).</li>
</ul>';

$textoDescDanos = 'La explotaci&oacute;n presenta da&ntilde;os ocasionados por el temporal de lluvias y vientos:<br>
<table class="datos" style="margin-top:6px;">
  <tr><th>Da&ntilde;os Agron&oacute;micos</th><td>Anegamiento de caballones, brotaci&oacute;n prematura con turiones deformes, asfixia de la garra y retraso/p&eacute;rdida del primer corte de primavera, as&iacute; como incidencia de plagas y enfermedades radiculares (Fusarium, Phytophthora).</td></tr>
  <tr><th>Da&ntilde;os Estructurales</th><td>Deterioro de accesos, erosi&oacute;n y c&aacute;rcavas, da&ntilde;os en infraestructura de riego, arrastre de fango en parcelas.</td></tr>
</table>';

// ─── CÁLCULOS ESPÁRRAGO (Apartado 7) - EXACTOS SEGÚN EXCEL ──────────
$precioEsparrago       = 3.16; // €/kg espárrago (Precio medio de campaña)
$precioProxEsparrago   = 3.18; // €/kg espárrago (Precio previsto próxima cosecha)
$depreciacionKg        = 1.50; // €/kg depreciación por destrío / calidad
$costeCoopKg           = 0.15; // €/kg sobrecoste cooperativa (lavado intensivo)
$costeRecoleccionRefHa = 6694.00; // €/ha coste medio recolección espárrago
$pctSobrecosteRec      = 0.40; // 40% incremento de recolección según Excel

// Producción de Menor Calidad / Destrío (en Kilos)
if ($menorCalidadTipo === 'pct') {
  $pct = min(1.0, $menorCalidadVal / 100);
  $kgMenorCalidad = $prodRealKg * $pct;
} else {
  $kgMenorCalidad = $menorCalidadVal;
}

// 1. Pérdida de producción campaña afectada (2025/2026)
$perdidaProdKg = max(0, $prodEstimadaKg - $prodRealKg);
$valorPerdidaProd = $perdidaProdKg * $precioEsparrago;

// 2. Pérdida campaña siguiente (2026/2027) - Daño diferido (Matriz Excel)
$reduccionFisiologica = 0.0;
if ($nivelAfeccion === 'Baja')     $reduccionFisiologica = ($drenajeParcelas === 'Bueno') ? 0.05 : 0.10;
if ($nivelAfeccion === 'Moderada') $reduccionFisiologica = ($drenajeParcelas === 'Bueno') ? 0.10 : 0.20;
if ($nivelAfeccion === 'Alta')     $reduccionFisiologica = ($drenajeParcelas === 'Bueno') ? 0.20 : 0.35;
if ($nivelAfeccion === 'Muy alta') $reduccionFisiologica = ($drenajeParcelas === 'Bueno') ? 0.35 : 1.00; // 100% en Mal drenaje según Excel

// Producción prevista para la próxima campaña (2026/2027)
$baseProxKg = ($prodPrevistaProxKg > 0) ? $prodPrevistaProxKg : $prodEstimadaKg;
$perdidaProximaKg = $baseProxKg * $reduccionFisiologica;
$valorPerdidaProxima = $perdidaProximaKg * $precioProxEsparrago;

// 3. Depreciación de calidad
$valorDepreciacion = $kgMenorCalidad * $depreciacionKg;

// 4. Sobrecostes Recolección y Cooperativa (Exacto Excel)
$valorSobrecosteRec = $supTotal * $costeRecoleccionRefHa * $pctSobrecosteRec;
$valorSobrecosteProd = $prodRealKg * $costeCoopKg;
$valorSobrecostes = $valorSobrecosteRec + $valorSobrecosteProd;

// 5. Sobrecostes Extraordinarios Justificados
$valorSobrecostesExtra = $sobrecostesExtraEur;

$danoTotal = $valorPerdidaProd + $valorPerdidaProxima + $valorDepreciacion + $valorSobrecostes + $valorSobrecostesExtra;

$textoValoracion = 'Para la elaboraci&oacute;n de la valoraci&oacute;n econ&oacute;mica de la explotaci&oacute;n de esp&aacute;rrago se han utilizado los datos de producci&oacute;n declarados y los par&aacute;metros oficiales del modelo pericial:<br>

<table class="datos" style="margin-top:6px;margin-bottom:12px;">
  <tr><th style="width:40%;">Campa&ntilde;a 2025/2026: Estimada vs Real</th><td>' . ($prodEstimadaKg > 0 ? number_format($prodEstimadaKg, 0, ',', '.') . ' Kg' : 'Estimaci&oacute;n t&eacute;cnica') . ' &rarr; ' . ($prodRealKg > 0 ? number_format($prodRealKg, 0, ',', '.') . ' Kg recolectados' : 'Pendiente') . '</td></tr>
  <tr><th>Esp&aacute;rrago de menor calidad / destr&iacute;o</th><td>' . ($kgMenorCalidad > 0 ? number_format($kgMenorCalidad, 0, ',', '.') . ' Kg' : '0 Kg') . '</td></tr>
  <tr><th>Campa&ntilde;a 2026/2027: Previsi&oacute;n sin borrascas</th><td>' . number_format($baseProxKg, 0, ',', '.') . ' Kg</td></tr>
  <tr><th>Nivel de afecci&oacute;n / Drenaje</th><td>' . m3_h($nivelAfeccion) . ' &middot; Drenaje ' . m3_h($drenajeParcelas) . ' (% Reducci&oacute;n fisiol&oacute;gica: ' . ($reduccionFisiologica * 100) . '%)</td></tr>
</table>

<h4 style="font-size:12.5px;color:#1b4332;margin-top:12px;margin-bottom:6px;">Cuadro Pericial de Desglose de Da&ntilde;os (C&aacute;lculo Esp&aacute;rrago):</h4>

<table class="datos" style="margin-top:6px;width:100%;border-collapse:collapse;font-size:10.5px;">
  <thead>
    <tr style="background:#2d6a4f;color:#fff;">
      <th style="text-align:left;padding:5px;width:35%;color:#fff;background:#2d6a4f;">Concepto</th>
      <th style="text-align:right;padding:5px;width:25%;color:#fff;background:#2d6a4f;">Base Kgs / Ha</th>
      <th style="text-align:right;padding:5px;width:20%;color:#fff;background:#2d6a4f;">&euro;/Kg / Ratio</th>
      <th style="text-align:right;padding:5px;width:20%;color:#fff;background:#2d6a4f;">Valor (&euro;)</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Campa&ntilde;a 2025/2026 (Estimada inicial)</strong></td>
      <td style="text-align:right;">' . number_format($prodEstimadaKg, 0, ',', '.') . ' Kg</td>
      <td style="text-align:right;">3,16 &euro;/Kg</td>
      <td style="text-align:right;">' . m3_eur($prodEstimadaKg * $precioEsparrago) . '</td>
    </tr>
    <tr>
      <td><strong>Campa&ntilde;a 2025/2026 (Real recolectada)</strong></td>
      <td style="text-align:right;">' . number_format($prodRealKg, 0, ',', '.') . ' Kg</td>
      <td style="text-align:right;">3,16 &euro;/Kg</td>
      <td style="text-align:right;">' . m3_eur($prodRealKg * $precioEsparrago) . '</td>
    </tr>
    <tr style="background:#f9fbf9;font-weight:bold;">
      <td>Subtotal 1. P&eacute;rdida producci&oacute;n directa (25/26)</td>
      <td style="text-align:right;">' . number_format($perdidaProdKg, 0, ',', '.') . ' Kg mermados</td>
      <td style="text-align:right;">3,16 &euro;/Kg</td>
      <td style="text-align:right;color:#1b4332;">' . m3_eur($valorPerdidaProd) . '</td>
    </tr>
    <tr>
      <td><strong>Subtotal 2. P&eacute;rdida pr&oacute;xima cosecha (26/27)</strong> (' . ($reduccionFisiologica * 100) . '% asfixia)</td>
      <td style="text-align:right;">' . number_format($perdidaProximaKg, 0, ',', '.') . ' Kg previstos</td>
      <td style="text-align:right;">3,18 &euro;/Kg</td>
      <td style="text-align:right;font-weight:bold;color:#1b4332;">' . m3_eur($valorPerdidaProxima) . '</td>
    </tr>
    <tr>
      <td><strong>Subtotal 3. Depreciaci&oacute;n de calidad</strong> (Destr&iacute;o entregado)</td>
      <td style="text-align:right;">' . number_format($kgMenorCalidad, 0, ',', '.') . ' Kg</td>
      <td style="text-align:right;">1,50 &euro;/Kg</td>
      <td style="text-align:right;font-weight:bold;color:#1b4332;">' . m3_eur($valorDepreciacion) . '</td>
    </tr>
    <tr>
      <td><strong>Subtotal 4. Sobrecostes recolecci&oacute;n y cooperativa</strong></td>
      <td style="text-align:right;">Rec. (' . m3_ha($supTotal) . ') / Coop. (' . number_format($prodRealKg, 0, ',', '.') . ' Kg)</td>
      <td style="text-align:right;">40% (Rec) / 0,15 &euro;/Kg (Coop)</td>
      <td style="text-align:right;font-weight:bold;color:#1b4332;">' . m3_eur($valorSobrecostes) . '</td>
    </tr>
    <tr>
      <td><strong>Subtotal 5. Sobrecostes extraordinarios justificados</strong></td>
      <td style="text-align:right;">&mdash;</td>
      <td style="text-align:right;">Seg&uacute;n facturas</td>
      <td style="text-align:right;font-weight:bold;color:#1b4332;">' . m3_eur($valorSobrecostesExtra) . '</td>
    </tr>
  </tbody>
  <tfoot>
    <tr style="background:#1b4332;color:#fff;font-weight:bold;">
      <td colspan="3" style="text-align:right;padding:7px;font-size:12px;background:#1b4332;color:#fff;">TOTAL DA&Ntilde;OS ESTIMADOS EN EXPLOTACI&Oacute;N:</td>
      <td style="text-align:right;padding:7px;font-size:13px;background:#1b4332;color:#fff;font-weight:bold;">' . m3_eur($danoTotal) . '</td>
    </tr>
  </tfoot>
</table>
<div style="font-size:10px;color:#666;margin-top:4px;">
  C&aacute;lculos realizados estrictamente seg&uacute;n el modelo oficial de c&aacute;lculo pericial de da&ntilde;os en esp&aacute;rrago verde.
</div>';
$textoConclu = 'Las lluvias torrenciales acaecidas durante los meses de enero y febrero de 2026, as&iacute; como los episodios con fuertes vientos, son la causa directa de los da&ntilde;os sufridos en la explotaci&oacute;n.<br><br>
En base a los datos disponibles y estimados, se acredita el perjuicio econ&oacute;mico real soportado por la explotaci&oacute;n, justificando la necesidad de compensaci&oacute;n e intervenci&oacute;n en el marco del Real Decreto-ley 5/2026, de 17 de febrero.';

$firmaImgTag = $firmaImgPath
  ? '<img src="' . $firmaImgPath . '" style="max-width:220px;max-height:80px;margin:8px 0 4px auto;display:block;" />'
  : '<div class="firma-linea" style="border-top:1px solid #888;width:200px;margin:24px 0 4px auto;"></div>';

// Portada e índice en HTML propio (sin cabecera de página)
$htmlPortada = pa_generar_portada_indice([
  'logo_cover_path' => realpath(__DIR__ . '/../assets/img/FaecaAGRO360Transparente.png') ?: '',
  'solicitante'     => $razonSocial,
  'documento'       => $cifNif,
  'expediente'      => $numExpediente,
  'fecha'           => $fechaHoy,
  'indice'          => [
    'Datos del solicitante',
    'Objeto del informe',
    'Datos de la explotaci&oacute;n',
    'Introducci&oacute;n y contexto meteorol&oacute;gico',
    'Metodolog&iacute;a y fuentes de informaci&oacute;n',
    'Descripci&oacute;n de da&ntilde;os',
    'Valoraci&oacute;n de da&ntilde;os y p&eacute;rdida de renta',
    'Conclusi&oacute;n',
    'Anexo: Fotograf&iacute;as del cultivo afectado',
  ],
]);

$htmlPDF = '<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">
<style>
body { font-family: DejaVu Sans, Arial, sans-serif; font-size:12px; color: #222; margin: 0; }
h2 { font-size:14px; color: #1b4332; border-bottom: 2px solid #2d6a4f; padding-bottom: 4px; margin-top: 16px; margin-bottom: 8px; text-transform: uppercase; }
h3 { font-size:12.5px; color: #2d6a4f; margin-top: 12px; margin-bottom: 6px; }
.indice { background: #f0faf3; border: 1px solid #c3dac8; border-radius: 4px; padding: 10px 14px; margin-bottom: 16px; }
.indice h3 { margin-top: 0; }
.indice ol { margin: 0; padding-left: 18px; }
.indice li { margin-bottom: 2px; font-size:11.5px; }
.num-exp { background: #1b4332; color: #fff; border-radius: 4px; padding: 4px 10px; font-weight: bold; font-size:13px; display: inline-block; margin-bottom: 8px; }
table.datos { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size:11.5px; }
table.datos th { background: #f0f4f0; color: #1b4332; padding: 4px 7px; text-align: left; border: 1px solid #c8ddd3; font-weight: bold; width: 38%; }
table.datos td { padding: 4px 7px; border: 1px solid #c8ddd3; }
.firma-bloque { margin-top: 20px; text-align: right; font-size:12px; color: #555; }
p { margin: 0 0 6px; line-height: 1.55; text-align: justify; }
.sec-body { font-size:12px; line-height: 1.55; text-align: justify; margin-bottom: 10px; }
</style>
</head><body>

<div class="num-exp"># ' . m3_h($numExpediente) . '</div>
<h1 style="font-size:16px;color:#1b4332;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;">
  Informe T&eacute;cnico: Evaluaci&oacute;n de Da&ntilde;os por Borrascas
</h1>
<div style="font-size:11.5px;color:#666;margin-bottom:12px;">' . m3_h(M3_TITULO_CAMPANA) . ' &middot; ' . m3_h(M3_PROVINCIA) . ' &middot; ' . $fechaHoy . '</div>

<h2>1. Datos del solicitante</h2>
<h3>Titular</h3>
<table class="datos">
  <tr><th>Nombre / Raz&oacute;n Social</th><td>' . m3_h($razonSocial) . '</td></tr>
  <tr><th>DNI / CIF / NIE</th><td>' . m3_h($cifNif) . '</td></tr>
</table>
' . (($repNombre || $repDni) ? '
<h3>Representante</h3>
<table class="datos">
  ' . ($repNombre ? '<tr><th>Nombre y apellidos</th><td>' . m3_h($repNombre) . '</td></tr>' : '') . '
  ' . ($repDni ? '<tr><th>DNI/NIE</th><td>' . m3_h($repDni) . '</td></tr>' : '') . '
</table>' : '') . '
<h3>Domicilio</h3>
<table class="datos">
  <tr><th>Direcci&oacute;n</th><td>' . m3_h($calle . ', ' . $numero . ($bloque ? ', ' . $bloque : '') . ($piso ? ', ' . $piso : '')) . '</td></tr>
  <tr><th>C&oacute;digo Postal</th><td>' . m3_h($codigoPostal) . '</td></tr>
  <tr><th>Municipio / Provincia</th><td>' . m3_h($municipio) . ' (' . m3_h($provincia) . ')</td></tr>
  <tr><th>Tel&eacute;fono m&oacute;vil</th><td>' . m3_h($telefono) . '</td></tr>
  <tr><th>Correo electr&oacute;nico</th><td>' . m3_h($email) . '</td></tr>
</table>

<h2>2. Objeto del informe</h2>
<div class="sec-body">' . $textoObjeto . '</div>

<h2>3. Datos de la explotaci&oacute;n</h2>
<table class="datos">
  <tr><th>C&oacute;d. REAFA</th><td>' . m3_h($reafa) . '</td></tr>
  <tr><th>Provincia</th><td>' . m3_h($expProvincia) . '</td></tr>
  <tr><th>Municipio</th><td>' . m3_h($expMunicipio) . '</td></tr>
  <tr><th>Localidad</th><td>' . m3_h($expLocalidad) . '</td></tr>
  <tr><th>Cultivo</th><td>' . m3_h($cultivo) . '</td></tr>
  <tr><th>Variedad</th><td>' . m3_h($variedad) . '</td></tr>
  <tr><th>Edad del cultivo</th><td>' . ($edadCultivo > 0 ? $edadCultivo . ' a&ntilde;os' : '—') . '</td></tr>
  <tr><th>Sup. Secano</th><td>' . m3_ha($supSecano) . ($supSecanoTipoLabel ? ' &ndash; ' . m3_h($supSecanoTipoLabel) : '') . '</td></tr>
  <tr><th>Sup. Regad&iacute;o</th><td>' . m3_ha($supRegadio) . ($supRegadioTipoLabel ? ' &ndash; ' . m3_h($supRegadioTipoLabel) : '') . '</td></tr>
  <tr><th>Superficie total</th><td><strong>' . m3_ha($supTotal) . '</strong></td></tr>
  <tr><th>Sistema de explotaci&oacute;n</th><td>' . m3_h($sistExplotacion) . '</td></tr>
  <tr><th>Sistema de cultivo</th><td>' . m3_h($sistCultivoLabel) . '</td></tr>
</table>

<h2>4. Introducci&oacute;n y contexto meteorol&oacute;gico</h2>
<div class="sec-body">' . $textoIntro . '</div>

<h2>5. Metodolog&iacute;a y fuentes de informaci&oacute;n</h2>
<div class="sec-body">' . $textoMetodologia . '</div>

<h2>6. Descripci&oacute;n de da&ntilde;os</h2>
<div class="sec-body">' . $textoDescDanos . '</div>

<h2>7. Valoraci&oacute;n de da&ntilde;os y p&eacute;rdida de renta</h2>
<div class="sec-body">' . $textoValoracion . '</div>

<h2>8. Conclusi&oacute;n</h2>
<div class="sec-body">' . $textoConclu . '</div>

<!-- FIRMA -->
<div class="firma-bloque">
  <p>Firmado en Granada, a ' . $fechaHoy . '</p>
  ' . $firmaImgTag . '
  <p>' . m3_h($repNombre ?: $razonSocial) . '<br>' . m3_h($repDni ?: $cifNif) . '</p>
</div>

</body></html>';

/// ─── GENERAR PDF ──────────────────────────────────────────────────
$mpdf = new \Mpdf\Mpdf([
  'mode'          => 'utf-8',
  'format'        => 'A4',
  'margin_top'    => 28,
  'margin_bottom' => 15,
  'margin_left'   => 15,
  'margin_right'  => 15,
  'default_font'  => 'dejavusans',
  'tempDir'       => sys_get_temp_dir(),
  'img_dpi'       => 96,
  'dpi'           => 96,
]);
$mpdf->SetCompression(true);
$mpdf->SetTitle('Informe Daños Borrasca M2 – ' . $razonSocial);
$mpdf->SetAuthor('ACGranada');

// Separar las cadenas de la portada y del índice
$partesEstructura = explode('<!--SPLIT_PORTADA_INDICE-->', $htmlPortada);
$htmlSoloPortada   = $partesEstructura[0] ?? '';
$htmlSoloIndice    = $partesEstructura[1] ?? '';

// Texto del footer exclusivo para la Portada
$footerEmpresaPortada = '
<div style="text-align:center; font-family:DejaVu Sans,Arial,sans-serif; font-size:11.5px; color:#555; line-height:1.6;">
    Cooperativas Agro-alimentarias de Granada<br>
    C/Doctor L&oacute;pez Font, bajo 7 &ndash; Edif. Guadalquivir. C.P. 18004 &ndash; Granada<br>
    Tfno: 958 522 616 &ndash; Fax: 958 535 245<br>
    www.faecagranada.com
</div>';

// 1. PÁGINA 1: Imprimir Portada con pie de empresa (Sin cabecera)
$mpdf->SetHTMLFooter($footerEmpresaPortada);
$mpdf->WriteHTML('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head><body>' . $htmlSoloPortada . '</body></html>');

// 2. PÁGINA 2: Imprimir Índice (Sin pie de empresa y Sin cabecera)
$mpdf->AddPage();
$mpdf->SetHTMLFooter(''); // Limpia el footer de la empresa para la página del índice
$mpdf->WriteHTML('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head><body>' . $htmlSoloIndice . '</body></html>');

// 3. PÁGINA 3 EN ADELANTE: Activar Cabecera y Pie Globales del informe
$mpdf->SetHTMLHeader($headerHtml);
$mpdf->SetHTMLFooter($footerHtml);
$mpdf->WriteHTML($htmlPDF);

if (!empty($imagenesBase)) {
  $mpdf->WriteHTML($htmlImagenes);
}


// ─── GUARDAR PDF CON NOMBRE DE EXPEDIENTE ──────────────────────────
$nombrePDFBase = $numExpediente;
$nombrePDF     = $nombrePDFBase . ($firmaInicial ? '_firmado' : '') . '.pdf';
$rutaPDF       = $carpetaUsuario . $nombrePDF;
$mpdf->Output($rutaPDF, \Mpdf\Output\Destination::FILE);

$nombreHtmlFuente = $nombrePDFBase . '.html';
$htmlFuente = str_replace($firmaImgTag, '##FIRMA##', $htmlPDF);
file_put_contents($carpetaUsuario . $nombreHtmlFuente, $htmlFuente, LOCK_EX);

// ─── REGISTRAR EN REGISTRO_JSON GENERAL ──────────────────────────
// ─── GESTIÓN DE DOCUMENTOS ADJUNTOS / FACTURAS ─────────────────────
$adjuntosTmp = [];
if (!empty($_FILES['adjuntos']['name'][0])) {
  $archivosAdj = $_FILES['adjuntos'];
  $totalAdj    = count(array_filter($archivosAdj['name'], fn($n) => $n !== ''));
  if ($totalAdj > MAX_ADJUNTOS) {
    m3_redirect('Se permiten como máximo ' . MAX_ADJUNTOS . ' documentos adjuntos.');
  }
  for ($i = 0; $i < count($archivosAdj['name']); $i++) {
    if ($archivosAdj['error'][$i] !== UPLOAD_ERR_OK || $archivosAdj['name'][$i] === '') continue;
    if ($archivosAdj['size'][$i] > MAX_TAMANO_ADJUNTO) {
      m3_redirect('Uno o más adjuntos superan el tamaño máximo de 8 MB.');
    }
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($archivosAdj['tmp_name'][$i]);
    if (!array_key_exists($mimeReal, TIPOS_ADJUNTO)) {
      m3_redirect('Tipo de archivo no permitido en adjuntos. Solo PDF e imágenes (JPG, PNG, WebP).');
    }
    $ext            = TIPOS_ADJUNTO[$mimeReal];
    $nombreOriginal = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($archivosAdj['name'][$i]));
    $nombreOriginal = substr($nombreOriginal, 0, 100);
    $adjuntosTmp[]  = ['nombre' => $nombreOriginal, 'mime' => $mimeReal, 'ext' => $ext, 'tmp' => $archivosAdj['tmp_name'][$i]];
  }
}

$adjuntosGuardados = [];
if (!empty($adjuntosTmp)) {
  $carpetaAdj = $carpetaUsuario . $subfolderAdjNombre . '/';
  if (!is_dir($carpetaAdj)) {
    mkdir($carpetaAdj, 0755, true);
  }
  foreach ($adjuntosTmp as $adj) {
    $nombreServidor = 'adj_' . bin2hex(random_bytes(8)) . '.' . $adj['ext'];
    if (move_uploaded_file($adj['tmp'], $carpetaAdj . $nombreServidor)) {
      $adjuntosGuardados[] = ['nombre' => $adj['nombre'], 'archivo' => $nombreServidor, 'mime' => $adj['mime']];
    }
  }
}

$entrada = [
  'expediente'          => $numExpediente,
  'expediente_base'     => $expedienteBase,
  'revision'            => $revision,
  'es_revision'         => $expInfo['es_revision'],
  'adjuntos'            => $adjuntosGuardados,
  'archivo'             => $nombrePDF,
  'carpeta'             => $cifNif,
  'modelo'              => '2',
  'modelo_id'           => 'M3',
  'dni'                 => $cifNif,
  'nombre'              => $razonSocial,
  'razon_social'        => $razonSocial,
  'cif_nif'             => $cifNif,
  'representante'       => $repNombre,
  'rep_dni'             => $repDni,
  'municipio'           => $municipio,
  'exp_municipio'       => $expMunicipio,
  'reafa'               => $reafa,
  'cultivo'             => $cultivo,
  'variedad'            => $variedad,
  'sup_total_ha'        => round($supTotal, 2),
  'total_eur'           => round($danoTotal, 2),
  'sistema_exp'         => $sistExplotacion,
  'sistema_cultivo'     => $sistCultivoLabel,
  'html_fuente'         => $nombreHtmlFuente,
  'firmado'             => $firmaInicial,
  'firma_fecha'         => $firmaInicial ? date('Y-m-d H:i:s') : '',
  'archivo_firmado'     => $firmaInicial ? $nombrePDF : '',
  'fecha'               => date('Y-m-d'),
  'hora'                => date('H:i:s'),
  'timestamp'           => time(),
];

array_unshift($registro, $entrada);
file_put_contents(REGISTRO_JSON, json_encode($registro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

// ─── LIMPIAR TEMPORALES ───────────────────────────────────────────
if ($logoImgPath && file_exists($logoImgPath)) unlink($logoImgPath);
if ($firmaImgPath && file_exists($firmaImgPath)) unlink($firmaImgPath);

$_SESSION['informe_ok_m3'] = [
  'nombre'     => $razonSocial,
  'expediente' => $numExpediente,
];
header('Location: index.php');
exit;
