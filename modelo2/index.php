<?php
declare(strict_types=1);
require_once __DIR__ . '/config_m2.php';
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$informeOk = null;
if (!empty($_SESSION['informe_ok_m2'])) {
    $informeOk = $_SESSION['informe_ok_m2'];
    unset($_SESSION['informe_ok_m2']);
}

$pageTitle  = 'Modelo 2 – Evaluación de Daños por Borrascas';
$modelLabel = M2_LABEL . ' – ' . M2_TITULO;
$backUrl    = '../landing.php';
$assetBase  = '../';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="container pb-5">

    <div id="alerta-errores" class="alert alert-danger d-none" role="alert"></div>

    <!-- Título + número de expediente -->
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3">
        <div>
            <h2 class="h4 fw-bold mb-1">Informe Técnico: Evaluación de Daños por Borrascas</h2>
            <p class="text-muted mb-0"><?= htmlspecialchars(M2_TITULO_CAMPANA) ?> &middot; <?= htmlspecialchars(M2_PROVINCIA) ?></p>
        </div>
        <div class="num-expediente-badge">
            <i class="bi bi-hash"></i>M2-<?= date('Y') ?>-XXXX
        </div>
    </div>

    <!-- ÍNDICE -->
    <div class="indice-informe mb-4">
        <h3><i class="bi bi-list-ol me-2"></i>Contenido del informe</h3>
        <ol>
            <li><a href="#sec-solicitante" class="text-decoration-none">Datos del solicitante</a></li>
            <li><a href="#sec-objeto" class="text-decoration-none">Objeto del informe</a></li>
            <li><a href="#sec-explotacion" class="text-decoration-none">Datos de la explotación</a></li>
            <li class="text-muted">Introducción y contexto meteorológico <small>(texto fijo)</small></li>
            <li class="text-muted">Metodología y fuentes de información <small>(texto fijo)</small></li>
            <li class="text-muted">Descripción de daños <small>(en preparación)</small></li>
            <li class="text-muted">Valoración de daños y pérdida de renta <small>(en preparación)</small></li>
            <li class="text-muted">Conclusión <small>(texto fijo)</small></li>
            <li><a href="#sec-anexo" class="text-decoration-none">Anexo: fotografías del cultivo afectado</a></li>
        </ol>
    </div>

    <?php if ($informeOk): ?>
        <div class="alert alert-success shadow-sm">
            <strong>Informe generado.</strong> <?= htmlspecialchars($informeOk['nombre'] ?? '') ?> – Exp. <?= htmlspecialchars($informeOk['expediente'] ?? '') ?>
        </div>
    <?php endif; ?>

    <form id="form-m2" method="POST" action="procesar.php" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- 1. DATOS DEL SOLICITANTE ════════════════════════════ -->
        <div id="sec-solicitante" class="card shadow-sm mb-4">
            <div class="card-header section-header">
                <i class="bi bi-building me-2"></i>1. Datos del solicitante
            </div>
            <div class="card-body p-4">

                <h6 class="fw-bold text-muted text-uppercase small mb-3">Titular</h6>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-8">
                        <label class="form-label fw-semibold">Razón Social <span class="text-danger">*</span></label>
                        <input type="text" name="razon_social" class="form-control" required
                               placeholder="Nombre de la empresa o explotación">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">CIF/NIF <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="cif_nif" id="cif_nif" class="form-control"
                                   placeholder="A12345678 / 12345678Z" required maxlength="12"
                                   style="text-transform:uppercase">
                            <span class="input-group-text" id="cif-estado"><i class="bi bi-dash-circle text-secondary"></i></span>
                        </div>
                        <div id="cif-feedback" class="form-text"></div>
                    </div>
                </div>

                <h6 class="fw-bold text-muted text-uppercase small mb-3">Representante</h6>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-8">
                        <label class="form-label fw-semibold">Nombre y apellidos <span class="text-danger">*</span></label>
                        <input type="text" name="representante_nombre" class="form-control" required
                               placeholder="Nombre completo del representante">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">DNI/NIE <span class="text-danger">*</span></label>
                        <input type="text" name="representante_dni" class="form-control" required
                               maxlength="10" style="text-transform:uppercase"
                               placeholder="12345678Z">
                    </div>
                </div>

                <h6 class="fw-bold text-muted text-uppercase small mb-3">Domicilio</h6>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold">Calle / Avenida <span class="text-danger">*</span></label>
                        <input type="text" name="calle" class="form-control" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-semibold">Nº <span class="text-danger">*</span></label>
                        <input type="text" name="numero" class="form-control" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-semibold">Bloque/Portal</label>
                        <input type="text" name="bloque" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-semibold">Piso/Puerta</label>
                        <input type="text" name="piso" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-semibold">C.P. <span class="text-danger">*</span></label>
                        <input type="text" name="codigo_postal" class="form-control"
                               maxlength="5" pattern="\d{5}" required placeholder="18000">
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold">Provincia <span class="text-danger">*</span></label>
                        <select name="provincia" id="m2-provincia" class="form-select" required>
                            <option value="">Seleccionar provincia…</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold">Municipio <span class="text-danger">*</span></label>
                        <select name="municipio" id="m2-municipio" class="form-select" required disabled>
                            <option value="">Selecciona primero una provincia…</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Teléfono móvil <span class="text-danger">*</span></label>
                        <input type="tel" name="telefono" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold">Correo electrónico <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. OBJETO DEL INFORME ════════════════════════════════ -->
        <div id="sec-objeto" class="card shadow-sm mb-4">
            <div class="card-header section-header">
                <i class="bi bi-file-text me-2"></i>2. Objeto del informe
            </div>
            <div class="card-body p-4">
                <div class="alert alert-light border mb-3 small">
                    El texto del objeto se genera automáticamente en el PDF. Indica la ubicación de la explotación.
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold">Localidad de la explotación <span class="text-danger">*</span></label>
                        <input type="text" name="localidad_exp" class="form-control" required
                               placeholder="Localidad donde se ubica">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Comarca <span class="text-danger">*</span></label>
                        <input type="text" name="comarca" class="form-control" required
                               placeholder="Ej: Montes Orientales">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-semibold">Provincia</label>
                        <input type="text" name="provincia_exp" class="form-control" value="Granada">
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. DATOS DE LA EXPLOTACIÓN ══════════════════════════ -->
        <div id="sec-explotacion" class="card shadow-sm mb-4">
            <div class="card-header section-header">
                <i class="bi bi-geo-alt me-2"></i>3. Datos de la explotación
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Cód. REAFA <span class="text-danger">*</span></label>
                        <input type="text" name="reafa" class="form-control" required
                               placeholder="Código de explotación REAFA">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Provincia <span class="text-danger">*</span></label>
                        <select name="exp_provincia" id="m2-exp-provincia" class="form-select" required>
                            <option value="">Seleccionar…</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Municipio <span class="text-danger">*</span></label>
                        <select name="exp_municipio" id="m2-exp-municipio" class="form-select" required disabled>
                            <option value="">Primero elige provincia…</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Localidad <span class="text-danger">*</span></label>
                        <input type="text" name="exp_localidad" class="form-control" required>
                    </div>
                </div>

                <h6 class="fw-bold text-muted text-uppercase small mb-3">Cultivo</h6>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-semibold">Cultivo</label>
                        <input type="text" class="form-control bg-light" value="Olivar" readonly>
                        <input type="hidden" name="cultivo" value="Olivar">
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold">Variedad <span class="text-danger">*</span></label>
                        <input type="text" name="variedad" class="form-control" required
                               placeholder="Ej: Picual, Hojiblanca, Picudo…">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Edad del cultivo (años)</label>
                        <input type="number" name="edad_cultivo" class="form-control"
                               min="1" max="999" placeholder="Ej: 40">
                    </div>
                </div>

                <h6 class="fw-bold text-muted text-uppercase small mb-3">Superficie y sistema</h6>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <label class="form-label fw-semibold">Secano (ha) <span class="text-danger">*</span></label>
                        <input type="number" name="sup_secano" id="sup_secano"
                               class="form-control" min="0" step="0.01" value="0" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Sistema secano</label>
                        <select name="sup_secano_tipo" class="form-select">
                            <option value="">Seleccionar…</option>
                            <?php foreach (M2_SISTEMAS_CULTIVO as $k => $v): ?>
                                <option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fw-semibold">Regadío (ha) <span class="text-danger">*</span></label>
                        <input type="number" name="sup_regadio" id="sup_regadio"
                               class="form-control" min="0" step="0.01" value="0" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Sistema regadío</label>
                        <select name="sup_regadio_tipo" class="form-select">
                            <option value="">Seleccionar…</option>
                            <?php foreach (M2_SISTEMAS_CULTIVO as $k => $v): ?>
                                <option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fw-semibold">Superficie total (ha)</label>
                        <div class="input-group">
                            <input type="number" name="superficie_total" id="superficie_total"
                                   class="form-control bg-light fw-bold" readonly value="0">
                            <span class="input-group-text text-success" title="Calculado automáticamente"><i class="bi bi-calculator"></i></span>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label fw-semibold">Sistema de explotación</label>
                        <input type="text" name="sistema_explotacion" id="sistema_explotacion"
                               class="form-control bg-light" readonly
                               placeholder="Secano / Regadío / Mixto">
                        <div class="form-text">Calculado según superficies.</div>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold">Sistema de cultivo <span class="text-danger">*</span></label>
                        <select name="sistema_cultivo" class="form-select" required>
                            <option value="">Seleccionar…</option>
                            <?php foreach (M2_SISTEMAS_CULTIVO as $k => $v): ?>
                                <option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aviso secciones 4-8 ════════════════════════════════ -->
        <div class="card shadow-sm mb-4 border-0 bg-light">
            <div class="card-body p-4 d-flex gap-3 align-items-start">
                <i class="bi bi-info-circle-fill text-primary fs-4 mt-1 flex-shrink-0"></i>
                <div>
                    <h5 class="fw-bold mb-1">Secciones 4 a 8 – Contenido técnico</h5>
                    <p class="mb-0 text-muted small">La introducción meteorológica, metodología, descripción de daños, valoración económica y conclusión se incluirán en el PDF de forma automática con el texto técnico estándar. Los cálculos de la sección 7 se añadirán en la siguiente versión.</p>
                </div>
            </div>
        </div>

        <!-- 9. FOTOGRAFÍAS ════════════════════════════════════════ -->
        <div id="sec-anexo" class="card shadow-sm mb-4">
            <div class="card-header section-header">
                <i class="bi bi-images me-2"></i>9. Anexo: fotografías del cultivo afectado
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-3">Adjunta fotografías que acrediten los daños visibles en la explotación.</p>
                <input type="file" name="imagenes[]" id="imagenes-m2"
                       class="form-control mb-3"
                       accept="image/jpeg,image/png,image/webp" multiple>
                <div id="preview-imagenes-m2" class="d-flex flex-wrap gap-2"></div>
                <div class="form-text mt-2">
                    Máximo <?= MAX_IMAGENES ?> imágenes · hasta <?= number_format(MAX_TAMANO_IMG / 1048576, 0) ?> MB cada una.
                </div>
            </div>
        </div>

        <!-- FIRMA ════════════════════════════════════════════════ -->
        <div class="card shadow-sm mb-4">
            <div class="card-header section-header">
                <i class="bi bi-pen me-2"></i>Firma del solicitante
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-3">Firma en el recuadro. Aparecerá en el documento PDF generado.</p>
                <canvas id="firma-canvas-m2"
                        style="width:100%;max-width:700px;height:180px;border:2px solid #c3dac8;border-radius:.5rem;background:#fff;cursor:crosshair;touch-action:none;display:block;"></canvas>
                <input type="hidden" name="firma_data" id="firma_data_m2">
                <button type="button" class="btn btn-outline-secondary btn-sm mt-3" id="limpiar-firma-m2">
                    <i class="bi bi-eraser me-1"></i>Limpiar firma
                </button>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
            <div class="text-muted small">Los campos con <span class="text-danger">*</span> son obligatorios.</div>
            <button type="submit" class="btn btn-primary btn-lg px-5" id="btn-enviar-m2">
                <i class="bi bi-send me-2"></i>Generar informe PDF
            </button>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Provincia / Municipio — usa el mismo geo.php que el modelo 1
function cargarProvincias(sel) {
    fetch('geo.php?tipo=provincias')
        .then(r => r.json())
        .then(data => {
            sel.innerHTML = '<option value="">Seleccionar provincia…</option>';
            data.forEach(p => {
                const o = document.createElement('option');
                o.value = p.nombre;
                o.textContent = p.nombre;
                o.dataset.cpro = p.cpro;
                sel.appendChild(o);
            });
        }).catch(() => { sel.innerHTML = '<option value="">Error al cargar provincias</option>'; });
}
function vincular(provSel, munSel) {
    cargarProvincias(provSel);
    provSel.addEventListener('change', () => {
        munSel.disabled = true;
        munSel.innerHTML = '<option value="">Cargando…</option>';
        const cpro = provSel.selectedOptions[0]?.dataset?.cpro;
        if (!cpro) { munSel.innerHTML = '<option value="">Selecciona primero provincia…</option>'; return; }
        fetch('geo.php?tipo=municipios&cpro=' + encodeURIComponent(cpro))
            .then(r => r.json())
            .then(data => {
                munSel.innerHTML = '<option value="">Seleccionar municipio…</option>';
                const items = Array.isArray(data) ? data : (data.municipios || []);
                items.forEach(m => {
                    const o = document.createElement('option');
                    o.value = o.textContent = (typeof m === 'string') ? m : (m.nombre || m.cmun || m);
                    munSel.appendChild(o);
                });
                munSel.disabled = false;
            }).catch(() => { munSel.innerHTML = '<option value="">Error al cargar</option>'; munSel.disabled = false; });
    });
}
vincular(document.getElementById('m2-provincia'),     document.getElementById('m2-municipio'));
vincular(document.getElementById('m2-exp-provincia'), document.getElementById('m2-exp-municipio'));

// Superficies
function calcSup() {
    const s = parseFloat(document.getElementById('sup_secano').value) || 0;
    const r = parseFloat(document.getElementById('sup_regadio').value) || 0;
    document.getElementById('superficie_total').value = (s + r).toFixed(2);
    document.getElementById('sistema_explotacion').value =
        s > 0 && r === 0 ? 'Secano' : r > 0 && s === 0 ? 'Regadío' : s > 0 && r > 0 ? 'Mixto' : '';
}
document.getElementById('sup_secano').addEventListener('input', calcSup);
document.getElementById('sup_regadio').addEventListener('input', calcSup);

// Canvas firma
(function () {
    const canvas = document.getElementById('firma-canvas-m2');
    const hidden = document.getElementById('firma_data_m2');
    const ctx = canvas.getContext('2d');
    let drawing = false, hasInk = false;
    function resize() {
        const r = window.devicePixelRatio || 1, b = canvas.getBoundingClientRect();
        const saved = hasInk ? canvas.toDataURL() : null;
        canvas.width = b.width * r; canvas.height = b.height * r;
        ctx.scale(r, r); ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
        ctx.strokeStyle = '#1b4332'; ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, b.width, b.height);
        if (saved) { const img = new Image(); img.onload = () => ctx.drawImage(img, 0, 0, b.width, b.height); img.src = saved; }
    }
    function pt(e) { const b = canvas.getBoundingClientRect(); return { x: e.clientX - b.left, y: e.clientY - b.top }; }
    canvas.addEventListener('pointerdown', e => { drawing = true; const p = pt(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); });
    canvas.addEventListener('pointermove', e => { if (!drawing) return; const p = pt(e); ctx.lineTo(p.x, p.y); ctx.stroke(); hasInk = true; e.preventDefault(); });
    canvas.addEventListener('pointerup',    () => drawing = false);
    canvas.addEventListener('pointerleave', () => drawing = false);
    document.getElementById('limpiar-firma-m2').addEventListener('click', () => {
        hasInk = false; const b = canvas.getBoundingClientRect();
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, b.width, b.height); hidden.value = '';
    });
    window.addEventListener('resize', resize);
    resize();
    document.getElementById('form-m2').addEventListener('submit', e => {
        const alertEl = document.getElementById('alerta-errores');
        if (!hasInk) {
            e.preventDefault(); alertEl.classList.remove('d-none');
            alertEl.textContent = 'Debes dibujar la firma antes de enviar.';
            window.scrollTo(0, 0); return;
        }
        hidden.value = canvas.toDataURL('image/png');
    });
})();

// Preview imágenes
document.getElementById('imagenes-m2').addEventListener('change', function () {
    const wrap = document.getElementById('preview-imagenes-m2');
    wrap.innerHTML = '';
    Array.from(this.files).forEach(f => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'height:80px;border-radius:4px;border:1px solid #ccc;object-fit:cover;';
            wrap.appendChild(img);
        };
        reader.readAsDataURL(f);
    });
// Validación básica de CIF/NIF
(function () {
    const input    = document.getElementById('cif_nif');
    const estado   = document.getElementById('cif-estado');
    const feedback = document.getElementById('cif-feedback');
    if (!input) return;

    function validarCifNif(val) {
        val = val.trim().toUpperCase();
        // DNI: 8 dígitos + letra
        const esDni = /^[0-9]{8}[A-Z]$/.test(val);
        // CIF: letra + 7 dígitos + letra/dígito
        const esCif = /^[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J]$/.test(val);
        // NIE: X/Y/Z + 7 dígitos + letra
        const esNie = /^[XYZ][0-9]{7}[A-Z]$/.test(val);
        return esDni || esCif || esNie;
    }

    input.addEventListener('input', function () {
        const val = this.value.trim().toUpperCase();
        this.value = val;
        if (val.length < 8) {
            estado.innerHTML = '<i class="bi bi-dash-circle text-secondary"></i>';
            feedback.textContent = '';
            return;
        }
        if (validarCifNif(val)) {
            estado.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
            feedback.textContent = 'Formato válido';
            feedback.className = 'form-text text-success';
        } else {
            estado.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
            feedback.textContent = 'Formato no reconocido (ej: A12345678, 12345678Z, X1234567A)';
            feedback.className = 'form-text text-danger';
        }
    });
})();
</script>
</body>
</html>
