<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

define('M3_LABEL',  'Modelo 3');
define('M3_TITULO', 'Evaluación de Daños en Espárrago');

define('M3_INFORMES_DIR',   __DIR__ . '/../informes3/');
define('M3_REGISTRO_JSON',  M3_INFORMES_DIR . 'registro.json');

define('M3_TITULO_CAMPANA',    'Campaña Espárrago 2026');
define('M3_PROVINCIA',         'Granada');
define('M3_REAL_DECRETO',      'Real Decreto-ley 5/2026, de 17 de febrero');

define('M3_PRECIO_KG',         3.16);
define('M3_PRECIO_KG_PROX',    3.18);
define('M3_DEPRECIACION_KG',   1.50);
define('M3_COSTE_COOP_KG',     0.15);
define('M3_COSTE_REC_HA',      6694.00);
define('M3_PCT_SOBRECOSTE_REC', 0.30); // 30% medio

define('M3_SISTEMAS_CULTIVO', [
    'Secano'  => 'Secano',
    'Regadío' => 'Regadío',
    'Mixto'   => 'Mixto'
]);
