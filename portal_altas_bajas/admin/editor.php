<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/functions.php';

pa_require_admin();

$slug = (string)($_GET['template'] ?? '');
if ($slug === '') {
    header('Location: ../admin.php');
    exit;
}

$templates = pa_template_catalog();
$template = pa_get_template($slug);

// Si no existe aún el JSON de definición, crear uno vacío
if ($template === null) {
    $def = [
        'slug' => $slug,
        'title' => ucwords(str_replace(['-','_'], ' ', $slug)),
        'description' => '',
        'pdf' => $slug . '.pdf',
        'person_key' => 'dni',
        'fields' => [],
        'signature' => [],
    ];
    $definitionPath = DEFINITIONS_DIR . '/' . $slug . '.json';
    pa_save_json($definitionPath, $def);
    $template = pa_get_template($slug) ?? ['slug' => $slug, 'title' => $slug, 'pdf' => '', 'pdf_path' => '', 'pdf_exists' => false, 'fields' => [], 'signature' => [], 'person_key' => 'dni'];
}

$definitionFile = DEFINITIONS_DIR . '/' . $slug . '.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        pa_verify_csrf();
        $action = (string)$_POST['action'];
        $def = pa_load_json($definitionFile, []);

        if ($action === 'save_all') {
            $input = json_decode((string)($_POST['definition'] ?? '{}'), true);
            if (!is_array($input)) throw new RuntimeException('Definición inválida.');
            $def = array_merge($def, $input);
            pa_save_json($definitionFile, $def);
            echo json_encode(['ok' => true]);
            exit;
        }

        if ($action === 'save_meta') {
            $def['title'] = trim((string)($_POST['title'] ?? $def['title']));
            $def['description'] = trim((string)($_POST['description'] ?? $def['description'] ?? ''));
            $def['person_key'] = trim((string)($_POST['person_key'] ?? $def['person_key'] ?? 'dni'));
            pa_save_json($definitionFile, $def);
            echo json_encode(['ok' => true]);
            exit;
        }

        throw new RuntimeException('Acción desconocida.');
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

$currentDefinition = pa_load_json($definitionFile, []);
$totalPdfPages = 0;
if (!empty($template['pdf_exists'])) {
    try {
        $fpdi = new \setasign\Fpdi\Fpdi();
        $totalPdfPages = $fpdi->setSourceFile((string)$template['pdf_path']);
    } catch (\Throwable $e) {
        $totalPdfPages = 1;
    }
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editor de campos – <?= pa_h((string)($template['title'] ?? $slug)) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/app.css" rel="stylesheet">
    <style>
        body { background: #f0f4f0; }
        .editor-layout { display: grid; grid-template-columns: 1fr 360px; gap: 1.5rem; align-items: start; min-height: 100vh; padding: 1.5rem; }
        .pdf-panel { position: sticky; top: 1.5rem; }
        .page-wrap { position: relative; display: inline-block; cursor: crosshair; width: 100%; }
        .page-wrap canvas { width: 100% !important; height: auto !important; display: block; border-radius: .5rem; box-shadow: 0 4px 20px rgba(0,0,0,.18); }
        .field-overlay { position: absolute; border: 2px solid #1f6b4f; background: rgba(31,107,79,.12); border-radius: 3px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 600; color: #14533b; overflow: hidden; white-space: nowrap; padding: 1px 4px; transition: background .15s; }
        .field-overlay:hover { background: rgba(31,107,79,.28); }
        .field-overlay.type-signature { border-color: #6f42c1; background: rgba(111,66,193,.12); color: #6f42c1; }
        .field-overlay.selected { border-width: 3px; background: rgba(31,107,79,.22); }
        .resize-handle { position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; background: #1f6b4f; cursor: se-resize; border-radius: 2px; }
        .field-overlay.type-signature .resize-handle { background: #6f42c1; }
        .page-nav { display: flex; gap: .5rem; align-items: center; justify-content: center; margin-bottom: .75rem; }
        .sidebar { display: flex; flex-direction: column; gap: 1rem; }
        .sidebar-card { background: #fff; border-radius: .75rem; border: 1px solid #d8e3dd; padding: 1.25rem; }
        .field-list-item { display: flex; justify-content: space-between; align-items: center; padding: .4rem .6rem; border-radius: .4rem; border: 1px solid #d8e3dd; margin-bottom: .35rem; cursor: pointer; font-size: .85rem; }
        .field-list-item:hover { background: #f0f7f3; }
        .field-list-item.active { background: #e6f2eb; border-color: #1f6b4f; }
        .badge-type { font-size: .7rem; padding: .15rem .45rem; border-radius: 999px; background: #e6f2eb; color: #14533b; }
        .badge-type.sig { background: #f3ecff; color: #6f42c1; }
        @media (max-width: 900px) { .editor-layout { grid-template-columns: 1fr; } .pdf-panel { position: static; } }
    </style>
</head>
<body>
    <div class="editor-layout">
        <!-- PDF VIEWER -->
        <div class="pdf-panel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <a href="../admin.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Panel</a>
                <div class="fw-bold text-truncate px-2"><?= pa_h((string)($template['title'] ?? $slug)) ?></div>
                <?php if ($totalPdfPages > 1): ?>
                    <div class="page-nav">
                        <button class="btn btn-sm btn-outline-secondary" id="btn-prev-page">‹</button>
                        <span id="page-info" class="small text-muted px-1"></span>
                        <button class="btn btn-sm btn-outline-secondary" id="btn-next-page">›</button>
                    </div>
                <?php endif; ?>
                <div class="text-muted small" id="coords-display">Click para añadir campo</div>
            </div>

            <?php if (empty($template['pdf_exists'])): ?>
                <div class="alert alert-warning">No hay PDF plantilla para este trámite. Sube <strong><?= pa_h((string)($template['pdf'] ?? '')) ?></strong> a la carpeta <code>plantillas/</code>.</div>
            <?php else: ?>
                <div class="page-wrap" id="pdf-container">
                    <canvas id="pdf-canvas"></canvas>
                </div>
            <?php endif; ?>
        </div>

        <!-- SIDEBAR -->
        <div class="sidebar" id="sidebar">
            <!-- META -->
            <div class="sidebar-card">
                <h2 class="h6 fw-bold mb-3">Información del trámite</h2>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Título público</label>
                    <input class="form-control form-control-sm" id="meta-title" value="<?= pa_h((string)($currentDefinition['title'] ?? '')) ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Descripción</label>
                    <input class="form-control form-control-sm" id="meta-desc" value="<?= pa_h((string)($currentDefinition['description'] ?? '')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Campo DNI (para agrupar expedientes)</label>
                    <input class="form-control form-control-sm" id="meta-personkey" value="<?= pa_h((string)($currentDefinition['person_key'] ?? 'dni')) ?>">
                </div>
                <button class="btn btn-sm btn-outline-primary w-100" id="save-meta-btn">Guardar info</button>
            </div>

            <!-- ADD FIELD -->
            <div class="sidebar-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold mb-0" id="form-mode-title">Añadir campo</h2>
                    <button class="btn btn-xs btn-outline-secondary" id="new-field-btn" style="font-size:.78rem;padding:2px 10px;display:none;"><i class="bi bi-plus me-1"></i>Nuevo</button>
                </div>
                <p class="small text-muted mb-3">Haz clic en el PDF para posicionar cada campo o rellena coordenadas manualmente (mm desde esquina superior izquierda).</p>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Nombre interno <span class="text-danger">*</span></label>
                    <input class="form-control form-control-sm" id="field-name" placeholder="ej: nombre_completo">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Etiqueta visible</label>
                    <input class="form-control form-control-sm" id="field-label" placeholder="ej: Nombre y apellidos">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Tipo</label>
                    <select class="form-select form-select-sm" id="field-type">
                        <option value="text">Texto</option>
                        <option value="email">Email</option>
                        <option value="date">Fecha</option>
                        <option value="number">Número</option>
                        <option value="textarea">Área de texto</option>
                        <option value="signature">Firma</option>
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">X (mm)</label>
                        <input class="form-control form-control-sm" type="number" id="field-x" step="0.1" value="20">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Y (mm)</label>
                        <input class="form-control form-control-sm" type="number" id="field-y" step="0.1" value="50">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Ancho (mm)</label>
                        <input class="form-control form-control-sm" type="number" id="field-w" step="0.1" value="80">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Alto (mm)</label>
                        <input class="form-control form-control-sm" type="number" id="field-h" step="0.1" value="6">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Página</label>
                        <input class="form-control form-control-sm" type="number" id="field-page" value="1" min="1">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Tamaño fuente</label>
                        <input class="form-control form-control-sm" type="number" id="field-fontsize" value="9" min="6" max="18">
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="field-required" checked>
                    <label class="form-check-label small" for="field-required">Campo obligatorio</label>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-sm" id="add-field-btn"><i class="bi bi-plus-circle me-1"></i>Añadir campo</button>
                    <button class="btn btn-outline-secondary btn-sm" id="cancel-edit-btn" style="display:none;">Cancelar edición</button>
                </div>
            </div>

            <!-- FIELD LIST -->
            <div class="sidebar-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold mb-0">Campos definidos <span id="field-count" class="badge text-bg-secondary ms-1">0</span></h2>
                    <button class="btn btn-sm btn-success" id="save-all-btn"><i class="bi bi-floppy me-1"></i>Guardar todo</button>
                </div>
                <div id="field-list"></div>
                <div id="no-fields" class="text-muted small text-center py-2">Sin campos todavía.</div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
const CSRF_TOKEN = <?= json_encode(pa_csrf_token()) ?>;
const SLUG = <?= json_encode($slug) ?>;
const PDF_URL = <?= json_encode(empty($template['pdf_exists']) ? null : '../servir_plantilla.php?slug=' . urlencode($slug)) ?>;
const INITIAL_DEFINITION = <?= json_encode($currentDefinition, JSON_UNESCAPED_UNICODE) ?>;
const TOTAL_PAGES = <?= (int)$totalPdfPages ?>;

let state = {
    fields: Array.isArray(INITIAL_DEFINITION.fields) ? [...INITIAL_DEFINITION.fields] : [],
    signature: INITIAL_DEFINITION.signature && typeof INITIAL_DEFINITION.signature === 'object' && !Array.isArray(INITIAL_DEFINITION.signature) && Object.keys(INITIAL_DEFINITION.signature).length > 0 ? {...INITIAL_DEFINITION.signature} : null,
    currentPage: 1,
    pdfDoc: null,
    pdfPageWidth: 210,
    pdfPageHeight: 297,
    selectedField: null,
    addingMode: false,
    dragging: null,
    resizing: null,
};

// Signature field treated as special last item in list
function allItems() {
    const items = [...state.fields];
    if (state.signature) items.push({...state.signature, _isSig: true, type: 'signature', name: '_firma'});
    return items;
}

const pdfContainer = document.getElementById('pdf-container');
const pdfCanvas = document.getElementById('pdf-canvas');
const coordsDisplay = document.getElementById('coords-display');

// Load PDF with pdf.js
if (PDF_URL) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    pdfjsLib.getDocument(PDF_URL).promise.then(doc => {
        state.pdfDoc = doc;
        renderPage(state.currentPage);
    }).catch(err => {
        console.error('PDF error:', err);
        coordsDisplay.textContent = 'Error al cargar el PDF.';
    });
}

function renderPage(pageNum) {
    if (!state.pdfDoc) return;
    state.pdfDoc.getPage(pageNum).then(page => {
        const viewport = page.getViewport({ scale: 1 });
        state.pdfPageWidth = viewport.width * 25.4 / 72;  // px → mm (72dpi)
        state.pdfPageHeight = viewport.height * 25.4 / 72;
        const containerWidth = pdfContainer.offsetWidth || 600;
        const scale = containerWidth / viewport.width;
        const scaledViewport = page.getViewport({ scale });
        pdfCanvas.width = scaledViewport.width;
        pdfCanvas.height = scaledViewport.height;
        const ctx = pdfCanvas.getContext('2d');
        page.render({ canvasContext: ctx, viewport: scaledViewport }).promise.then(() => {
            renderOverlays();
        });
    });
    if (document.getElementById('page-info')) {
        document.getElementById('page-info').textContent = `Pág ${pageNum} / ${TOTAL_PAGES}`;
    }
}

function mm2px(mm, axis) {
    const w = pdfCanvas.offsetWidth || pdfCanvas.width;
    const h = pdfCanvas.offsetHeight || pdfCanvas.height;
    if (axis === 'x') return (mm / state.pdfPageWidth) * w;
    return (mm / state.pdfPageHeight) * h;
}

function px2mm(px, axis) {
    const w = pdfCanvas.offsetWidth || pdfCanvas.width;
    const h = pdfCanvas.offsetHeight || pdfCanvas.height;
    if (axis === 'x') return (px / w) * state.pdfPageWidth;
    return (px / h) * state.pdfPageHeight;
}

function renderOverlays() {
    // Remove existing overlays
    pdfContainer.querySelectorAll('.field-overlay').forEach(el => el.remove());

    allItems().forEach((item, idx) => {
        if ((item.page || 1) !== state.currentPage) return;
        const isSig = item._isSig || item.type === 'signature';

        const div = document.createElement('div');
        div.className = 'field-overlay' + (isSig ? ' type-signature' : '') + (state.selectedField === idx ? ' selected' : '');
        div.dataset.idx = String(idx);
        div.textContent = item.label || item.name || '—';
        div.style.left = mm2px(item.x || 0, 'x') + 'px';
        div.style.top = mm2px(item.y || 0, 'y') + 'px';
        div.style.width = mm2px(item.w || 60, 'x') + 'px';
        div.style.height = mm2px(item.h || 6, 'y') + 'px';

        const handle = document.createElement('div');
        handle.className = 'resize-handle';
        handle.addEventListener('pointerdown', startResize.bind(null, idx, div));
        div.appendChild(handle);

        div.addEventListener('pointerdown', startDrag.bind(null, idx, div));
        div.addEventListener('click', e => { e.stopPropagation(); selectField(idx); });

        pdfContainer.appendChild(div);
    });
}

function clearFieldForm() {
    state.selectedField = null;
    document.getElementById('field-name').value = '';
    document.getElementById('field-label').value = '';
    document.getElementById('field-type').value = 'text';
    document.getElementById('field-x').value = '20';
    document.getElementById('field-y').value = '50';
    document.getElementById('field-w').value = '80';
    document.getElementById('field-h').value = '6';
    document.getElementById('field-fontsize').value = '9';
    document.getElementById('field-required').checked = true;
    document.getElementById('add-field-btn').innerHTML = '<i class="bi bi-plus-circle me-1"></i>Añadir campo';
    document.getElementById('add-field-btn').className = 'btn btn-primary btn-sm';
    document.getElementById('cancel-edit-btn').style.display = 'none';
    document.getElementById('new-field-btn').style.display = 'none';
    document.getElementById('form-mode-title').textContent = 'Añadir campo';
    renderOverlays();
    renderFieldList();
}

function selectField(idx) {
    state.selectedField = idx;
    const items = allItems();
    const item = items[idx];
    if (!item) return;

    if (!item._isSig) {
        document.getElementById('field-name').value = item.name || '';
        document.getElementById('field-label').value = item.label || '';
        document.getElementById('field-type').value = item.type || 'text';
    } else {
        document.getElementById('field-name').value = '';
        document.getElementById('field-label').value = '';
        document.getElementById('field-type').value = 'signature';
    }
    document.getElementById('field-x').value = Number(item.x || 0).toFixed(1);
    document.getElementById('field-y').value = Number(item.y || 0).toFixed(1);
    document.getElementById('field-w').value = Number(item.w || 60).toFixed(1);
    document.getElementById('field-h').value = Number(item.h || 6).toFixed(1);
    document.getElementById('field-page').value = item.page || 1;
    document.getElementById('field-fontsize').value = item.font_size || 9;
    document.getElementById('field-required').checked = item.required !== false;

    // Switch UI to edit mode
    document.getElementById('add-field-btn').innerHTML = '<i class="bi bi-pencil me-1"></i>Actualizar campo';
    document.getElementById('add-field-btn').className = 'btn btn-warning btn-sm';
    document.getElementById('cancel-edit-btn').style.display = '';
    document.getElementById('new-field-btn').style.display = '';
    document.getElementById('form-mode-title').textContent = 'Editando campo';

    renderOverlays();
    renderFieldList();
}

// Drag & resize
let dragStart = null;
let resizeStart = null;

function startDrag(idx, el, e) {
    if (e.target.classList.contains('resize-handle')) return;
    e.stopPropagation();
    selectField(idx);
    const rect = el.getBoundingClientRect();
    dragStart = { idx, startX: e.clientX, startY: e.clientY, origX: allItems()[idx].x, origY: allItems()[idx].y };
    window.addEventListener('pointermove', onDrag);
    window.addEventListener('pointerup', stopDrag, { once: true });
    e.preventDefault();
}

function onDrag(e) {
    if (!dragStart) return;
    const dx = e.clientX - dragStart.startX;
    const dy = e.clientY - dragStart.startY;
    const newX = Math.max(0, dragStart.origX + px2mm(dx, 'x'));
    const newY = Math.max(0, dragStart.origY + px2mm(dy, 'y'));
    const items = allItems();
    const item = items[dragStart.idx];
    if (item._isSig) {
        state.signature.x = +newX.toFixed(1);
        state.signature.y = +newY.toFixed(1);
    } else {
        state.fields[dragStart.idx].x = +newX.toFixed(1);
        state.fields[dragStart.idx].y = +newY.toFixed(1);
    }
    document.getElementById('field-x').value = newX.toFixed(1);
    document.getElementById('field-y').value = newY.toFixed(1);
    renderOverlays();
}

function stopDrag() {
    dragStart = null;
    window.removeEventListener('pointermove', onDrag);
}

function startResize(idx, el, e) {
    e.stopPropagation();
    resizeStart = { idx, startX: e.clientX, startY: e.clientY, origW: allItems()[idx].w, origH: allItems()[idx].h };
    window.addEventListener('pointermove', onResize);
    window.addEventListener('pointerup', stopResize, { once: true });
    e.preventDefault();
}

function onResize(e) {
    if (!resizeStart) return;
    const dx = e.clientX - resizeStart.startX;
    const dy = e.clientY - resizeStart.startY;
    const newW = Math.max(10, resizeStart.origW + px2mm(dx, 'x'));
    const newH = Math.max(4, resizeStart.origH + px2mm(dy, 'y'));
    const items = allItems();
    const item = items[resizeStart.idx];
    if (item._isSig) {
        state.signature.w = +newW.toFixed(1);
        state.signature.h = +newH.toFixed(1);
    } else {
        state.fields[resizeStart.idx].w = +newW.toFixed(1);
        state.fields[resizeStart.idx].h = +newH.toFixed(1);
    }
    document.getElementById('field-w').value = newW.toFixed(1);
    document.getElementById('field-h').value = newH.toFixed(1);
    renderOverlays();
}

function stopResize() {
    resizeStart = null;
    window.removeEventListener('pointermove', onResize);
}

// Click on PDF to set position
if (pdfContainer) {
    pdfContainer.addEventListener('click', e => {
        if (e.target !== pdfCanvas) return;
        const rect = pdfCanvas.getBoundingClientRect();
        const x = px2mm(e.clientX - rect.left, 'x');
        const y = px2mm(e.clientY - rect.top, 'y');
        document.getElementById('field-x').value = x.toFixed(1);
        document.getElementById('field-y').value = y.toFixed(1);
        document.getElementById('field-page').value = state.currentPage;
        coordsDisplay.textContent = `X: ${x.toFixed(1)} mm  Y: ${y.toFixed(1)} mm`;
    });

    pdfContainer.addEventListener('mousemove', e => {
        if (e.target !== pdfCanvas) return;
        const rect = pdfCanvas.getBoundingClientRect();
        const x = px2mm(e.clientX - rect.left, 'x');
        const y = px2mm(e.clientY - rect.top, 'y');
        coordsDisplay.textContent = `X: ${x.toFixed(1)} mm  Y: ${y.toFixed(1)} mm`;
    });
}

// Page navigation
document.getElementById('btn-prev-page')?.addEventListener('click', () => {
    if (state.currentPage > 1) { state.currentPage--; renderPage(state.currentPage); }
});
document.getElementById('btn-next-page')?.addEventListener('click', () => {
    if (state.currentPage < TOTAL_PAGES) { state.currentPage++; renderPage(state.currentPage); }
});

// Add/update field
document.getElementById('add-field-btn').addEventListener('click', () => {
    const name = document.getElementById('field-name').value.trim();
    const label = document.getElementById('field-label').value.trim();
    const type = document.getElementById('field-type').value;
    const x = parseFloat(document.getElementById('field-x').value) || 0;
    const y = parseFloat(document.getElementById('field-y').value) || 0;
    const w = parseFloat(document.getElementById('field-w').value) || 80;
    const h = parseFloat(document.getElementById('field-h').value) || 6;
    const page = parseInt(document.getElementById('field-page').value) || 1;
    const fontSize = parseInt(document.getElementById('field-fontsize').value) || 9;
    const required = document.getElementById('field-required').checked;

    if (type === 'signature') {
        state.signature = { page, x, y, w, h };
        clearFieldForm();
        return;
    }

    if (!name) { alert('El nombre interno es obligatorio.'); return; }
    const fieldData = { name, label: label || name, type, page, x, y, w, h, font_size: fontSize, required };

    if (state.selectedField !== null && state.selectedField < state.fields.length) {
        // Edit mode: update existing
        state.fields[state.selectedField] = fieldData;
    } else {
        // Add mode: push new field
        state.fields.push(fieldData);
    }

    // Always return to add mode after saving
    clearFieldForm();
});

// Delete selected field
function deleteField(idx) {
    const items = allItems();
    const item = items[idx];
    if (item._isSig) {
        state.signature = null;
    } else {
        state.fields.splice(idx, 1);
    }
    clearFieldForm();
}

document.getElementById('cancel-edit-btn').addEventListener('click', clearFieldForm);
document.getElementById('new-field-btn').addEventListener('click', clearFieldForm);

function renderFieldList() {
    const list = document.getElementById('field-list');
    const noFields = document.getElementById('no-fields');
    const countBadge = document.getElementById('field-count');
    const items = allItems();
    list.innerHTML = '';
    noFields.style.display = items.length ? 'none' : 'block';
    countBadge.textContent = String(items.length);

    items.forEach((item, idx) => {
        const isSig = item._isSig || item.type === 'signature';
        const row = document.createElement('div');
        row.className = 'field-list-item' + (state.selectedField === idx ? ' active' : '');
        row.innerHTML = `
            <div class="d-flex align-items-center gap-2">
                <span class="badge-type ${isSig ? 'sig' : ''}">${isSig ? 'firma' : (item.type || 'text')}</span>
                <span class="fw-semibold">${item.label || item.name || 'Firma'}</span>
            </div>
            <div class="d-flex gap-1">
                <button class="btn btn-xs btn-outline-secondary" style="padding:1px 6px;font-size:.75rem;" data-edit="${idx}"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-xs btn-outline-danger" style="padding:1px 6px;font-size:.75rem;" data-del="${idx}"><i class="bi bi-trash"></i></button>
            </div>`;
        row.querySelector('[data-edit]').addEventListener('click', e => { e.stopPropagation(); selectField(idx); });
        row.querySelector('[data-del]').addEventListener('click', e => { e.stopPropagation(); deleteField(idx); });
        row.addEventListener('click', () => selectField(idx));
        list.appendChild(row);
    });
}

// Save meta
document.getElementById('save-meta-btn').addEventListener('click', () => {
    const data = new FormData();
    data.append('action', 'save_meta');
    data.append('csrf_token', CSRF_TOKEN);
    data.append('title', document.getElementById('meta-title').value.trim());
    data.append('description', document.getElementById('meta-desc').value.trim());
    data.append('person_key', document.getElementById('meta-personkey').value.trim() || 'dni');
    fetch('', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => { if (res.ok) alert('Información guardada.'); else alert('Error: ' + res.error); });
});

// Save all
document.getElementById('save-all-btn').addEventListener('click', () => {
    const definition = {
        title: document.getElementById('meta-title').value.trim() || INITIAL_DEFINITION.title || SLUG,
        description: document.getElementById('meta-desc').value.trim() || '',
        person_key: document.getElementById('meta-personkey').value.trim() || 'dni',
        fields: state.fields,
        signature: state.signature || {},
    };
    const formData = new FormData();
    formData.append('action', 'save_all');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('definition', JSON.stringify(definition));
    fetch('', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.ok) alert('Definición guardada correctamente.');
            else alert('Error: ' + res.error);
        });
});

// Init
renderFieldList();
</script>
</body>
</html>
