<?php
declare(strict_types=1);
ini_set('memory_limit', '1024M');
session_start();
require_once __DIR__ . '/config_m2.php';
require_once __DIR__ . '/../vendor/autoload.php';

// ─── Helpers ──────────────────────────────────────────────────────
function m2_eur(float $v): string { return number_format($v, 2, ',', '.') . ' €'; }
function m2_ha(float $v): string  { return number_format($v, 2, ',', '.') . ' ha'; }
function m2_redirect(string $msg): never {
    $_SESSION['form_error'] = $msg;
    header('Location: index.php');
    exit;
}
function m2_h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ─── CSRF ─────────────────────────────────────────────────────────
$tokenEnviado = $_POST['csrf_token'] ?? '';
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $tokenEnviado)) {
    m2_redirect('Token de seguridad inválido. Recarga la página.');
}

// ─── SANITIZACIÓN ─────────────────────────────────────────────────
$razonSocial      = trim((string)($_POST['razon_social']         ?? ''));
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
$cultivo          = trim((string)($_POST['cultivo']             ?? 'Olivar'));
$variedad         = trim((string)($_POST['variedad']            ?? ''));
$edadCultivo      = (int)($_POST['edad_cultivo']               ?? 0);
$supSecano        = abs((float)($_POST['sup_secano']            ?? 0));
$supSecanoTipo    = trim((string)($_POST['sup_secano_tipo']     ?? ''));
$supRegadio       = abs((float)($_POST['sup_regadio']           ?? 0));
$supRegadioTipo   = trim((string)($_POST['sup_regadio_tipo']    ?? ''));
$sistCultivo      = trim((string)($_POST['sistema_cultivo']     ?? ''));
$firmaDataUri     = trim((string)($_POST['firma_data']          ?? ''));

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

$sistCultivoLabel = M2_SISTEMAS_CULTIVO[$sistCultivo] ?? $sistCultivo;
$supSecanoTipoLabel = M2_SISTEMAS_CULTIVO[$supSecanoTipo] ?? $supSecanoTipo;
$supRegadioTipoLabel = M2_SISTEMAS_CULTIVO[$supRegadioTipo] ?? $supRegadioTipo;

// ─── VALIDACIÓN ───────────────────────────────────────────────────
$errores = [];
if (empty($razonSocial))   $errores[] = 'La razón social es obligatoria.';
if (empty($cifNif))        $errores[] = 'El CIF/NIF es obligatorio.';
if (empty($repNombre))     $errores[] = 'El nombre del representante es obligatorio.';
if (empty($repDni))        $errores[] = 'El DNI/NIE del representante es obligatorio.';
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
if (empty($firmaDataUri) || !str_starts_with($firmaDataUri, 'data:image/png;base64,')) {
    $errores[] = 'Debes firmar el informe antes de enviarlo.';
}
if (!empty($errores)) {
    m2_redirect(implode(' ', $errores));
}

// ─── Número de expediente ─────────────────────────────────────────
if (!is_dir(M2_INFORMES_DIR)) {
    mkdir(M2_INFORMES_DIR, 0755, true);
}
$registro = [];
if (file_exists(M2_REGISTRO_JSON)) {
    $raw = file_get_contents(M2_REGISTRO_JSON);
    $registro = json_decode($raw, true) ?? [];
}
$seq = count($registro) + 1;
$numExpediente = 'M2-' . date('Y') . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

// ─── IMÁGENES ─────────────────────────────────────────────────────
$carpetaUsuario = M2_INFORMES_DIR . $cifNif . '/';
$carpetaImagenesPerm = $carpetaUsuario . 'imagenes/';
if (!is_dir($carpetaImagenesPerm)) {
    mkdir($carpetaImagenesPerm, 0755, true);
}

$imagenesBase = [];
if (!empty($_FILES['imagenes']['name'][0])) {
    $archivos = $_FILES['imagenes'];
    $total    = count($archivos['name']);
    if ($total > MAX_IMAGENES) {
        m2_redirect('Se permiten como máximo ' . MAX_IMAGENES . ' imágenes.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    for ($i = 0; $i < $total; $i++) {
        if ($archivos['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($archivos['size'][$i]  > MAX_TAMANO_IMG) {
            m2_redirect('Una o más imágenes superan el tamaño máximo de 8 MB.');
        }
        $mimeReal = $finfo->file($archivos['tmp_name'][$i]);
        if (!in_array($mimeReal, TIPOS_IMAGEN, true)) {
            m2_redirect('Solo se aceptan imágenes JPG, PNG o WebP.');
        }
        $permRuta = $carpetaImagenesPerm . 'img_' . bin2hex(random_bytes(8)) . '.jpg';
        $srcPath  = $archivos['tmp_name'][$i];
        $img = match($mimeReal) {
            'image/jpeg' => @imagecreatefromjpeg($srcPath),
            'image/png'  => @imagecreatefrompng($srcPath),
            'image/webp' => @imagecreatefromwebp($srcPath),
            default      => false,
        };
        if ($img === false) {
            move_uploaded_file($srcPath, $permRuta);
        } else {
            $maxDim = 1200; $w = imagesx($img); $h = imagesy($img);
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
$firmaBase64  = substr($firmaDataUri, strlen('data:image/png;base64,'));
$firmaDecoded = base64_decode($firmaBase64, true);
$firmaImgPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'borrascas_m2_firma_' . bin2hex(random_bytes(4)) . '.png';
file_put_contents($firmaImgPath, $firmaDecoded);

// ─── LOGO ─────────────────────────────────────────────────────────
$logoPath    = __DIR__ . '/../assets/img/Faeca.png';
$logoImgPath = '';
if (file_exists($logoPath)) {
    $logoSrc = @imagecreatefrompng($logoPath);
    if ($logoSrc) {
        $lw = imagesx($logoSrc); $lh = imagesy($logoSrc);
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
        $logoImgPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'borrascas_m2_logo_' . bin2hex(random_bytes(4)) . '.jpg';
        imagejpeg($logoSrc, $logoImgPath, 85);
        imagedestroy($logoSrc);
    }
}
$logoHtml = $logoImgPath ? '<img src="' . $logoImgPath . '" style="height:55px;width:auto;" />' : '';

$domicilioCompleto = $calle . ', ' . $numero
    . ($bloque ? ', ' . $bloque : '')
    . ($piso   ? ', ' . $piso   : '')
    . ' – ' . $codigoPostal . ' ' . $municipio . ' (' . $provincia . ')';

$fechaHoy = date('d/m/Y');

// ─── HTML DEL PDF ─────────────────────────────────────────────────
$headerHtml = '
<table style="width:100%;border-bottom:3px solid #2d6a4f;padding-bottom:6px;margin-bottom:0;font-family:DejaVu Sans,Arial,sans-serif;">
  <tr>
    <td style="width:70px;vertical-align:middle;">' . $logoHtml . '</td>
    <td style="vertical-align:middle;padding-left:10px;">
      <div style="font-size:13px;font-weight:bold;color:#1b4332;text-transform:uppercase;letter-spacing:.03em;">INFORME DE DA&Ntilde;OS POR BORRASCA</div>
      <div style="font-size:10px;color:#555;margin-top:2px;">' . htmlspecialchars(M2_TITULO_CAMPANA) . ' &middot; ' . htmlspecialchars(M2_LABEL) . '</div>
    </td>
    <td style="width:80px;text-align:right;vertical-align:top;font-size:9px;color:#888;">' . $fechaHoy . '</td>
  </tr>
</table>';

$htmlImagenes = '';
if (!empty($imagenesBase)) {
    $colWidthMm = 87.0; $marginBt = 5.0;
    $colLeft = []; $colRight = []; $altL = 0.0; $altR = 0.0;
    foreach ($imagenesBase as $img) {
        $info = @getimagesize($img['ruta']);
        $dh   = ($info && $info[0] > 0) ? ($colWidthMm * $info[1] / $info[0]) : 60.0;
        if ($altL <= $altR) { $colLeft[]  = $img; $altL += $dh + $marginBt; }
        else                { $colRight[] = $img; $altR += $dh + $marginBt; }
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

// ─── Texto del objeto ─────────────────────────────────────────────
$textoObjeto = 'El objeto del presente informe es cuantificar, con una base objetiva y t&eacute;cnica, los da&ntilde;os econ&oacute;micos ocasionados en el contexto de un tren de borrascas atl&aacute;nticas de gran intensidad que incidi&oacute; de forma reiterada sobre Andaluc&iacute;a durante enero y febrero de 2026 y que incidi&oacute; en la explotaci&oacute;n agr&iacute;cola cuyo titular es <strong>' . m2_h($razonSocial) . '</strong>, ubicada en la localidad de <strong>' . m2_h($localidadExp) . '</strong> en la comarca <strong>' . m2_h($comarca) . '</strong> de ' . m2_h($provinciaExp) . '. Dicha explotaci&oacute;n se encuentra ubicada dentro de la relaci&oacute;n de municipios afectados por las borrascas.<br><br>
Se proceder&aacute; a la descripci&oacute;n de los da&ntilde;os, su diagn&oacute;stico y la valoraci&oacute;n econ&oacute;mica asociada, con motivo del evento adverso acontecido. Todo ello con base en la informaci&oacute;n proporcionada por la persona titular o representante de la explotaci&oacute;n.<br><br>
Este informe se enmarca en el ' . m2_h(M2_REAL_DECRETO) . ', por el que se adoptan medidas urgentes en respuesta a los da&ntilde;os causados por diversos fen&oacute;menos meteorol&oacute;gicos adversos, con especial afectaci&oacute;n en las comunidades aut&oacute;nomas de Andaluc&iacute;a y Extremadura.';

// Textos fijos (secciones 4-8 resumidas para el PDF)
$textoIntro = 'Durante el mes de enero y primeros d&iacute;as de febrero del a&ntilde;o 2026 se produjeron diversos episodios meteorol&oacute;gicos adversos en la provincia de Granada, caracterizados por precipitaciones intensas y persistentes, acompa&ntilde;adas en algunos momentos de fuertes rachas de viento. Seg&uacute;n el avance climatol&oacute;gico de AEMET para Andaluc&iacute;a, enero de 2026 fue un mes muy h&uacute;medo con una media de 132,6 mm de lluvia (320&nbsp;% de la cantidad habitual). Febrero de 2026 fue extremadamente h&uacute;medo con 158,2 mm de lluvia (436&nbsp;% de la habitual). Las persistentes lluvias ocasionaron encharcamientos, arrastres de suelo, da&ntilde;os en caminos rurales y p&eacute;rdidas en cultivos.';

$textoMetodologia = 'Este apartado incorpora al informe un an&aacute;lisis objetivo del comportamiento de la campa&ntilde;a agr&iacute;cola. El da&ntilde;o econ&oacute;mico incorpora tanto las p&eacute;rdidas directas como los da&ntilde;os diferidos y los incrementos de costes. Se distinguen cinco componentes fundamentales: (1)&nbsp;p&eacute;rdidas de producci&oacute;n de la campa&ntilde;a afectada; (2)&nbsp;p&eacute;rdidas de producci&oacute;n de la campa&ntilde;a siguiente; (3)&nbsp;depreciaci&oacute;n de la calidad comercial; (4)&nbsp;incremento de costes de recolecci&oacute;n y producci&oacute;n; (5)&nbsp;costes extraordinarios de recuperaci&oacute;n de la explotaci&oacute;n.';

$textoDescDanos = '<em>Secci&oacute;n en preparaci&oacute;n. Se incluir&aacute; la descripci&oacute;n detallada de los da&ntilde;os observados en la explotaci&oacute;n en pr&oacute;ximas versiones del informe.</em>';
$textoValoracion = '<em>Secci&oacute;n en preparaci&oacute;n. La valoraci&oacute;n econ&oacute;mica se calcular&aacute; cuando se faciliten los par&aacute;metros de campa&ntilde;a del Modelo 2.</em>';
$textoConclu = 'Con base en la evidencia cient&iacute;fica, la informaci&oacute;n meteorol&oacute;gica oficial y los datos aportados por la persona titular, se concluye que la explotaci&oacute;n objeto del presente informe ha sufrido da&ntilde;os econ&oacute;micos derivados de los episodios de lluvias intensas y persistentes registrados en la provincia de Granada durante enero y febrero de 2026, tal y como se acredita en los apartados anteriores.';

$firmaImgTag = '<img src="' . $firmaImgPath . '" style="max-width:220px;max-height:80px;margin:8px 0 4px auto;display:block;" />';

$htmlPDF = '<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">
<style>
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #222; margin: 0; }
h2 { font-size: 13px; color: #1b4332; border-bottom: 2px solid #2d6a4f; padding-bottom: 4px; margin-top: 18px; margin-bottom: 8px; }
h3 { font-size: 11px; color: #2d6a4f; margin-top: 14px; margin-bottom: 6px; }
.indice { background: #f0faf3; border: 1px solid #c3dac8; border-radius: 4px; padding: 10px 14px; margin-bottom: 16px; }
.indice h3 { margin-top: 0; }
.indice ol { margin: 0; padding-left: 18px; }
.indice li { margin-bottom: 2px; font-size: 9.5px; }
.num-exp { background: #1b4332; color: #fff; border-radius: 4px; padding: 4px 10px; font-weight: bold; font-size: 11px; display: inline-block; margin-bottom: 8px; }
table.datos { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
table.datos th { background: #f0f4f0; color: #1b4332; padding: 4px 7px; text-align: left; border: 1px solid #c8ddd3; font-weight: bold; width: 38%; font-size: 9px; }
table.datos td { padding: 4px 7px; border: 1px solid #c8ddd3; font-size: 9.5px; }
.firma-bloque { margin-top: 20px; text-align: right; font-size: 10px; color: #555; }
.firma-linea  { border-top: 1px solid #888; width: 200px; margin: 24px 0 4px auto; }
p { margin: 0 0 6px; line-height: 1.55; }
</style>
</head><body>

<div class="num-exp"># ' . m2_h($numExpediente) . '</div>
<h1 style="font-size:14px;color:#1b4332;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;">
  Informe T&eacute;cnico: Evaluaci&oacute;n de Da&ntilde;os por Borrascas
</h1>
<div style="font-size:9.5px;color:#666;margin-bottom:12px;">' . m2_h(M2_TITULO_CAMPANA) . ' &middot; ' . m2_h(M2_PROVINCIA) . ' &middot; ' . $fechaHoy . '</div>

<div class="indice">
  <h3>Contenido</h3>
  <ol>
    <li>Datos del solicitante</li>
    <li>Objeto del informe</li>
    <li>Datos de la explotaci&oacute;n</li>
    <li>Introducci&oacute;n y contexto meteorol&oacute;gico</li>
    <li>Metodolog&iacute;a y fuentes de informaci&oacute;n</li>
    <li>Descripci&oacute;n de da&ntilde;os</li>
    <li>Valoraci&oacute;n de da&ntilde;os y p&eacute;rdida de renta</li>
    <li>Conclusi&oacute;n</li>
    <li>Anexo: Fotograf&iacute;as del cultivo afectado</li>
  </ol>
</div>

<h2>1. Datos del solicitante</h2>
<h3>Titular</h3>
<table class="datos">
  <tr><th>Raz&oacute;n Social</th><td>' . m2_h($razonSocial) . '</td></tr>
  <tr><th>CIF/NIF</th><td>' . m2_h($cifNif) . '</td></tr>
</table>
<h3>Representante</h3>
<table class="datos">
  <tr><th>Nombre y apellidos</th><td>' . m2_h($repNombre) . '</td></tr>
  <tr><th>DNI/NIE</th><td>' . m2_h($repDni) . '</td></tr>
</table>
<h3>Domicilio</h3>
<table class="datos">
  <tr><th>Direcci&oacute;n</th><td>' . m2_h($calle . ', ' . $numero . ($bloque ? ', ' . $bloque : '') . ($piso ? ', ' . $piso : '')) . '</td></tr>
  <tr><th>C&oacute;digo Postal</th><td>' . m2_h($codigoPostal) . '</td></tr>
  <tr><th>Municipio / Provincia</th><td>' . m2_h($municipio) . ' (' . m2_h($provincia) . ')</td></tr>
  <tr><th>Tel&eacute;fono m&oacute;vil</th><td>' . m2_h($telefono) . '</td></tr>
  <tr><th>Correo electr&oacute;nico</th><td>' . m2_h($email) . '</td></tr>
</table>

<h2>2. Objeto del informe</h2>
<p>' . $textoObjeto . '</p>

<h2>3. Datos de la explotaci&oacute;n</h2>
<table class="datos">
  <tr><th>C&oacute;d. REAFA</th><td>' . m2_h($reafa) . '</td></tr>
  <tr><th>Provincia</th><td>' . m2_h($expProvincia) . '</td></tr>
  <tr><th>Municipio</th><td>' . m2_h($expMunicipio) . '</td></tr>
  <tr><th>Localidad</th><td>' . m2_h($expLocalidad) . '</td></tr>
  <tr><th>Cultivo</th><td>' . m2_h($cultivo) . '</td></tr>
  <tr><th>Variedad</th><td>' . m2_h($variedad) . '</td></tr>
  <tr><th>Edad del cultivo</th><td>' . ($edadCultivo > 0 ? $edadCultivo . ' a&ntilde;os' : '—') . '</td></tr>
  <tr><th>Sup. Secano</th><td>' . m2_ha($supSecano) . ($supSecanoTipoLabel ? ' &ndash; ' . m2_h($supSecanoTipoLabel) : '') . '</td></tr>
  <tr><th>Sup. Regad&iacute;o</th><td>' . m2_ha($supRegadio) . ($supRegadioTipoLabel ? ' &ndash; ' . m2_h($supRegadioTipoLabel) : '') . '</td></tr>
  <tr><th>Superficie total</th><td><strong>' . m2_ha($supTotal) . '</strong></td></tr>
  <tr><th>Sistema de explotaci&oacute;n</th><td>' . m2_h($sistExplotacion) . '</td></tr>
  <tr><th>Sistema de cultivo</th><td>' . m2_h($sistCultivoLabel) . '</td></tr>
</table>

<h2>4. Introducci&oacute;n y contexto meteorol&oacute;gico</h2>
<p>' . $textoIntro . '</p>

<h2>5. Metodolog&iacute;a y fuentes de informaci&oacute;n</h2>
<p>' . $textoMetodologia . '</p>

<h2>6. Descripci&oacute;n de da&ntilde;os</h2>
<p>' . $textoDescDanos . '</p>

<h2>7. Valoraci&oacute;n de da&ntilde;os y p&eacute;rdida de renta</h2>
<p>' . $textoValoracion . '</p>

<h2>8. Conclusi&oacute;n</h2>
<p>' . $textoConclu . '</p>

<!-- FIRMA -->
<div class="firma-bloque">
  <p>Firmado en Granada, a ' . $fechaHoy . '</p>
  ' . $firmaImgTag . '
  <p>' . m2_h($repNombre) . '<br>' . m2_h($repDni) . '</p>
</div>

</body></html>';

// ─── GENERAR PDF ──────────────────────────────────────────────────
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
$mpdf->SetTitle('Informe Daños Borrasca M2 – ' . $razonSocial);
$mpdf->SetAuthor('ACGranada');
$mpdf->WriteHTML($htmlPDF);
if (!empty($imagenesBase)) {
    $mpdf->WriteHTML($htmlImagenes);
}

// ─── GUARDAR PDF ──────────────────────────────────────────────────
$nombrePDF = 'informe_m2_' . $cifNif . '_' . date('Ymd_His') . '.pdf';
$rutaPDF   = $carpetaUsuario . $nombrePDF;
$mpdf->Output($rutaPDF, \Mpdf\Output\Destination::FILE);

// ─── REGISTRAR ────────────────────────────────────────────────────
$entrada = [
    'expediente'       => $numExpediente,
    'archivo'          => $nombrePDF,
    'carpeta'          => $cifNif,
    'modelo'           => 'M2',
    'razon_social'     => $razonSocial,
    'cif_nif'          => $cifNif,
    'representante'    => $repNombre,
    'rep_dni'          => $repDni,
    'municipio'        => $municipio,
    'exp_municipio'    => $expMunicipio,
    'reafa'            => $reafa,
    'cultivo'          => $cultivo,
    'variedad'         => $variedad,
    'sup_total_ha'     => round($supTotal, 2),
    'sistema_exp'      => $sistExplotacion,
    'sistema_cultivo'  => $sistCultivoLabel,
    'firmado'          => true,
    'firma_fecha'      => date('Y-m-d H:i:s'),
    'archivo_firmado'  => '',
    'fecha'            => date('Y-m-d'),
    'hora'             => date('H:i:s'),
    'timestamp'        => time(),
];

array_unshift($registro, $entrada);
file_put_contents(M2_REGISTRO_JSON, json_encode($registro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

// ─── LIMPIAR TEMPORALES ───────────────────────────────────────────
if ($logoImgPath && file_exists($logoImgPath)) unlink($logoImgPath);
if ($firmaImgPath && file_exists($firmaImgPath)) unlink($firmaImgPath);

$_SESSION['informe_ok_m2'] = [
    'nombre'     => $razonSocial,
    'expediente' => $numExpediente,
];
header('Location: index.php');
exit;
