<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

$modelos = [
    [
        'id'          => 'modelo1',
        'titulo'      => 'Modelo 1 - Danos en produccion oleicola',
        'campana'     => 'Campana Oleicola 2024/2025',
        'descripcion' => 'Informe tecnico de evaluacion de perdidas en produccion de aceite de oliva ocasionadas por borrascas.',
        'url'         => 'modelo1/index.php',
        'activo'      => true,
        'icono'       => 'bi-file-earmark-text',
        'color'       => 'success',
    ],
    [
        'id'          => 'modelo2',
        'titulo'      => 'Modelo 2 - Evaluacion de danos por borrascas',
        'campana'     => 'Campana 2024/2025',
        'descripcion' => 'Informe tecnico completo con datos de explotacion, metodologia y valoracion economica de danos.',
        'url'         => 'modelo2/index.php',
        'activo'      => true,
        'icono'       => 'bi-file-earmark-bar-graph',
        'color'       => 'primary',
    ],
    [
        'id'          => 'modelo3',
        'titulo'      => 'Modelo 3 - Evaluación de daños en Espárrago',
        'campana'     => 'Campaña 2026',
        'descripcion' => 'Informe técnico completo con datos de explotación, metodología y valoración económica de daños para el cultivo del Espárrago Verde.',
        'url'         => 'modelo3/index.php',
        'activo'      => true,
        'icono'       => 'bi-flower1',
        'color'       => 'success',
    ],
];

$pageTitle  = 'Informes de Danos por Borrasca - ACGranada';
$modelLabel = '';
$backUrl    = null;
$assetBase  = '';
require_once __DIR__ . '/includes/header.php';
?>



<div class="container py-5">
    <div class="row g-4 justify-content-center">
        <?php foreach ($modelos as $m): ?>
            <div class="col-12 col-md-6 col-lg-5">
                <a href="<?= htmlspecialchars($m['url']) ?>" class="text-decoration-none">
                    <div class="model-card card h-100 p-4">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="model-icon-wrap bg-<?= $m['color'] ?>-subtle text-<?= $m['color'] ?>">
                                <i class="bi <?= $m['icono'] ?>"></i>
                            </div>
                            <div>
                                <h2 class="h5 fw-bold mb-1"><?= htmlspecialchars($m['titulo']) ?></h2>
                                <span class="badge text-bg-<?= $m['color'] ?>"><?= htmlspecialchars($m['campana']) ?></span>
                            </div>
                        </div>
                        <p class="text-muted mb-4"><?= htmlspecialchars($m['descripcion']) ?></p>
                        <div class="mt-auto">
                            <span class="btn btn-<?= $m['color'] ?> w-100">
                                Abrir informe <i class="bi bi-arrow-right ms-1"></i>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center mt-5">
        <a href="admin.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-shield-lock me-1"></i>Panel de administracion
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>