<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/functions.php';

$templates = pa_template_catalog();
$okMessage = pa_flash('ok');
$errorMessage = pa_flash('error');
$refMessage = (string)($_GET['ref'] ?? '');

?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= pa_h(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
    <main class="portal-shell">
        <section class="hero-panel">
            <div class="container py-4 py-lg-5">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <span class="eyebrow">Trámites online</span>
                        <h1 class="display-6 fw-bold mb-3"><?= pa_h(APP_NAME) ?></h1>
                        <p class="lead mb-0">Sube las plantillas PDF, define los campos y deja que la persona complete y firme el documento sin imprimir ni escanear.</p>
                    </div>
                    <a class="btn btn-outline-light btn-sm" href="admin.php"><i class="bi bi-shield-lock me-1"></i>Panel admin</a>
                </div>
            </div>
        </section>

        <section class="container py-4">
            <?php if ($okMessage || $refMessage !== ''): ?>
                <div class="alert alert-success shadow-sm">
                    <?= pa_h($okMessage ?? 'Documento generado correctamente.') ?>
                    <?php if ($refMessage !== ''): ?>
                        <div class="small mt-1">Referencia: <strong><?= pa_h($refMessage) ?></strong></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger shadow-sm"><?= pa_h($errorMessage) ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <?php if ($templates === []): ?>
                    <div class="col-12">
                        <div class="empty-state shadow-sm">
                            <h2 class="h4 fw-bold mb-2">Aún no hay plantillas cargadas</h2>
                            <p class="mb-2">Deja los PDFs plantilla en <strong>portal_altas_bajas/plantillas/</strong> y sus definiciones JSON en <strong>portal_altas_bajas/storage/definiciones/</strong>.</p>
                            <p class="mb-0">Cada definición debe indicar el PDF, los campos a rellenar y la posición de la firma.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                        <div class="col-12 col-lg-6">
                            <div class="card h-100 shadow-sm border-0 template-card">
                                <div class="card-body p-4 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                        <div>
                                            <h2 class="h4 fw-bold mb-1"><?= pa_h((string)($template['title'] ?? $template['slug'])) ?></h2>
                                            <p class="text-muted mb-0"><?= pa_h((string)($template['description'] ?? 'Formulario PDF editable y firmable en la web.')) ?></p>
                                        </div>
                                        <span class="badge text-bg-<?= !empty($template['pdf_exists']) ? 'success' : 'warning' ?>">
                                            <?= !empty($template['pdf_exists']) ? 'PDF listo' : 'PDF pendiente' ?>
                                        </span>
                                    </div>

                                    <ul class="list-unstyled small text-muted mb-4">
                                        <li class="mb-2"><i class="bi bi-folder2-open me-2"></i><?= pa_h((string)($template['pdf'] ?? 'sin archivo.pdf')) ?></li>
                                        <li class="mb-2"><i class="bi bi-ui-checks-grid me-2"></i><?= count($template['fields'] ?? []) ?> campos definidos</li>
                                        <li><i class="bi bi-pen me-2"></i>Firma integrada en el PDF final</li>
                                    </ul>

                                    <div class="mt-auto d-flex gap-2">
                                        <a class="btn btn-primary" href="solicitud.php?template=<?= urlencode((string)$template['slug']) ?>">
                                            Abrir trámite
                                        </a>
                                        <a class="btn btn-outline-secondary" href="admin.php">
                                            Ver panel
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
