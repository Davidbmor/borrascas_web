<?php
/**
 * modelo1/config_m1.php
 * Constantes específicas del Modelo 1 – Daños en producción oleícola
 * Campaña 2024/2025
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';  // constantes compartidas

// ─── Identificación del modelo ─────────────────────────────────────
define('M1_LABEL',    'Modelo 1');
define('M1_TITULO',   'Daños en Producción Oleícola');
define('M1_REGISTRO_JSON', INFORMES_DIR . 'registro.json');

// ─── Campaña ───────────────────────────────────────────────────────
define('M1_TITULO_CAMPANA',    'Campaña Oleícola 2024/2025');
define('M1_FECHA_RECOLECCION', '10/11/2025');

// ─── Datos técnicos de la campaña ──────────────────────────────────
define('M1_PREVISION_GRANADA_TM',  124000);   // Tm previstas
define('M1_CIERRE_GRANADA_TM',      98000);   // Tm cierre estimado
define('M1_BAJADA_PORCENTAJE',       20.97);  // % de bajada
define('M1_RENDIMIENTO_MEDIO',       0.2077); // 20,77 % kg aceite/kg aceituna
define('M1_PRECIO_KG_AOVE',           4.40);  // €/kg aceite
define('M1_PRECIO_CALIDAD_ACEITE',    1.50);  // €/kg aceite (diferencia calidad)
define('M1_SOBRECOSTE_RECOLECCION',   0.25);  // €/kg aceituna
define('M1_SOBRECOSTE_PRODUCCION',    0.04);  // €/kg aceite

// Alias retrocompatibles con procesar.php del modelo 1
// (por si se siguen usando las constantes sin prefijo M1_)
if (!defined('TITULO_CAMPANA'))       define('TITULO_CAMPANA',       M1_TITULO_CAMPANA);
if (!defined('FECHA_RECOLECCION'))    define('FECHA_RECOLECCION',    M1_FECHA_RECOLECCION);
if (!defined('PREVISION_GRANADA_TM')) define('PREVISION_GRANADA_TM', M1_PREVISION_GRANADA_TM);
if (!defined('CIERRE_GRANADA_TM'))    define('CIERRE_GRANADA_TM',    M1_CIERRE_GRANADA_TM);
if (!defined('BAJADA_PORCENTAJE'))    define('BAJADA_PORCENTAJE',    M1_BAJADA_PORCENTAJE);
if (!defined('RENDIMIENTO_MEDIO'))    define('RENDIMIENTO_MEDIO',    M1_RENDIMIENTO_MEDIO);
if (!defined('PRECIO_KG_AOVE'))       define('PRECIO_KG_AOVE',       M1_PRECIO_KG_AOVE);
if (!defined('PRECIO_CALIDAD_ACEITE'))define('PRECIO_CALIDAD_ACEITE',M1_PRECIO_CALIDAD_ACEITE);
if (!defined('SOBRECOSTE_RECOLECCION'))define('SOBRECOSTE_RECOLECCION',M1_SOBRECOSTE_RECOLECCION);
if (!defined('SOBRECOSTE_PRODUCCION')) define('SOBRECOSTE_PRODUCCION', M1_SOBRECOSTE_PRODUCCION);
