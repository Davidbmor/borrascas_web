<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

// Lee el estado de activación de cada modelo desde el panel admin
$cfgPath    = __DIR__ . '/data/modelos_config.json';
$modelosCfg = json_decode((string)(file_exists($cfgPath) ? file_get_contents($cfgPath) : '{}'), true) ?? [];

$todosLosModelos = [
    [
        'num'         => '01',
        'key'         => 'M1',
        'id'          => 'modelo1',
        'titulo'      => 'Daños en producción oleícola',
        'campana'     => 'Campaña Oleícola 2024/2025',
        'descripcion' => 'Evaluación de pérdidas económicas en producción de aceite de oliva ocasionadas por borrascas atlánticas.',
        'detalle'     => ['Cálculo automático de pérdidas', 'Verificación de DNI autorizado', 'PDF firmado digitalmente'],
        'url'         => 'modelo1/index.php',
        'activo'      => true,
        'icono'       => 'bi-droplet-half',
        'color'       => '2d6a4f',
        'color_light' => 'e8f5ee',
    ],
    [
        'num'         => '02',
        'key'         => 'M2',
        'id'          => 'modelo2',
        'titulo'      => 'Evaluación de daños por borrascas',
        'campana'     => 'Campaña 2024/2025',
        'descripcion' => 'Informe técnico completo con datos de explotación, metodología y valoración económica de daños en olivar.',
        'detalle'     => ['Informe con índice completo', 'Datos de explotación REAFA', 'Cálculo por secciones'],
        'url'         => 'modelo2/index.php',
        'activo'      => true,
        'icono'       => 'bi-file-earmark-bar-graph',
        'color'       => '1a5c8a',
        'color_light' => 'e3f0fa',
    ],
    [
        'num'         => '03',
        'key'         => 'M3',
        'id'          => 'modelo3',
        'titulo'      => 'Evaluación de daños en Espárrago',
        'campana'     => 'Campaña 2026',
        'descripcion' => 'Informe técnico de valoración económica de daños para el cultivo del Espárrago Verde.',
        'detalle'     => ['Específico para espárrago verde', 'Metodología adaptada', 'PDF firmado digitalmente'],
        'url'         => 'modelo3/index.php',
        'activo'      => true,
        'icono'       => 'bi-flower1',
        'color'       => '5a6e2c',
        'color_light' => 'edf2e0',
    ],
];

$modelos = array_filter($todosLosModelos, fn($m) => $modelosCfg[$m['key']]['activo'] ?? true);

$pageTitle  = 'Informes de Daños por Borrasca – ACGranada';
$modelLabel = '';
$backUrl    = null;
$assetBase  = '';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.landing-intro {
    background: linear-gradient(180deg, rgba(32,59,43,0.06) 0%, transparent 100%);
    padding: 2.5rem 0 1rem;
}
.landing-intro h1 {
    font-size: clamp(1.4rem, 3vw, 2rem);
    font-weight: 800;
    color: #1b4332;
    letter-spacing: -.02em;
    line-height: 1.2;
}
.landing-intro p {
    color: #5a6b60;
    max-width: 580px;
}

/* Tarjetas de modelo */
.modelo-card {
    border: 1.5px solid #e0ebe4;
    border-radius: 1.25rem;
    background: #fff;
    box-shadow: 0 4px 20px rgba(27,67,50,.07);
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    text-decoration: none;
    color: inherit;
}
.modelo-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 16px 48px rgba(27,67,50,.14);
    border-color: transparent;
    color: inherit;
}
.modelo-card-top {
    padding: 1.75rem 1.75rem 1rem;
    flex: 1;
}
.modelo-num {
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .18em;
    text-transform: uppercase;
    margin-bottom: .75rem;
}
.modelo-icon-circle {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    margin-bottom: 1rem;
    flex-shrink: 0;
}
.modelo-title {
    font-size: 1.1rem;
    font-weight: 700;
    line-height: 1.3;
    margin-bottom: .4rem;
    color: #1a2820;
}
.modelo-desc {
    font-size: .875rem;
    color: #667b70;
    line-height: 1.55;
    margin-bottom: 1rem;
}
.modelo-pills {
    display: flex;
    flex-direction: column;
    gap: .35rem;
    margin-bottom: .75rem;
}
.modelo-pill {
    font-size: .78rem;
    color: #48705a;
    display: flex;
    align-items: center;
    gap: .4rem;
}
.modelo-pill i { font-size: .7rem; opacity: .7; }

.modelo-card-bottom {
    padding: 1rem 1.75rem 1.5rem;
    border-top: 1px solid #f0f4f1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
}
.modelo-badge {
    font-size: .72rem;
    font-weight: 600;
    padding: .3rem .75rem;
    border-radius: 999px;
    letter-spacing: .01em;
}
.modelo-cta {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    font-size: .85rem;
    font-weight: 700;
    padding: .55rem 1.25rem;
    border-radius: .65rem;
    border: none;
    white-space: nowrap;
    transition: filter .15s;
}
.modelo-cta:hover { filter: brightness(1.08); }

/* Separador admin */
.admin-strip {
    margin-top: 3rem;
    padding: 1.25rem 1.5rem;
    background: #fff;
    border: 1.5px solid #e0ebe4;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.admin-strip-title { font-weight: 700; font-size: .95rem; color: #1b4332; }
.admin-strip-sub   { font-size: .82rem; color: #667b70; }
</style>

<div class="landing-intro">
    <div class="container">
        <h1>Selecciona el tipo de informe</h1>
        <p class="mt-2 mb-0">Elige el modelo que corresponde al cultivo y al tipo de daño que deseas documentar. Todos los informes se generan, firman y archivan directamente en esta plataforma.</p>
    </div>
</div>

<div class="container py-4 pb-5">

    <div class="row g-4 mt-1">
        <?php foreach ($modelos as $m):
            $hex   = $m['color'];
            $light = $m['color_light'];
        ?>
            <div class="col-12 col-md-6 col-xl-4">
                <a href="<?= htmlspecialchars($m['url']) ?>" class="modelo-card"
                   style="--card-color:#<?= $hex ?>; --card-light:#<?= $light ?>;">
                    <div class="modelo-card-top">
                        <div class="modelo-num" style="color:#<?= $hex ?>">
                            Modelo <?= htmlspecialchars($m['num']) ?>
                        </div>
                        <div class="d-flex align-items-start gap-3">
                            <div class="modelo-icon-circle" style="background:#<?= $light ?>; color:#<?= $hex ?>">
                                <i class="bi <?= $m['icono'] ?>"></i>
                            </div>
                            <div>
                                <div class="modelo-title"><?= htmlspecialchars($m['titulo']) ?></div>
                                <div class="modelo-desc"><?= htmlspecialchars($m['descripcion']) ?></div>
                            </div>
                        </div>
                        <div class="modelo-pills mt-1">
                            <?php foreach ($m['detalle'] as $d): ?>
                                <div class="modelo-pill">
                                    <i class="bi bi-check-circle-fill" style="color:#<?= $hex ?>"></i>
                                    <?= htmlspecialchars($d) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modelo-card-bottom">
                        <span class="modelo-badge"
                              style="background:#<?= $light ?>; color:#<?= $hex ?>">
                            <?= htmlspecialchars($m['campana']) ?>
                        </span>
                        <span class="modelo-cta text-white"
                              style="background:#<?= $hex ?>">
                            Abrir
                            <i class="bi bi-arrow-right"></i>
                        </span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Acceso administración -->
    <div class="admin-strip mt-4">
        <div>
            <div class="admin-strip-title"><i class="bi bi-shield-lock-fill me-2" style="color:#2d6a4f;"></i>Panel de administración</div>
            <div class="admin-strip-sub">Consulta, descarga y gestiona todos los expedientes generados.</div>
        </div>
        <a href="admin.php" class="btn btn-sm px-4 py-2 fw-semibold"
           style="background:#1b4332; color:#fff; border-radius:.65rem; font-size:.85rem;">
            Entrar al panel
        </a>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>