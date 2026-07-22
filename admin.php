<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

// ──────────────────────────────────────────────────────────────
// AUTENTICACIÓN
// ──────────────────────────────────────────────────────────────
$error = '';

if (isset($_POST['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $user = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    if ($user === ADMIN_USER && password_verify($pass, ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_ok'] = true;
    } else {
        // Pequeño delay para dificultar fuerza bruta
        sleep(1);
        $error = 'Usuario o contraseña incorrectos.';
    }
}

$logueado = !empty($_SESSION['admin_ok']);

// ──────────────────────────────────────────────────────────────
// LECTURA Y FILTRADO DEL REGISTRO
// ──────────────────────────────────────────────────────────────

$informes = [];
$filtDni    = trim((string)($_GET['dni']    ?? ''));
$filtFecha  = trim((string)($_GET['fecha']  ?? ''));
$filtModelo = trim((string)($_GET['modelo'] ?? ''));
$firmaOk = !empty($_SESSION['firma_ok']) ? (string)$_SESSION['firma_ok'] : '';
unset($_SESSION['firma_ok']);

if ($logueado) {
    // Cargar modelo 1
    if (file_exists(REGISTRO_JSON)) {
        $raw  = file_get_contents(REGISTRO_JSON);
        $m1   = json_decode($raw, true) ?? [];
        foreach ($m1 as &$r) { if (empty($r['modelo_id'])) $r['modelo_id'] = 'M1'; }
        unset($r);
    } else {
        $m1 = [];
    }

    // Cargar modelo 2
    $m2RegistroPath = __DIR__ . '/informes2/registro.json';
    if (file_exists($m2RegistroPath)) {
        $raw = file_get_contents($m2RegistroPath);
        $m2  = json_decode($raw, true) ?? [];
        foreach ($m2 as &$r) { if (empty($r['modelo_id'])) $r['modelo_id'] = 'M2'; }
        unset($r);
    } else {
        $m2 = [];
    }

    $todos = array_merge($m1, $m2);
    // Ordenar por timestamp desc
    usort($todos, fn($a, $b) => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

    foreach ($todos as $inf) {
        if ($filtDni   && stripos((string)($inf['dni'] ?? $inf['cif_nif'] ?? ''), $filtDni) === false) continue;
        if ($filtFecha && ($inf['fecha'] ?? '') !== $filtFecha) continue;
        if ($filtModelo !== '' && ($inf['modelo_id'] ?? '') !== $filtModelo) continue;
        $informes[] = $inf;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel de Administración – Borrascas</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root { --verde: #2d6a4f; --verde-oscuro: #1b4332; }
body { background: #f0f4f0; font-size: .93rem; }
.admin-header {
    background: #fff;
    border-bottom: 2px solid #e0e8e3;
    box-shadow: 0 2px 6px rgba(0,0,0,.06);
}
.admin-header img { height: 52px; }
.card { border-radius: .6rem; box-shadow: 0 2px 8px rgba(0,0,0,.07); }
.card-header-green {
    background: linear-gradient(90deg, var(--verde) 0%, var(--verde-oscuro) 100%);
    color: #fff;
    font-weight: 600;
    border-radius: .6rem .6rem 0 0;
    padding: .65rem 1rem;
}
.table th { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: var(--verde-oscuro); }
.table td { vertical-align: middle; }
.badge-modelo { background: #e8f5e9; color: #1b4332; font-weight: 600; border-radius: .3rem; padding: 2px 8px; }
</style>
</head>
<body>

<header class="admin-header py-2 mb-4">
    <div class="container d-flex align-items-center justify-content-between">
        <img src="assets/img/Faeca.png" alt="ACGranada">
        <?php if ($logueado): ?>
        <form method="POST" class="mb-0">
            <button name="logout" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión
            </button>
        </form>
        <?php endif; ?>
    </div>
</header>

<main class="container pb-5">

<?php if ($firmaOk): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($firmaOk) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
</div>
<?php endif; ?>

<?php if (!$logueado): ?>
<!-- ═══ FORMULARIO DE LOGIN ═══ -->
<div class="row justify-content-center">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header-green">
                <i class="bi bi-lock-fill me-2"></i>Acceso al panel
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Usuario</label>
                        <input type="text" name="username" class="form-control" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Contraseña</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-unlock-fill me-2"></i>Entrar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ═══ PANEL PRINCIPAL ═══ -->

<!-- FILTROS -->
<div class="card mb-4">
    <div class="card-header-green">
        <i class="bi bi-funnel-fill me-2"></i>Filtrar informes
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">DNI</label>
                <input type="text" name="dni" class="form-control text-uppercase"
                       placeholder="12345678A" maxlength="9"
                       value="<?= htmlspecialchars($filtDni) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Fecha</label>
                <input type="date" name="fecha" class="form-control"
                       value="<?= htmlspecialchars($filtFecha) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Modelo</label>
                <select name="modelo" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" <?= $filtModelo === '1' ? 'selected' : '' ?>>Modelo 1 – Oleícola</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-success flex-grow-1">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
                <a href="admin.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- TABLA DE INFORMES -->
<div class="card">
    <div class="card-header-green d-flex align-items-center justify-content-between">
        <span><i class="bi bi-file-earmark-pdf-fill me-2"></i>Informes generados</span>
        <span class="badge bg-light text-dark"><?= count($informes) ?> resultado<?= count($informes) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($informes)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No se encontraron informes con esos criterios.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Fecha</th>
                        <th>Hora</th>
                        <th>DNI</th>
                        <th>Nombre</th>
                        <th>Cooperativa</th>
                        <th>Modelo</th>
                        <th>Estado</th>
                        <th class="text-end">Total €</th>
                        <th class="text-center">Archivos</th>
                        <th class="text-center">Adjuntos</th>
                        <th class="text-center">Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($informes as $inf): ?>
                    <?php
                        $isM2           = ($inf['modelo_id'] ?? '') === 'M2';
                        $firmado        = !empty($inf['firmado']);
                        $archivoFirmado = trim((string)($inf['archivo_firmado'] ?? ''));
                        $carpetaInf     = trim((string)($inf['carpeta'] ?? ($inf['cif_nif'] ?? '')));
                        $adjInfos       = $inf['adjuntos'] ?? [];
                        $displayDni     = $isM2 ? ($inf['cif_nif'] ?? '—') : ($inf['dni'] ?? '—');
                        $displayNombre  = $isM2 ? ($inf['razon_social'] ?? '—') : ($inf['nombre'] ?? '—');
                        $displayExtra   = $isM2 ? ($inf['exp_municipio'] ?? '—') : ($inf['cooperativa'] ?? '—');
                        $displayTotal   = $isM2 ? '—' : number_format((float)($inf['total_eur'] ?? 0), 2, ',', '.') . ' €';
                        $modeloIdLabel  = $isM2 ? 'M2' : 'M1';
                        $expediente     = $isM2 ? ($inf['expediente'] ?? '') : '';
                        $modeloParam    = $isM2 ? '&modelo=M2' : '';
                        $estadoLabel    = 'Pendiente de firma';
                        $estadoClass    = 'bg-warning text-dark';
                        if ($firmado && $archivoFirmado !== '') {
                            $estadoLabel = 'Firmado y archivado';
                            $estadoClass = 'bg-success';
                        } elseif ($firmado) {
                            $estadoLabel = 'Firmado';
                            $estadoClass = 'bg-success';
                        }
                    ?>
                    <tr>
                        <td class="ps-3"><?= htmlspecialchars(date('d/m/Y', strtotime((string)($inf['fecha'] ?? 'now')))) ?></td>
                        <td><?= htmlspecialchars((string)($inf['hora'] ?? '—')) ?></td>
                        <td><code><?= htmlspecialchars($displayDni) ?></code>
                            <?php if ($expediente): ?><br><small class="text-muted"><?= htmlspecialchars($expediente) ?></small><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($displayNombre) ?></td>
                        <td><?= htmlspecialchars($displayExtra) ?></td>
                        <td><span class="badge-modelo"><?= htmlspecialchars($modeloIdLabel) ?></span></td>
                        <td><span class="badge <?= $estadoClass ?>"><?= htmlspecialchars($estadoLabel) ?></span></td>
                        <td class="text-end fw-semibold"><?= htmlspecialchars($displayTotal) ?></td>
                        <td class="text-center">
                            <div class="d-inline-flex gap-1">
                                <a href="descargar.php?f=<?= urlencode((string)($inf['archivo'] ?? '')) ?>&carpeta=<?= urlencode($carpetaInf) ?><?= $modeloParam ?>"
                                   class="btn btn-sm btn-outline-success" title="Descargar original">
                                    <i class="bi bi-file-earmark-arrow-down"></i>
                                </a>
                                <?php if ($archivoFirmado !== ''): ?>
                                    <a href="descargar.php?f=<?= urlencode($archivoFirmado) ?>&carpeta=<?= urlencode($carpetaInf) ?><?= $modeloParam ?>"
                                       class="btn btn-sm btn-success" title="Descargar firmado">
                                        <i class="bi bi-file-earmark-check"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <?php if (empty($adjInfos)): ?>
                                <span class="text-muted">—</span>
                            <?php else: ?>
                                <?php foreach ($adjInfos as $adj): ?>
                                    <a href="descargar_adjunto.php?f=<?= urlencode((string)($adj['archivo'] ?? '')) ?>&carpeta=<?= urlencode($carpetaInf) ?><?= $modeloParam ?>"
                                       class="d-block small text-truncate" style="max-width:130px;"
                                       title="<?= htmlspecialchars((string)($adj['nombre'] ?? '')) ?>">
                                        <i class="bi bi-paperclip me-1"></i><?= htmlspecialchars(substr((string)($adj['nombre'] ?? ''), 0, 18)) ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!$firmado && !$isM2): ?>
                                <a href="firmar_informe.php?f=<?= urlencode((string)($inf['archivo'] ?? '')) ?>&carpeta=<?= urlencode($carpetaInf) ?>"
                                   class="btn btn-sm btn-warning">
                                    <i class="bi bi-pen-fill me-1"></i>Firmar
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
