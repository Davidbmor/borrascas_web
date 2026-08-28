<?php
/**
 * informe_estructura.php
 * Portada e índice reutilizables para el PDF de cualquier modelo de informe.
 */
declare(strict_types=1);

function pa_generar_portada_indice(array $datos): string
{
    $solicitante   = htmlspecialchars((string)($datos['solicitante']   ?? ''));
    $documento     = htmlspecialchars((string)($datos['documento']     ?? ''));
    $expediente    = htmlspecialchars((string)($datos['expediente']    ?? ''));
    $fecha         = htmlspecialchars((string)($datos['fecha']         ?? date('d/m/Y')));
    $indiceItems   = $datos['indice'] ?? [];

    // Logo principal
    $logoCoverPath = $datos['logo_cover_path'] ?? '';
    if ($logoCoverPath === '' || !file_exists($logoCoverPath)) {
        $candidates = [
            realpath(__DIR__ . '/../assets/img/FaecaAGRO360Transparente.jpg'),
            realpath(__DIR__ . '/../assets/img/FaecaAGRO360Transparente.png'),
        ];
        foreach ($candidates as $c) {
            if ($c !== false && file_exists($c)) { $logoCoverPath = $c; break; }
        }
    }

    $logoHtml = '';
    if ($logoCoverPath !== '' && file_exists($logoCoverPath)) {
        $rawBytes = file_get_contents($logoCoverPath);
        $src = $rawBytes !== false ? @imagecreatefromstring($rawBytes) : false;
        if ($src !== false) {
            $maxW = 500; $w = imagesx($src); $h = imagesy($src);
            if ($w > $maxW) {
                $nw = $maxW; $nh = (int)round($h * $maxW / $w);
                $res = imagecreatetruecolor($nw, $nh);
                imagefill($res, 0, 0, imagecolorallocate($res, 255, 255, 255));
                imagecopyresampled($res, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($src); $src = $res;
            } else {
                $bg = imagecreatetruecolor($w, $h);
                imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
                imagecopy($bg, $src, 0, 0, 0, 0, $w, $h);
                imagedestroy($src); $src = $bg;
            }
            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'portada_logo_' . bin2hex(random_bytes(4)) . '.jpg';
            imagejpeg($src, $tmp, 85);
            imagedestroy($src);
            $logoHtml = '<img src="' . $tmp . '" style="height:75px;width:auto;" />';
        }
    }

    $filasIndice = '';
    foreach ($indiceItems as $item) {
        $filasIndice .= '<li>' . $item . '</li>';
    }

    $datosSolicitante = '';
    if ($solicitante !== '') $datosSolicitante .= '<p class="p-dat"><strong>Solicitante:</strong> ' . $solicitante . '</p>';
    if ($documento   !== '') $datosSolicitante .= '<p class="p-dat"><strong>DNI / CIF / NIE:</strong> ' . $documento . '</p>';
    if ($expediente  !== '') $datosSolicitante .= '<p class="p-dat"><strong>N&ordm;&nbsp;Expediente:</strong> ' . $expediente . '</p>';
    if ($fecha       !== '') $datosSolicitante .= '<p class="p-dat"><strong>Fecha:</strong> ' . $fecha . '</p>';

    return '
<style>
  .portada-logo-wrap { text-align: left; margin-bottom: 0; }
  
  .tabla-portada-contenedor { 
    width: 100%; 
    height: 170mm; 
    border-collapse: collapse; 
  }
  
  .celda-portada-padre { 
    vertical-align: middle; 
    text-align: center; 
  }

  /* ESTILOS DE TEXTO CON ESPACIADO DE SEGURIDAD */
  .portada-pretitulo { font-size:18px; color:#2d6a4f; font-weight:bold; margin:0 0 8px 0; letter-spacing:.04em; }
  .portada-titulo { font-size:28px; color:#1b4332; font-weight:bold; text-transform:uppercase; letter-spacing:.06em; margin:0; line-height:1.35; }
  .portada-objeto { font-size:13.5px; color:#444; line-height:1.65; margin:0 20px; }

  .portada-datos { text-align:center; }
  .p-dat { font-size:15px; color:#222; margin:6px 0; }
  .p-dat strong { color:#1b4332; }

  /* ÍNDICE */
  .indice-logo-wrap { text-align:left; margin-bottom:20px; }
  .indice-titulo { font-size:24px; color:#1b4332; font-weight:bold; text-transform:uppercase;
                   letter-spacing:.05em; border-bottom:3px solid #2d6a4f; padding-bottom:8px; margin-bottom:24px; }
  .indice-lista { font-size:16px; color:#222; line-height:2.4; padding-left:24px; }
</style>

<!-- PORTADA -->
<div class="portada-logo-wrap">' . $logoHtml . '</div>

<table class="tabla-portada-contenedor">
  <tr>
    <td class="celda-portada-padre" height="170mm">
      
      <!-- Tabla interna para estructurar las distancias en milímetros -->
      <table style="width:100%; border-collapse:collapse;">
        
        <!-- 1. TÍTULO -->
        <tr>
          <td style="text-align:center; padding-bottom:12mm;">
            <p class="portada-pretitulo">Informe T&eacute;cnico:</p>
            <p class="portada-titulo">Evaluaci&oacute;n de Da&ntilde;os<br>por Borrascas</p>
          </td>
        </tr>

        <!-- 2. OBJETO DEL INFORME -->
        <tr>
          <td style="text-align:center; padding-bottom:12mm;">
            <p class="portada-objeto">
              El presente informe tiene por objeto cuantificar, con base objetiva y t&eacute;cnica, los da&ntilde;os econ&oacute;micos ocasionados en la explotaci&oacute;n agr&iacute;cola del solicitante como consecuencia de los episodios de borrascas registrados en la provincia de Granada, conforme a la normativa vigente.
            </p>
          </td>
        </tr>

        <!-- 3. LÍNEA DIVISORIA VERDE (Pintada directamente sobre el borde superior de la celda) -->
        <tr>
          <td style="padding-bottom:12mm;">
            <table style="width:90%; margin:0 auto; border-collapse:collapse;">
              <tr>
                <td style="border-top:2px solid #2d6a4f; height:1px; font-size:1px; line-height:1px;">&nbsp;</td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- 4. DATOS DEL SOLICITANTE -->
        <tr>
          <td style="text-align:center;">
            <div class="portada-datos">' . $datosSolicitante . '</div>
          </td>
        </tr>

      </table>

    </td>
  </tr>
</table>

<!-- FIN PORTADA -->
<!--SPLIT_PORTADA_INDICE-->

<!-- ÍNDICE -->
<div class="indice-logo-wrap">' . $logoHtml . '</div>
<div class="indice-titulo">&Iacute;ndice del informe</div>
<ol class="indice-lista">' . $filasIndice . '</ol>

<pagebreak />
';
}