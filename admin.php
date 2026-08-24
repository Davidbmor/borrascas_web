<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

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
        sleep(1);
        $error = 'Usuario o contraseña incorrectos.';
    }
}

// Toggle de modelo desde admin
$toggleError = '';
if (!empty($_SESSION['admin_ok']) && isset($_POST['action']) && $_POST['action'] === 'toggle_modelo') {
    $modeloKey  = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)($_POST['modelo_key'] ?? '')));
    $dataDir    = __DIR__ . '/data';
    $configPath = $dataDir . '/modelos_config.json';
    $defaults   = ['M1' => ['activo' => true], 'M2' => ['activo' => true], 'M3' => ['activo' => true]];

    if (!is_dir($dataDir) && !mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
        $toggleError = 'No se pudo crear la carpeta data/. Revisa los permisos en el servidor.';
    } else {
        $cfg = file_exists($configPath) ? (json_decode((string)file_get_contents($configPath), true) ?? []) : [];
        $cfg = array_merge($defaults, $cfg); // asegura que M1/M2/M3 siempre existan

        if ($modeloKey !== '' && isset($cfg[$modeloKey])) {
            $cfg[$modeloKey]['activo'] = !($cfg[$modeloKey]['activo'] ?? true);
            $written = @file_put_contents($configPath, json_encode($cfg, JSON_PRETTY_PRINT), LOCK_EX);
            clearstatcache(true, $configPath);
            if ($written === false) {
                $toggleError = 'No se pudo guardar data/modelos_config.json. Revisa los permisos de escritura (chmod 664/775) en el servidor.';
            }
        }
    }

    if ($toggleError !== '') {
        $_SESSION['toggle_error'] = $toggleError;
    }
    header('Location: admin.php?tab=modelos');
    exit;
}

$logueado = !empty($_SESSION['admin_ok']);

// Leer config de modelos
$modelosConfigPath = __DIR__ . '/data/modelos_config.json';
$modelosCfg = json_decode((string)(file_exists($modelosConfigPath) ? file_get_contents($modelosConfigPath) : '{}'), true) ?? [];

$toggleErrorMsg = !empty($_SESSION['toggle_error']) ? (string)$_SESSION['toggle_error'] : '';
unset($_SESSION['toggle_error']);

$informes = [];
$filtDni    = trim((string)($_GET['dni']    ?? ''));
$filtFecha  = trim((string)($_GET['fecha']  ?? ''));
$filtModelo = trim((string)($_GET['modelo'] ?? ''));
$activeTab  = trim((string)($_GET['tab']    ?? 'expedientes'));
$firmaOk    = !empty($_SESSION['firma_ok']) ? (string)$_SESSION['firma_ok'] : '';
unset($_SESSION['firma_ok']);

if ($logueado) {
    $registros = ['M1' => REGISTRO_JSON, 'M2' => __DIR__ . '/informes2/registro.json', 'M3' => __DIR__ . '/informes3/registro.json'];
    $todos = [];
    foreach ($registros as $mid => $path) {
        if (!file_exists($path)) continue;
        $items = json_decode((string)file_get_contents($path), true) ?? [];
        foreach ($items as &$r) { $r['modelo_id'] = $r['modelo_id'] ?? $mid; }
        unset($r);
        $todos = array_merge($todos, $items);
    }
    usort($todos, fn($a, $b) => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));
    foreach ($todos as $inf) {
        if ($filtDni   && stripos((string)($inf['dni'] ?? $inf['cif_nif'] ?? ''), $filtDni) === false) continue;
        if ($filtFecha && ($inf['fecha'] ?? '') !== $filtFecha) continue;
        if ($filtModelo && ($inf['modelo_id'] ?? '') !== $filtModelo) continue;
        $informes[] = $inf;
    }
}

// Stats
$totalHoy  = count(array_filter($informes, fn($i) => ($i['fecha'] ?? '') === date('Y-m-d')));
$totalFirm = count(array_filter($informes, fn($i) => !empty($i['firmado'])));
$byModelo  = array_count_values(array_column($informes, 'modelo_id'));

$pageTitle  = 'Panel de Administración – Borrascas';
$modelLabel = '';
$backUrl    = 'landing.php';
$assetBase  = '';
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Admin layout */
.adm-wrap { max-width: 1400px; margin: 0 auto; padding: 1.5rem 1rem 4rem; }

/* Login */
.adm-login { max-width: 400px; margin: 5rem auto; }
.adm-login-card { background:#fff; border-radius:1.25rem; box-shadow:0 8px 40px rgba(27,67,50,.13); overflow:hidden; }
.adm-login-header { background:linear-gradient(135deg,#1b4332 0%,#2d6a4f 100%); padding:2rem; text-align:center; }
.adm-login-header h1 { color:#fff; font-size:1.2rem; font-weight:800; margin:0; }
.adm-login-header p  { color:rgba(255,255,255,.75); font-size:.85rem; margin:.4rem 0 0; }

/* Tabs */
.adm-tabs { display:flex; gap:.25rem; border-bottom:2px solid #e2ebe5; margin-bottom:1.75rem; }
.adm-tab { padding:.55rem 1.25rem; border-radius:.5rem .5rem 0 0; font-size:.875rem; font-weight:600; color:#5a7a68; text-decoration:none; background:transparent; border:none; cursor:pointer; transition:background .15s,color .15s; }
.adm-tab:hover { background:#f0f7f2; color:#1b4332; }
.adm-tab.active { background:#fff; color:#1b4332; border:2px solid #e2ebe5; border-bottom:2px solid #fff; margin-bottom:-2px; }

/* Stats cards */
.stat-card { background:#fff; border-radius:1rem; padding:1.25rem 1.5rem; box-shadow:0 2px 12px rgba(0,0,0,.07); border:1.5px solid #e8f0ec; }
.stat-value { font-size:2rem; font-weight:900; color:#1b4332; line-height:1; }
.stat-label { font-size:.78rem; color:#6b8878; margin-top:.25rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em; }

/* Filters */
.adm-filters { background:#fff; border-radius:1rem; padding:1.25rem 1.5rem; box-shadow:0 2px 12px rgba(0,0,0,.07); border:1.5px solid #e8f0ec; margin-bottom:1.5rem; }

/* Table card */
.adm-table-card { background:#fff; border-radius:1rem; box-shadow:0 2px 12px rgba(0,0,0,.07); border:1.5px solid #e8f0ec; overflow:hidden; }
.adm-table-head { padding:.9rem 1.5rem; border-bottom:1.5px solid #f0f5f2; display:flex; align-items:center; justify-content:space-between; gap:1rem; }
.adm-table-head h2 { font-size:.95rem; font-weight:700; color:#1b4332; margin:0; }
.adm-empty { text-align:center; padding:4rem 2rem; color:#8aaa98; }
.adm-empty i { font-size:2.5rem; display:block; margin-bottom:.75rem; }
table.adm-table { width:100%; border-collapse:collapse; font-size:.83rem; }
table.adm-table thead th { padding:.6rem .75rem; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:#6b8878; font-weight:700; border-bottom:1.5px solid #edf2ef; background:#fafcfb; white-space:nowrap; }
table.adm-table td { padding:.65rem .75rem; border-bottom:1px solid #f3f7f4; vertical-align:middle; }
table.adm-table tbody tr:hover { background:#f7fbf8; }
table.adm-table tbody tr:last-child td { border-bottom:none; }
.badge-m { font-size:.68rem; font-weight:800; padding:.2rem .55rem; border-radius:.3rem; letter-spacing:.04em; }
.badge-m1 { background:#e8f5ee; color:#1b4332; }
.badge-m2 { background:#e3f0fa; color:#1a5c8a; }
.badge-m3 { background:#edf2e0; color:#4a5e1c; }
.adm-btn { display:inline-flex; align-items:center; gap:.3rem; font-size:.78rem; font-weight:700; padding:.35rem .75rem; border-radius:.5rem; border:1.5px solid transparent; text-decoration:none; white-space:nowrap; transition:filter .15s; }
.adm-btn:hover { filter:brightness(1.08); }
.adm-btn-dl  { background:#f0faf4; color:#1b7a4a; border-color:#c3e8d4; }
.adm-btn-dlf { background:#1b7a4a; color:#fff; }
.adm-btn-sign { background:#fff8e3; color:#9a6800; border-color:#f0d890; }

/* Modelo toggle cards */
.modelo-toggle-card { background:#fff; border-radius:1rem; padding:1.5rem; box-shadow:0 2px 12px rgba(0,0,0,.07); border:1.5px solid #e8f0ec; display:flex; align-items:center; justify-content:space-between; gap:1.5rem; }
.modelo-toggle-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.form-switch .form-check-input { width:3rem; height:1.5rem; cursor:pointer; }
.form-switch .form-check-input:checked { background-color:#2d6a4f; border-color:#2d6a4f; }
</style>

<div class="adm-wrap">

<?php if ($firmaOk): ?>
<div class="alert alert-success alert-dismissible fade show mb-4 rounded-3" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($firmaOk) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!$logueado): ?>
<!-- ═══ LOGIN ═══ -->
<div class="adm-login">
    <div style="text-align:center;margin-bottom:2rem;">
        <div style="width:48px;height:48px;border-radius:50%;background:#1b4332;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.4rem;margin:0 auto 1rem;">
            <i class="bi bi-shield-lock"></i>
        </div>
        <h1 style="font-size:1.25rem;font-weight:800;color:#1a2820;margin:0 0 .25rem;">Panel de administración</h1>
    </div>
    <?php if ($error): ?>
        <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:.6rem;padding:.65rem 1rem;font-size:.82rem;font-weight:600;color:#b91c1c;margin-bottom:1.25rem;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <form method="POST" autocomplete="off">
        <div style="margin-bottom:1rem;">
            <label style="display:block;font-size:.78rem;font-weight:700;color:#3d5244;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.06em;">Usuario</label>
            <input type="text" name="username" required autofocus placeholder="admin"
                   style="width:100%;padding:.75rem 1rem;border:1.5px solid #dce6df;border-radius:.65rem;font-size:.95rem;outline:none;box-sizing:border-box;color:#1a2820;background:#fff;"
                   onfocus="this.style.borderColor='#2d6a4f'" onblur="this.style.borderColor='#dce6df'">
        </div>
        <div style="margin-bottom:1.75rem;">
            <label style="display:block;font-size:.78rem;font-weight:700;color:#3d5244;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.06em;">Contraseña</label>
            <input type="password" name="password" required placeholder="contraseña"
                   style="width:100%;padding:.75rem 1rem;border:1.5px solid #dce6df;border-radius:.65rem;font-size:.95rem;outline:none;box-sizing:border-box;color:#1a2820;background:#fff;"
                   onfocus="this.style.borderColor='#2d6a4f'" onblur="this.style.borderColor='#dce6df'">
        </div>
        <button type="submit"
                style="width:100%;padding:.85rem;background:#1b4332;color:#fff;border:none;border-radius:.75rem;font-size:.95rem;font-weight:700;cursor:pointer;letter-spacing:.01em;transition:background .15s;"
                onmouseover="this.style.background='#2d6a4f'" onmouseout="this.style.background='#1b4332'">
            Entrar
        </button>
    </form>
</div>

<?php else: ?>
<!-- ═══ PANEL PRINCIPAL ═══ -->

<!-- STATS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?= count($informes) ?></div><div class="stat-label">Total expedientes</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?= $totalFirm ?></div><div class="stat-label">Firmados</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?= count($informes) - $totalFirm ?></div><div class="stat-label">Pendientes firma</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?= $totalHoy ?></div><div class="stat-label">Hoy</div></div></div>
</div>

<!-- TABS -->
<div class="adm-tabs">
    <a href="?tab=expedientes" class="adm-tab <?= $activeTab === 'expedientes' ? 'active' : '' ?>">
        <i class="bi bi-files me-1"></i>Expedientes
    </a>
    <a href="?tab=modelos" class="adm-tab <?= $activeTab === 'modelos' ? 'active' : '' ?>">
        <i class="bi bi-toggles me-1"></i>Gestionar modelos
    </a>
    <form method="POST" class="ms-auto d-flex align-items-center">
        <button type="submit" name="logout" value="1" class="adm-tab" style="color:#b44">
            <i class="bi bi-box-arrow-right me-1"></i>Salir
        </button>
    </form>
</div>

<?php if ($activeTab === 'modelos'): ?>
<!-- ════ TAB: GESTIONAR MODELOS ════ -->
<h2 class="fw-bold mb-1" style="font-size:1.05rem;color:#1b4332;">Visibilidad de modelos en la página de inicio</h2>
<p class="text-muted small mb-4">Activa o desactiva cada modelo para controlar qué ven los usuarios en la landing page. Los modelos desactivados no aparecen en la selección pública.</p>
<?php if ($toggleErrorMsg): ?>
<div class="alert alert-danger py-2 small fw-semibold rounded-3 mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($toggleErrorMsg) ?></div>
<?php endif; ?>
<?php
$modelosInfo = [
    'M1' => ['titulo' => 'Modelo 1 – Daños en producción oleícola', 'desc' => 'Campaña Oleícola 2024/2025', 'color' => '2d6a4f', 'icono' => 'bi-droplet-half'],
    'M2' => ['titulo' => 'Modelo 2 – Evaluación de daños por borrascas', 'desc' => 'Informe olivar completo', 'color' => '1a5c8a', 'icono' => 'bi-file-earmark-bar-graph'],
    'M3' => ['titulo' => 'Modelo 3 – Evaluación de daños en Espárrago', 'desc' => 'Campaña 2026', 'color' => '5a6e2c', 'icono' => 'bi-flower1'],
];
?>
<div class="d-flex flex-column gap-3">
<?php foreach ($modelosInfo as $key => $info):
    $isActive = $modelosCfg[$key]['activo'] ?? true;
?>
    <div class="modelo-toggle-card">
        <div class="d-flex align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:50%;background:#<?= $info['color'] ?>1a;color:#<?= $info['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
                <i class="bi <?= $info['icono'] ?>"></i>
            </div>
            <div>
                <div class="fw-bold" style="color:#1a2820;"><?= htmlspecialchars($info['titulo']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars($info['desc']) ?></div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 flex-shrink-0">
            <span class="badge <?= $isActive ? 'text-bg-success' : 'text-bg-secondary' ?>" style="font-size:.72rem;">
                <?= $isActive ? '<i class="bi bi-eye me-1"></i>Visible' : '<i class="bi bi-eye-slash me-1"></i>Oculto' ?>
            </span>
            <form method="POST" class="m-0">
                <input type="hidden" name="action" value="toggle_modelo">
                <input type="hidden" name="modelo_key" value="<?= htmlspecialchars($key) ?>">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           <?= $isActive ? 'checked' : '' ?>
                           onchange="this.form.submit()">
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php else: ?>
<!-- ════ TAB: EXPEDIENTES ════ -->

<!-- FILTROS -->
<div class="adm-filters">
    <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="tab" value="expedientes">
        <div class="col-12 col-md-3">
            <label class="form-label fw-semibold small">DNI / CIF</label>
            <input type="text" name="dni" class="form-control text-uppercase" placeholder="12345678A" maxlength="12" value="<?= htmlspecialchars($filtDni) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label fw-semibold small">Fecha</label>
            <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($filtFecha) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label fw-semibold small">Modelo</label>
            <select name="modelo" class="form-select">
                <option value="">Todos los modelos</option>
                <option value="M1" <?= $filtModelo === 'M1' ? 'selected' : '' ?>>M1 – Oleícola</option>
                <option value="M2" <?= $filtModelo === 'M2' ? 'selected' : '' ?>>M2 – Daños olivar</option>
                <option value="M3" <?= $filtModelo === 'M3' ? 'selected' : '' ?>>M3 – Espárrago</option>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-success flex-grow-1"><i class="bi bi-search me-1"></i>Filtrar</button>
            <a href="admin.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>
</div>

<!-- TABLA -->
<div class="adm-table-card">
    <div class="adm-table-head">
        <h2><i class="bi bi-file-earmark-pdf-fill me-2" style="color:#2d6a4f;"></i>Expedientes generados</h2>
        <span class="badge text-bg-light fw-semibold" style="font-size:.78rem;"><?= count($informes) ?> resultado<?= count($informes) !== 1 ? 's' : '' ?></span>
    </div>
    <?php if (empty($informes)): ?>
        <div class="adm-empty"><i class="bi bi-inbox"></i>No hay expedientes con esos criterios.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="adm-table">
            <thead>
                <tr>
                    <th class="ps-4">Fecha</th>
                    <th>Identificación</th>
                    <th>Nombre / Razón Social</th>
                    <th>Cooperativa / Mpio.</th>
                    <th>Modelo</th>
                    <th>Estado</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Archivos</th>
                    <th class="text-center">Adjuntos</th>
                    <th class="text-center">Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($informes as $inf):
                $mid  = strtoupper((string)($inf['modelo_id'] ?? 'M1'));
                $isM23 = in_array($mid, ['M2','M3'], true);
                $carpetaInf = (string)($inf['carpeta'] ?? $inf['cif_nif'] ?? '');
                $modeloParam = $isM23 ? '&modelo=' . $mid : '';
                $firmado        = !empty($inf['firmado']);
                $archivoFirmado = trim((string)($inf['archivo_firmado'] ?? ''));
                $adjInfos       = $inf['adjuntos'] ?? [];
                $displayDni    = $isM23 ? ($inf['cif_nif'] ?? '—') : ($inf['dni'] ?? '—');
                $displayNombre = $isM23 ? ($inf['razon_social'] ?? '—') : ($inf['nombre'] ?? '—');
                $displayExtra  = $isM23 ? ($inf['exp_municipio'] ?? '—') : ($inf['cooperativa'] ?? '—');
                $displayTotal  = number_format((float)($inf['total_eur'] ?? 0), 2, ',', '.') . ' €';
                $expediente    = (string)($inf['expediente'] ?? '');
                if ($firmado && $archivoFirmado !== '') { $eLabel='Firmado'; $eCls='text-bg-success'; }
                elseif ($firmado)                       { $eLabel='Firmado'; $eCls='text-bg-success'; }
                else                                    { $eLabel='Sin firma'; $eCls='text-bg-warning'; }
            ?>
                <tr>
                    <td class="ps-4">
                        <div class="fw-semibold"><?= date('d/m/Y', strtotime((string)($inf['fecha'] ?? 'now'))) ?></div>
                        <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars((string)($inf['hora'] ?? '')) ?></div>
                    </td>
                    <td>
                        <code style="font-size:.78rem;"><?= htmlspecialchars($displayDni) ?></code>
                        <?php if ($expediente): ?><div class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars($expediente) ?></div><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($displayNombre) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($displayExtra) ?></td>
                    <td><span class="badge-m badge-m<?= strtolower($mid) ?>"><?= htmlspecialchars($mid) ?></span></td>
                    <td><span class="badge <?= $eCls ?>" style="font-size:.72rem;"><?= $eLabel ?></span></td>
                    <td class="text-end fw-semibold"><?= htmlspecialchars($displayTotal) ?></td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            <a class="adm-btn adm-btn-dl" href="descargar.php?f=<?= urlencode((string)($inf['archivo'] ?? '')) ?>&carpeta=<?= urlencode($carpetaInf) ?><?= $modeloParam ?>" title="Original"><i class="bi bi-download"></i></a>
                            <?php if ($archivoFirmado): ?>
                            <a class="adm-btn adm-btn-dlf" href="descargar.php?f=<?= urlencode($archivoFirmado) ?>&carpeta=<?= urlencode($carpetaInf) ?><?= $modeloParam ?>" title="Firmado"><i class="bi bi-patch-check"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-center">
                        <?php if (empty($adjInfos)): ?><span style="color:#ccc;">—</span>
                        <?php else: foreach ($adjInfos as $adj): ?>
                            <a href="descargar_adjunto.php?f=<?= urlencode((string)($adj['archivo'] ?? '')) ?>&carpeta=<?= urlencode($carpetaInf) ?><?= $modeloParam ?>" class="d-block text-truncate" style="font-size:.75rem;max-width:110px;color:#2d6a4f;" title="<?= htmlspecialchars((string)($adj['nombre'] ?? '')) ?>">
                                <i class="bi bi-paperclip"></i> <?= htmlspecialchars(substr((string)($adj['nombre'] ?? ''), 0, 16)) ?>
                            </a>
                        <?php endforeach; endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if (!$firmado): ?>
                            <a class="adm-btn adm-btn-sign" href="firmar_informe.php?f=<?= urlencode((string)($inf['archivo'] ?? '')) ?>&carpeta=<?= urlencode($carpetaInf) ?>&modelo=<?= urlencode($mid) ?>"><i class="bi bi-pen-fill"></i> Firmar</a>
                        <?php else: ?><span style="color:#ccc;">—</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php endif; // tab ?>

<?php endif; // logueado ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
