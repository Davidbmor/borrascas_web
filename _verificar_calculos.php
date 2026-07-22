<?php
require_once __DIR__ . '/config.php';

// Simula SIN previsión inicial (campo vacío = null)
$prevInicialKg = null;
$prodRealKg    = 20;
$recoleccionKg = 20;
$variosEur     = 20;

echo "=== CASO: sin previsión inicial (campo vacío) ===" . PHP_EOL . PHP_EOL;

// Igual que el Excel: D14 vacío → G14=0, I14=0
$prevInicialKgCalc = $prevInicialKg ?? 0.0;

echo "=== VALORES DE CONFIGURACIÓN ===" . PHP_EOL;
echo "Rendimiento medio:       " . RENDIMIENTO_MEDIO . "  (" . (RENDIMIENTO_MEDIO*100) . "%)" . PHP_EOL;
echo "Precio Kg AOVE:          " . PRECIO_KG_AOVE . " €" . PHP_EOL;
echo "Precio calidad aceite:   " . PRECIO_CALIDAD_ACEITE . " €/Kg" . PHP_EOL;
echo "Sobrecoste recolección:  " . SOBRECOSTE_RECOLECCION . " €/Kg aceituna" . PHP_EOL;
echo "Sobrecoste producción:   " . SOBRECOSTE_PRODUCCION . " €/Kg aceite" . PHP_EOL;

echo PHP_EOL . "=== CÁLCULOS WEB (sin previsión, resto = 20) ===" . PHP_EOL;

// G14, I14
$prevInicialAceite = $prevInicialKgCalc * RENDIMIENTO_MEDIO;
$prevInicialEur    = $prevInicialAceite * PRECIO_KG_AOVE;
echo "Prev. inicial aceite (G14): $prevInicialKgCalc × " . RENDIMIENTO_MEDIO . " = $prevInicialAceite Kg" . PHP_EOL;
echo "Prev. inicial euros  (I14): $prevInicialAceite × " . PRECIO_KG_AOVE . " = $prevInicialEur €" . PHP_EOL;

// G15, I15 - Producción real
$prodRealAceite = $prodRealKg * RENDIMIENTO_MEDIO;
$prodRealEur    = $prodRealAceite * PRECIO_KG_AOVE;
echo "Prod. real aceite    (G15): $prodRealKg × " . RENDIMIENTO_MEDIO . " = $prodRealAceite Kg" . PHP_EOL;
echo "Prod. real euros     (I15): $prodRealAceite × " . PRECIO_KG_AOVE . " = $prodRealEur €" . PHP_EOL;

// G16, I16 - Pérdidas
$perdidasAceiteKg = $prevInicialAceite - $prodRealAceite;
$perdidasEur      = $prevInicialEur    - $prodRealEur;
echo "Pérdidas aceite      (G16): $prevInicialAceite - $prodRealAceite = $perdidasAceiteKg Kg" . PHP_EOL;
echo "Pérdidas euros       (I16): $prevInicialEur - $prodRealEur = $perdidasEur €" . PHP_EOL;

// G18 - Recolección
$recoleccionAceite = $recoleccionKg * RENDIMIENTO_MEDIO;
echo "Recolección aceite   (G18): $recoleccionKg × " . RENDIMIENTO_MEDIO . " = $recoleccionAceite Kg" . PHP_EOL;

// I20 - Calidad
$calidadEur = $recoleccionAceite * PRECIO_CALIDAD_ACEITE;
echo "Calidad aceite       (I20): $recoleccionAceite × " . PRECIO_CALIDAD_ACEITE . " = $calidadEur €" . PHP_EOL;

// I21 - Sobrecoste recolección
$sobrecosteRecEur = $recoleccionKg * SOBRECOSTE_RECOLECCION;
echo "Sobrecoste recol.    (I21): $recoleccionKg × " . SOBRECOSTE_RECOLECCION . " = $sobrecosteRecEur €" . PHP_EOL;

// I22 - Sobrecoste producción
$sobrecosteProdEur = $recoleccionAceite * SOBRECOSTE_PRODUCCION;
echo "Sobrecoste prod.     (I22): $recoleccionAceite × " . SOBRECOSTE_PRODUCCION . " = $sobrecosteProdEur €" . PHP_EOL;

// I23 - Varios
echo "Varios               (I23): $variosEur €" . PHP_EOL;

// D25 - Total
$totalEur = $perdidasEur + $calidadEur + $sobrecosteRecEur + $sobrecosteProdEur + $variosEur;
echo PHP_EOL;
echo "TOTAL WEB (D25): $perdidasEur + $calidadEur + $sobrecosteRecEur + $sobrecosteProdEur + $variosEur = $totalEur €" . PHP_EOL;

echo PHP_EOL . "=== RESULTADO EXCEL (D25 sin previsión, resto = 20) ===" . PHP_EOL;
// I16 = 0 - 18.2776 = -18.2776
$excelTotal = (-18.2776) + (4.154 * 1.5) + (0.25 * 20) + (0.04 * 4.154) + 20;
echo "Excel D25 = -18.2776 + 6.231 + 5 + 0.16616 + 20 = $excelTotal €" . PHP_EOL;

echo PHP_EOL . "=== DIFERENCIA ===" . PHP_EOL;
$diff = abs($totalEur - $excelTotal);
echo "Diferencia: $diff €" . PHP_EOL;
if ($diff < 0.0001) {
    echo "✓ Los cálculos son IDÉNTICOS." . PHP_EOL;
} else {
    echo "✗ HAY DIFERENCIA." . PHP_EOL;
}
