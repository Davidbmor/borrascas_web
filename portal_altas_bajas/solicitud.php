<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/functions.php';

$slug = (string)($_GET['template'] ?? '');
$template = $slug !== '' ? pa_get_template($slug) : null;

if ($template === null) {
    http_response_code(404);
    exit('No se encontró la plantilla solicitada.');
}

$okMessage = pa_flash('ok');
$errorMessage = pa_flash('error');
$noFields = empty($template['fields']);

function pa_render_form_field(array $field): string
{
    $name = (string)($field['name'] ?? '');
    $label = (string)($field['label'] ?? $name);
    $type = (string)($field['type'] ?? 'text');
    $required = !empty($field['required']) ? 'required' : '';
    $placeholder = (string)($field['placeholder'] ?? '');
    $help = (string)($field['help'] ?? '');
    $rows = max(2, (int)($field['rows'] ?? 4));
    $colClass = (string)($field['col'] ?? 'col-12 col-md-6');
    $autocomplete = (string)($field['autocomplete'] ?? '');

    $control = '';
    if ($type === 'textarea') {
        $control = '<textarea class="form-control form-control-lg" name="' . pa_h($name) . '" rows="' . $rows . '" placeholder="' . pa_h($placeholder) . '" ' . $required . '></textarea>';
    } else {
        $control = '<input class="form-control form-control-lg" type="' . pa_h($type) . '" name="' . pa_h($name) . '" placeholder="' . pa_h($placeholder) . '" ' . ($autocomplete !== '' ? 'autocomplete="' . pa_h($autocomplete) . '"' : '') . ' ' . $required . '>';
    }

    return '<div class="' . pa_h($colClass) . '"><label class="form-label fw-semibold">' . pa_h($label) . ' ' . ($required !== '' ? '<span class="text-danger">*</span>' : '') . '</label>' . $control . ($help !== '' ? '<div class="form-text">' . pa_h($help) . '</div>' : '') . '</div>';
}

?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= pa_h((string)($template['title'] ?? APP_NAME)) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
    <main class="portal-shell">
        <section class="container py-4 py-lg-5">
            <a class="text-decoration-none small fw-semibold text-uppercase letter-space" href="index.php"><i class="bi bi-arrow-left me-1"></i>Volver al inicio</a>
            <div class="form-hero mt-3 mb-4">
                <span class="eyebrow">Trámite: <?= pa_h((string)($template['slug'] ?? '')) ?></span>
                <h1 class="display-6 fw-bold mb-2"><?= pa_h((string)($template['title'] ?? 'Solicitud')) ?></h1>
                <p class="lead mb-0"><?= pa_h((string)($template['description'] ?? 'Rellena los campos, firma y envía el documento.')) ?></p>
            </div>

            <?php if ($okMessage): ?>
                <div class="alert alert-success shadow-sm"><?= pa_h($okMessage) ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger shadow-sm"><?= pa_h($errorMessage) ?></div>
            <?php endif; ?>
            <?php if ($noFields): ?>
                <div class="alert alert-warning shadow-sm">
                    <strong>Este trámite aún no tiene campos configurados.</strong>
                    El formulario no tiene campos que rellenar porque el administrador no ha definido ninguno todavía.
                    Puedes firmarlo y enviarlo igualmente, pero los datos quedarán vacíos en el PDF.
                    Para configurar los campos ve a <a href="admin.php" class="alert-link">Panel admin → Configurar campos</a>.
                </div>
            <?php endif; ?>

            <form class="card shadow-sm border-0 p-4 p-lg-5" method="post" action="guardar.php" enctype="multipart/form-data" id="solicitud-form">
                <?= pa_csrf_field() ?>
                <input type="hidden" name="template" value="<?= pa_h((string)$template['slug']) ?>">

                <div class="row g-4">
                    <?php foreach (($template['fields'] ?? []) as $field): ?>
                        <?= pa_render_form_field(is_array($field) ? $field : []) ?>
                    <?php endforeach; ?>
                </div>

                <hr class="my-4">

                <div class="row g-4 align-items-start">
                    <div class="col-12 col-lg-7">
                        <label class="form-label fw-semibold">Firma digitalizada <span class="text-danger">*</span></label>
                        <div class="signature-wrap">
                            <canvas id="signature-canvas" class="signature-canvas"></canvas>
                        </div>
                        <input type="hidden" name="firma_data" id="firma_data">
                        <div class="d-flex gap-2 mt-3 flex-wrap">
                            <button type="button" class="btn btn-outline-secondary" id="clear-signature"><i class="bi bi-eraser me-1"></i>Limpiar firma</button>
                            <small class="text-muted align-self-center">Firma con ratón o dedo.</small>
                        </div>
                    </div>
                    <div class="col-12 col-lg-5">
                        <div class="info-card h-100">
                            <h2 class="h5 fw-bold mb-3">Documentos adjuntos</h2>
                            <p class="text-muted mb-3">Si necesitas aportar archivos complementarios, súbelos aquí. El sistema limita peso y tipos para mantener el servidor seguro.</p>
                            <input class="form-control form-control-lg" type="file" name="adjuntos[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp">
                            <div class="form-text mt-2">Máximo <?= (int)MAX_ATTACHMENTS ?> archivos de hasta <?= pa_format_bytes(MAX_ATTACHMENT_BYTES) ?> cada uno.</div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex gap-2 flex-wrap justify-content-between align-items-center">
                    <div class="small text-muted">Los campos marcados con * son obligatorios.</div>
                    <button type="submit" class="btn btn-lg btn-primary px-4"><i class="bi bi-send me-1"></i>Enviar y generar PDF</button>
                </div>
            </form>
        </section>
    </main>

<script>
(function () {
    const canvas = document.getElementById('signature-canvas');
    const hiddenInput = document.getElementById('firma_data');
    const clearButton = document.getElementById('clear-signature');
    const form = document.getElementById('solicitud-form');
    const context = canvas.getContext('2d');
    let drawing = false;
    let hasInk = false;

    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const bounds = canvas.getBoundingClientRect();
        const imageData = hasInk ? canvas.toDataURL('image/png') : null;
        canvas.width = bounds.width * ratio;
        canvas.height = bounds.height * ratio;
        context.scale(ratio, ratio);
        context.lineWidth = 2.2;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.strokeStyle = '#1b4332';
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, bounds.width, bounds.height);
        if (imageData && imageData !== 'data:,') {
            const img = new Image();
            img.onload = () => context.drawImage(img, 0, 0, bounds.width, bounds.height);
            img.src = imageData;
        }
    }

    function pointerPosition(event) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: event.clientX - rect.left,
            y: event.clientY - rect.top,
        };
    }

    function startStroke(event) {
        drawing = true;
        const point = pointerPosition(event);
        context.beginPath();
        context.moveTo(point.x, point.y);
        event.preventDefault();
    }

    function drawStroke(event) {
        if (!drawing) return;
        const point = pointerPosition(event);
        context.lineTo(point.x, point.y);
        context.stroke();
        hasInk = true;
        event.preventDefault();
    }

    function endStroke() {
        drawing = false;
    }

    function clearSignature() {
        hasInk = false;
        const bounds = canvas.getBoundingClientRect();
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, bounds.width, bounds.height);
        hiddenInput.value = '';
    }

    window.addEventListener('resize', resizeCanvas);
    canvas.addEventListener('pointerdown', startStroke);
    canvas.addEventListener('pointermove', drawStroke);
    canvas.addEventListener('pointerup', endStroke);
    canvas.addEventListener('pointerleave', endStroke);
    clearButton.addEventListener('click', clearSignature);

    form.addEventListener('submit', function (event) {
        if (!hasInk) {
            event.preventDefault();
            alert('Debes dibujar la firma antes de enviar el trámite.');
            return;
        }
        hiddenInput.value = canvas.toDataURL('image/png');
    });

    resizeCanvas();
})();
</script>
</body>
</html>
