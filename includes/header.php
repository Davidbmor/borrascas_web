<?php
/**
 * includes/header.php
 * Cabecera compartida para todos los modelos.
 * Variables esperadas antes del include:
 *   $pageTitle  – título de la página
 *   $modelLabel – etiqueta del modelo (ej: "Modelo 1")
 *   $backUrl    – URL para el botón "Volver" (null = sin botón)
 *   $assetBase  – prefijo de ruta a assets/ (ej: "../" cuando se llama desde modelo1/)
 *   $numExpediente – (opcional) número de expediente para mostrar en la cabecera
 */
$pageTitle    = $pageTitle    ?? 'Informe de Daños – ACGranada';
$modelLabel   = $modelLabel   ?? '';
$backUrl      = $backUrl      ?? '../landing.php';
$assetBase    = $assetBase    ?? '../';
$numExpediente = $numExpediente ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <?php include __DIR__ . '/css.php'; ?>
</head>
<body>

<header class="site-header py-2 mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

            <!-- Logo + textos -->
            <div class="d-flex align-items-center gap-3">
                <a href="<?= htmlspecialchars($backUrl) ?>" class="d-flex align-items-center text-decoration-none">
                    <img src="<?= htmlspecialchars($assetBase) ?>assets/img/Faeca.png"
                         alt="ACGranada" class="site-logo" style="height:52px;">
                </a>
                <div>
                    <div class="site-title-main fw-bold">Informes de Daños por Borrasca</div>
                    <div class="site-title-sub text-muted">Cooperativas Agro-alimentarias de Granada</div>
                </div>
            </div>

            <!-- Navegación derecha -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if ($numExpediente): ?>
                    <span class="badge bg-success fs-6 px-3 py-2">
                        <i class="bi bi-hash me-1"></i>Exp. <?= htmlspecialchars($numExpediente) ?>
                    </span>
                <?php endif; ?>
                <?php if ($modelLabel): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                        <i class="bi bi-file-earmark-text me-1"></i><?= htmlspecialchars($modelLabel) ?>
                    </span>
                <?php endif; ?>
                <?php if ($backUrl): ?>
                    <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-grid me-1"></i>Cambiar modelo
                    </a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($assetBase) ?>admin.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-shield-lock me-1"></i>Admin
                </a>
            </div>
        </div>
    </div>
</header>
