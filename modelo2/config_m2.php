<?php
/**
 * modelo2/config_m2.php
 * Constantes específicas del Modelo 2 – Evaluación de daños por borrascas (v2)
 * Se completarán los cálculos cuando se definan los valores de campaña.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';  // constantes compartidas

// ─── Identificación del modelo ─────────────────────────────────────
define('M2_LABEL',  'Modelo 2');
define('M2_TITULO', 'Evaluación de Daños por Borrascas');

// ─── Directorio de informes propio ─────────────────────────────────
define('M2_INFORMES_DIR',   __DIR__ . '/../informes2/');
define('M2_REGISTRO_JSON',  M2_INFORMES_DIR . 'registro.json');

// ─── Campaña ───────────────────────────────────────────────────────
define('M2_TITULO_CAMPANA',    'Campaña 2024/2025');
define('M2_FECHA_RECOLECCION', '10/11/2025');
define('M2_PROVINCIA',         'Granada');
define('M2_REAL_DECRETO',      'Real Decreto-ley 5/2026, de 17 de febrero');

// ─── Valores técnicos (pendientes de definir) ──────────────────────
// Se actualizarán cuando se faciliten los cálculos del modelo 2
define('M2_PREVISION_GRANADA_TM',  124000);
define('M2_RENDIMIENTO_MEDIO',     0.2077);
define('M2_PRECIO_KG_AOVE',        4.40);

// ─── Sistemas de cultivo olivar ────────────────────────────────────
define('M2_SISTEMAS_CULTIVO', [
    'OTNM' => 'Olivar Tradicional No Mecanizable (OTNM)',
    'OTM'  => 'Olivar Tradicional Mecanizable (OTM)',
    'OI'   => 'Olivar Intensivo (OI)',
    'OS'   => 'Olivar Superintensivo (OS)',
]);

// ─── Niveles de afección para daños diferidos ──────────────────────
define('M2_NIVELES_AFECCION', [
    'baja'    => ['label' => 'Baja (encharcamiento <10 días)',       'min' => 5,  'max' => 10],
    'moderada'=> ['label' => 'Moderada (encharcamientos repetidos)', 'min' => 10, 'max' => 20],
    'alta'    => ['label' => 'Alta (encharcamiento >20-30 días)',    'min' => 20, 'max' => 35],
    'muy_alta'=> ['label' => 'Muy alta (inundación prolongada)',     'min' => 35, 'max' => 50],
]);
