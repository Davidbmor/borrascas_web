<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/functions.php';

if (($_GET['action'] ?? '') === 'logout') {
    session_destroy();
    session_start();
    pa_flash('ok', 'Sesión cerrada correctamente.');
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    try {
        pa_verify_csrf();
        $user = trim((string)($_POST['user'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($user !== ADMIN_USER || !hash_equals(ADMIN_PASSWORD, $password)) {
            throw new RuntimeException('Credenciales inválidas.');
        }

        $_SESSION['admin_ok'] = true;
        $_SESSION['admin_user'] = $user;
        pa_flash('ok', 'Acceso concedido.');
        header('Location: admin.php');
        exit;
    } catch (Throwable $e) {
        pa_flash('error', $e->getMessage());
        header('Location: admin.php');
        exit;
    }
}

$logged = pa_is_admin();
$okMessage = pa_flash('ok');
$errorMessage = pa_flash('error');
$query = trim((string)($_GET['q'] ?? ''));
$registry = pa_registry();
$filtered = [];

if ($logged) {
    foreach ($registry as $item) {
        $haystack = mb_strtolower(implode(' ', [
            (string)($item['dni'] ?? ''),
            (string)($item['nombre'] ?? ''),
            (string)($item['template']['title'] ?? ''),
            (string)($item['template']['slug'] ?? ''),
        ]), 'UTF-8');
        if ($query !== '' && mb_strpos($haystack, mb_strtolower($query, 'UTF-8')) === false) {
            continue;
        }
        $filtered[] = $item;
    }
}

$grouped = [];
if ($logged) {
    foreach ($filtered as $item) {
        $dni = (string)($item['dni'] ?? 'sin-dni');
        if (!isset($grouped[$dni])) {
            $grouped[$dni] = [
                'nombre' => (string)($item['nombre'] ?? $dni),
                'items' => [],
            ];
        }
        $grouped[$dni]['items'][] = $item;
    }
}

?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel admin - <?= pa_h(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
    <main class="portal-shell">
        <section class="hero-panel admin-hero">
            <div class="container py-4 py-lg-5">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <span class="eyebrow">Panel privado</span>
                        <h1 class="display-6 fw-bold mb-2">Expedientes y firmas</h1>
                        <p class="lead mb-0">Vista agrupada por persona, con sus documentos, adjuntos y descargas seguras.</p>
                    </div>
                    <?php if ($logged): ?>
                        <a class="btn btn-outline-light btn-sm" href="admin.php?action=logout"><i class="bi bi-box-arrow-right me-1"></i>Salir</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="container py-4">
            <?php if ($okMessage): ?>
                <div class="alert alert-success shadow-sm"><?= pa_h($okMessage) ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger shadow-sm"><?= pa_h($errorMessage) ?></div>
            <?php endif; ?>

            <?php if (!$logged): ?>
                <div class="row justify-content-center">
                    <div class="col-12 col-lg-5">
                        <form class="card shadow-sm border-0 p-4 p-lg-5" method="post" action="admin.php">
                            <?= pa_csrf_field() ?>
                            <input type="hidden" name="login" value="1">
                            <h2 class="h4 fw-bold mb-3">Acceso al panel</h2>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Usuario</label>
                                <input class="form-control form-control-lg" type="text" name="user" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Contraseña</label>
                                <input class="form-control form-control-lg" type="password" name="password" required>
                            </div>
                            <button class="btn btn-primary btn-lg w-100" type="submit">Entrar</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-0 p-3 p-lg-4 mb-4">
                    <form class="row g-2 align-items-center" method="get" action="admin.php">
                        <div class="col-12 col-lg-9">
                            <input class="form-control form-control-lg" type="search" name="q" value="<?= pa_h($query) ?>" placeholder="Buscar por DNI, nombre o tipo de trámite">
                        </div>
                        <div class="col-12 col-lg-3 d-grid">
                            <button class="btn btn-outline-primary btn-lg" type="submit"><i class="bi bi-search me-1"></i>Buscar</button>
                        </div>
                    </form>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="stats-card shadow-sm">
                            <div class="stats-value"><?= count($filtered) ?></div>
                            <div class="stats-label">Expedientes visibles</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="stats-card shadow-sm">
                            <div class="stats-value"><?= count($grouped) ?></div>
                            <div class="stats-label">Personas agrupadas</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="stats-card shadow-sm">
                            <div class="stats-value"><?= count($templates = pa_template_catalog()) ?></div>
                            <div class="stats-label">Plantillas cargadas</div>
                        </div>
                    </div>
                </div>

                <!-- Plantillas disponibles -->
                <?php $templates = pa_template_catalog(); ?>
                <div class="card shadow-sm border-0 mb-4 overflow-hidden">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <h2 class="h5 fw-bold mb-0"><i class="bi bi-file-earmark-text me-2"></i>Plantillas de trámite</h2>
                        <a class="btn btn-sm btn-outline-success" href="admin/importar_plantillas.php">
                            <i class="bi bi-arrow-down-circle me-1"></i>Importar PDFs de plantillas
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Trámite</th>
                                    <th>PDF</th>
                                    <th>Campos</th>
                                    <th>Firma</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($templates === []): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">Sube PDFs a <code>plantillas/</code> y crea sus definiciones JSON en <code>storage/definiciones/</code> o usa el editor.</td></tr>
                            <?php else: ?>
                                <?php foreach ($templates as $t): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= pa_h((string)($t['title'] ?? $t['slug'])) ?></div>
                                            <div class="text-muted small"><?= pa_h((string)$t['slug']) ?></div>
                                        </td>
                                        <td>
                                            <?php if (!empty($t['pdf_exists'])): ?>
                                                <span class="badge text-bg-success">PDF listo</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-warning">Sin PDF</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= count($t['fields'] ?? []) ?></td>
                                        <td><?= !empty($t['signature']) ? '<span class="badge text-bg-primary">Definida</span>' : '<span class="badge text-bg-light text-dark">Sin definir</span>' ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary" href="admin/editor.php?template=<?= urlencode((string)$t['slug']) ?>">
                                                <i class="bi bi-sliders me-1"></i>Configurar campos
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Fin plantillas -->

                <?php if ($grouped === []): ?>
                    <div class="empty-state shadow-sm">
                        <h2 class="h4 fw-bold mb-2">No hay expedientes todavía</h2>
                        <p class="mb-0">Cuando alguien complete y firme un trámite, aparecerá aquí agrupado por DNI.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($grouped as $dni => $bundle): ?>
                    <div class="card shadow-sm border-0 mb-4 overflow-hidden">
                        <div class="card-header bg-white py-3">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div>
                                    <h2 class="h5 fw-bold mb-1"><?= pa_h($bundle['nombre']) ?></h2>
                                    <div class="text-muted small">DNI/NIE: <strong><?= pa_h($dni) ?></strong> · <?= count($bundle['items']) ?> documento(s)</div>
                                </div>
                                <div class="text-muted small text-end">
                                    Carpeta: <code>storage/expedientes/<?= pa_h($dni) ?></code>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Trámite</th>
                                        <th>Referencia</th>
                                        <th>Fecha</th>
                                        <th>Adjuntos</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bundle['items'] as $item): ?>
                                        <?php $attachments = (array)($item['adjuntos'] ?? []); ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= pa_h((string)($item['template']['title'] ?? 'Trámite')) ?></div>
                                                <div class="text-muted small"><?= pa_h((string)($item['template']['slug'] ?? '')) ?></div>
                                            </td>
                                            <td><code><?= pa_h((string)($item['id'] ?? '')) ?></code></td>
                                            <td><?= pa_h((string)($item['signed_at'] ?? $item['created_at'] ?? '')) ?></td>
                                            <td><?= count($attachments) ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-primary" href="descargar.php?id=<?= urlencode((string)$item['id']) ?>&tipo=pdf">
                                                    <i class="bi bi-download me-1"></i>PDF
                                                </a>
                                                <?php if ($attachments !== []): ?>
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#adj-<?= pa_h((string)$item['id']) ?>">
                                                        Adjuntos
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if ($attachments !== []): ?>
                                            <tr class="collapse" id="adj-<?= pa_h((string)$item['id']) ?>">
                                                <td colspan="5" class="bg-light">
                                                    <div class="small fw-semibold mb-2">Archivos adjuntos</div>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <?php foreach ($attachments as $attachment): ?>
                                                            <a class="file-pill" href="descargar.php?id=<?= urlencode((string)$item['id']) ?>&tipo=adjunto&archivo=<?= urlencode((string)($attachment['archivo'] ?? '')) ?>">
                                                                <i class="bi bi-paperclip me-1"></i><?= pa_h((string)($attachment['original'] ?? $attachment['archivo'] ?? 'archivo')) ?>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
