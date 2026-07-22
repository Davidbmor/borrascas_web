<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/config_m1.php';
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$informeOk = null;
if (!empty($_SESSION['informe_ok'])) {
    $informeOk = $_SESSION['informe_ok'];
    unset($_SESSION['informe_ok']);
}

$pageTitle  = 'Modelo 1 – Daños en Producción Oleícola';
$modelLabel = M1_LABEL . ' – ' . M1_TITULO;
$backUrl    = '../landing.php';
$assetBase  = '../';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="container pb-5">

    <!-- ALERTA DE ERRORES (se rellena por JS) -->
    <div id="alerta-errores" class="alert alert-danger d-none" role="alert"></div>
    <form id="form-borrascas" method="POST" action="procesar.php"
          enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- ═══════════════════════════════════════
             PASO 1 · DATOS PERSONALES
        ═══════════════════════════════════════ -->
        <div class="card shadow-sm mb-4">
            <div class="card-header section-header">
                <i class="bi bi-person-fill me-2"></i>Datos del solicitante
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <!-- DNI (verificación AJAX) -->
                    <div class="col-md-4">
                        <label for="dni" class="form-label fw-semibold">
                            DNI <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="text" id="dni" name="dni"
                                   class="form-control text-uppercase"
                                   placeholder="12345678A" maxlength="9" required
                                   autocomplete="off">
                            <span class="input-group-text" id="dni-estado">
                                <i class="bi bi-dash-circle text-secondary"></i>
                            </span>
                        </div>
                        <div id="dni-feedback" class="form-text text-muted"></div>
                    </div>

                    <!-- Nombre -->
                    <div class="col-md-8">
                        <label for="nombre" class="form-label fw-semibold">
                            Nombre y apellidos <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="nombre" name="nombre"
                               class="form-control" required maxlength="120">
                    </div>

                    <!-- Dirección desglosada -->
                    <div class="col-md-7">
                        <label for="calle" class="form-label fw-semibold">
                            Calle / Vía <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="calle" name="calle"
                               class="form-control" required maxlength="150"
                               placeholder="Ej: Calle Mayor">
                    </div>
                    <div class="col-md-2">
                        <label for="numero" class="form-label fw-semibold">
                            Número <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="numero" name="numero"
                               class="form-control" required maxlength="10"
                               placeholder="5">
                    </div>
                    <div class="col-md-3">
                        <label for="bloque" class="form-label fw-semibold">
                            Bloque / Portal
                            <span class="badge bg-secondary ms-1">Opc.</span>
                        </label>
                        <input type="text" id="bloque" name="bloque"
                               class="form-control" maxlength="20"
                               placeholder="Bl. 3">
                    </div>
                    <div class="col-md-3">
                        <label for="piso" class="form-label fw-semibold">
                            Piso / Puerta
                            <span class="badge bg-secondary ms-1">Opc.</span>
                        </label>
                        <input type="text" id="piso" name="piso"
                               class="form-control" maxlength="20"
                               placeholder="2º A">
                    </div>
                    <div class="col-md-4">
                        <label for="provincia" class="form-label fw-semibold">
                            Provincia <span class="text-danger">*</span>
                        </label>
                        <select id="provincia" name="provincia" class="form-select" required>
                            <option value="">Cargando provincias…</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="municipio" class="form-label fw-semibold">
                            Municipio <span class="text-danger">*</span>
                        </label>
                        <select id="municipio" name="municipio" class="form-select" required disabled>
                            <option value="">— Elige primero la provincia —</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="codigo_postal" class="form-label fw-semibold">
                            Código Postal <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="codigo_postal" name="codigo_postal"
                               class="form-control" required maxlength="5"
                               placeholder="18001" pattern="[0-9]{5}"
                               inputmode="numeric">
                    </div>

                    <!-- Teléfono -->
                    <div class="col-md-3">
                        <label for="telefono" class="form-label fw-semibold">
                            Teléfono <span class="text-danger">*</span>
                        </label>
                        <input type="tel" id="telefono" name="telefono"
                               class="form-control" required maxlength="20">
                    </div>

                    <!-- Email -->
                    <div class="col-md-3">
                        <label for="email" class="form-label fw-semibold">
                            Correo electrónico <span class="text-danger">*</span>
                        </label>
                        <input type="email" id="email" name="email"
                               class="form-control" required maxlength="120">
                    </div>

                    <!-- Cooperativa -->
                    <div class="col-md-6">
                        <label for="cooperativa" class="form-label fw-semibold">
                            Cooperativa <span class="text-danger">*</span>
                        </label>
                        <select id="cooperativa" name="cooperativa"
                                class="form-select" required>
                            <option value="">— Selecciona —</option>
                            <?php foreach (COOPERATIVAS as $coop): ?>
                                <option value="<?= htmlspecialchars($coop) ?>">
                                    <?= htmlspecialchars($coop) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div><!-- /row -->
            </div><!-- /card-body -->
        </div><!-- /card -->


        <input type="hidden" name="tipo_informe" value="1">

        <!-- ═══════════════════════════════════════
             MODELO 1 · DATOS DE PRODUCCIÓN
        ═══════════════════════════════════════ -->
        <div id="modelo1">

            <!-- DATOS DE CAMPAÑA (informativos) -->
            <div class="card shadow-sm mb-4 border-success">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-bar-chart-fill me-2"></i>Datos de campaña – Granada
                    <span class="badge bg-light text-success ms-2">Valores oficiales</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <tbody>
                            <tr>
                                <th class="ps-3">Previsión inicial de campaña en Granada</th>
                                <td class="text-end pe-3 fw-semibold">
                                    <?= number_format(PREVISION_GRANADA_TM, 0, ',', '.') ?> Tm
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-3">Previsión de cierre en Granada</th>
                                <td class="text-end pe-3 fw-semibold">
                                    <?= number_format(CIERRE_GRANADA_TM, 0, ',', '.') ?> Tm
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-3">Bajada en porcentaje</th>
                                <td class="text-end pe-3 fw-semibold"><?= BAJADA_PORCENTAJE ?>%</td>
                            </tr>
                            <tr>
                                <th class="ps-3">Rendimiento medio</th>
                                <td class="text-end pe-3 fw-semibold"><?= round(RENDIMIENTO_MEDIO * 100, 2) ?>%</td>
                            </tr>
                            <tr>
                                <th class="ps-3">Precio actual del Kg. AOVE</th>
                                <td class="text-end pe-3 fw-semibold">
                                    <?= number_format(PRECIO_KG_AOVE, 2, ',', '.') ?> €/Kg
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- DATOS A RELLENAR -->
            <div class="card shadow-sm mb-4">
                <div class="card-header section-header">
                    <i class="bi bi-pencil-fill me-2"></i>Datos de producción del solicitante
                </div>
                <div class="card-body">
                    <div class="row g-4">

                        <!-- Previsión inicial -->
                        <div class="col-md-4">
                            <label for="prev_inicial_kg" class="form-label fw-semibold">
                                Previsión inicial de producción
                                <span class="badge bg-secondary ms-1">Opcional</span>
                            </label>
                            <div class="input-group">
                                <input type="number" id="prev_inicial_kg" name="prev_inicial_kg"
                                       class="form-control calc-input" min="0" step="1"
                                       placeholder="Kgs. aceituna">
                                <span class="input-group-text">Kg</span>
                            </div>
                            <div class="calc-result mt-1 small text-muted" id="prev_inicial_result"></div>
                        </div>

                        <!-- Producción real -->
                        <div class="col-md-4">
                            <label for="prod_real_kg" class="form-label fw-semibold">
                                Producción real
                                <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" id="prod_real_kg" name="prod_real_kg"
                                       class="form-control calc-input" min="0" step="1"
                                       placeholder="Kgs. aceituna" required>
                                <span class="input-group-text">Kg</span>
                            </div>
                            <div class="calc-result mt-1 small text-muted" id="prod_real_result"></div>
                        </div>

                        <!-- Recolección -->
                        <div class="col-md-4">
                            <label for="recoleccion_kg" class="form-label fw-semibold">
                                Recolección aceituna
                                <small class="text-muted">(desde <?= FECHA_RECOLECCION ?>)</small>
                                <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" id="recoleccion_kg" name="recoleccion_kg"
                                       class="form-control calc-input" min="0" step="1"
                                       placeholder="Kgs. aceituna" required>
                                <span class="input-group-text">Kg</span>
                            </div>
                            <div class="calc-result mt-1 small text-muted" id="recoleccion_result"></div>
                        </div>

                        <!-- Varios -->
                        <div class="col-md-4">
                            <label for="varios_eur" class="form-label fw-semibold">Varios</label>
                            <div class="input-group">
                                <input type="number" id="varios_eur" name="varios_eur"
                                       class="form-control calc-input" min="0" step="0.01"
                                       placeholder="0,00">
                                <span class="input-group-text">€</span>
                            </div>
                        </div>

                    </div><!-- /row -->
                </div>
            </div>

            <!-- RESUMEN DE CÁLCULOS (live preview) -->
            <div class="card shadow-sm mb-4 border-primary" id="resumen-calculos" style="display:none!important">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-calculator-fill me-2"></i>Resumen de cálculos (vista previa)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 calc-table">
                            <thead class="table-dark">
                                <tr>
                                    <th>Concepto</th>
                                    <th class="text-end">Kgs. Aceituna</th>
                                    <th class="text-end">Kgs. Aceite</th>
                                    <th class="text-end">€/Kg</th>
                                    <th class="text-end">Total (€)</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-calculos">
                                <!-- rellena JS -->
                            </tbody>
                            <tfoot>
                                <tr class="table-warning fw-bold">
                                    <td colspan="4">TOTAL DAÑOS ESTIMADOS</td>
                                    <td class="text-end" id="total-eur">—</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

        </div><!-- /#modelo1 -->


        <!-- ═══════════════════════════════════════
             IMÁGENES / DOCUMENTACIÓN
        ═══════════════════════════════════════ -->
        <div class="card shadow-sm mb-4" id="seccion-imagenes" style="display:none">
            <div class="card-header section-header">
                <i class="bi bi-images me-2"></i>Imágenes como prueba de daños
                <span class="badge bg-secondary ms-2">Opcional · máx. <?= MAX_IMAGENES ?> fotos</span>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="imagenes" class="form-label">
                        Selecciona las imágenes (JPG, PNG, WebP · máx. 8 MB cada una)
                    </label>
                    <input type="file" id="imagenes" name="imagenes[]"
                           class="form-control" accept="image/jpeg,image/png,image/webp"
                           multiple>
                    <div id="imagenes-preview" class="row g-2 mt-2"></div>
                </div>
            </div>
        </div>


        <!-- ═══════════════════════════════════════
             DOCUMENTOS ADJUNTOS
        ═══════════════════════════════════════ -->
        <div class="card shadow-sm mb-4" id="seccion-adjuntos" style="display:none">
            <div class="card-header section-header">
                <i class="bi bi-paperclip me-2"></i>Documentos adjuntos
                <span class="badge bg-secondary ms-2">Opcional · máx. <?= MAX_ADJUNTOS ?> archivos · 8&nbsp;MB c/u</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    Adjunta documentos de apoyo al informe: fotografías adicionales, facturas, certificados…<br>
                    Se aceptan <strong>PDF e imágenes</strong> (JPG, PNG, WebP). Estos archivos se almacenan junto al informe y son descargables desde el panel de administración.
                </p>
                <div class="mb-3">
                    <label for="adjuntos" class="form-label">Selecciona archivos</label>
                    <input type="file" id="adjuntos" name="adjuntos[]"
                           class="form-control"
                           accept="image/jpeg,image/png,image/webp,application/pdf"
                           multiple>
                    <div id="adjuntos-lista" class="mt-2 small"></div>
                </div>
            </div>
        </div>


        <!-- ═══════════════════════════════════════
             FIRMA DIGITAL
        ═══════════════════════════════════════ -->
        <div id="seccion-firma" class="card shadow-sm mb-4" style="display:none">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-pen-fill me-2 text-success"></i>Firma del solicitante <span class="badge bg-secondary ms-2">Opcional</span>
            </div>
            <div class="card-body text-center">
                <p class="text-muted small mb-2">Si el interesado firma ahora, el informe quedará marcado como firmado desde el inicio. Si no, podréis firmarlo después desde el panel de administración.</p>
                <canvas id="firma-canvas"
                        style="border:2px solid #dee2e6;border-radius:6px;cursor:crosshair;max-width:100%;touch-action:none;background:#fff;"
                        width="600" height="170"></canvas>
                <br>
                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btn-borrar-firma">
                    <i class="bi bi-eraser me-1"></i>Borrar y repetir
                </button>
                <input type="hidden" name="firma_data" id="firma_data">
            </div>
        </div>

        <!-- ═══════════════════════════════════════
             BOTÓN ENVIAR
        ═══════════════════════════════════════ -->
        <div id="seccion-enviar" class="text-center" style="display:none">
            <p class="text-muted small mb-3">
                Al pulsar <strong>Generar informe PDF</strong>, se validarán los datos
                y se descargará el informe. Este formulario no almacena datos personales.
            </p>
            <button type="submit" class="btn btn-success btn-lg px-5" id="btn-generar">
                <i class="bi bi-file-earmark-check-fill me-2"></i>Enviar informe
            </button>
        </div>

    </form><!-- /form -->

</main>

<!-- MODAL ERRORES DE VALIDACIÓN -->
<div class="modal fade" id="modalErrores" tabindex="-1" aria-labelledby="modalErroresLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white py-2">
                <h5 class="modal-title" id="modalErroresLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Faltan datos obligatorios
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2 small">Revisa y corrige los siguientes campos:</p>
                <ul id="modal-errores-lista" class="mb-0 ps-3 text-danger small fw-semibold"></ul>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-danger btn-sm px-4" data-bs-dismiss="modal">
                    Corregir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ÉXITO -->
<div class="modal fade" id="modalExito" tabindex="-1" aria-labelledby="modalExitoLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5 px-4">
                <div class="mb-3" style="font-size:3.5rem;color:#2d6a4f;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h4 class="fw-bold mb-2">Informe tramitado correctamente</h4>
                <p class="text-muted mb-1">
                    El informe de <strong><?= $informeOk ? htmlspecialchars($informeOk['nombre']) : '' ?></strong>
                    ha sido registrado con éxito.
                </p>
                <p class="text-muted small mb-4">DNI: <code><?= $informeOk ? htmlspecialchars($informeOk['dni']) : '' ?></code></p>
                <button type="button" class="btn btn-success px-5" data-bs-dismiss="modal">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo informe
                </button>
            </div>
        </div>
    </div>
</div>

<footer class="border-top py-3 mt-4 text-center text-muted small">
    <?= TITULO_CAMPANA ?> · ACGranada
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Pasar constantes PHP a JS de forma segura
    const CONFIG = {
        rendimiento:        <?= RENDIMIENTO_MEDIO ?>,
        precioAOVE:         <?= PRECIO_KG_AOVE ?>,
        precioCalidad:      <?= PRECIO_CALIDAD_ACEITE ?>,
        sobrecosteRec:      <?= SOBRECOSTE_RECOLECCION ?>,
        sobrecosteProd:     <?= SOBRECOSTE_PRODUCCION ?>,
        maxImagenes:        <?= MAX_IMAGENES ?>,
        maxTamanoImg:       <?= MAX_TAMANO_IMG ?>,
        maxAdjuntos:        <?= MAX_ADJUNTOS ?>,
        maxTamanoAdjunto:   <?= MAX_TAMANO_ADJUNTO ?>
    };
</script>
<script src="../assets/js/formulario.js"></script>
<script>
// tipo_informe está fijo en 1: mostramos todas las secciones al cargar
document.addEventListener('DOMContentLoaded', function () {
    ['modelo1','seccion-imagenes','seccion-adjuntos','seccion-firma','seccion-enviar'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('d-none');
        el.style.display = '';
    });
    // Recalcular (resumen-calculos lo gestiona calcular() internamente)
    if (typeof calcular === 'function') calcular();
});
</script>
<?php if ($informeOk): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = new bootstrap.Modal(document.getElementById('modalExito'));
        modal.show();
        // Al cerrar el modal, limpiar el formulario
        document.getElementById('modalExito').addEventListener('hidden.bs.modal', () => {
            document.getElementById('form-borrascas').reset();
            // Resetear select de municipio (form.reset no re-deshabilita)
            const selMun = document.getElementById('municipio');
            selMun.innerHTML = '<option value="">— Elige primero la provincia —</option>';
            selMun.disabled  = true;
            document.getElementById('imagenes-preview').innerHTML = '';
            document.getElementById('modelo1').classList.add('d-none');
            document.getElementById('seccion-imagenes').style.display = 'none';
            document.getElementById('seccion-adjuntos').style.display = 'none';
            document.getElementById('adjuntos').value = '';
            document.getElementById('adjuntos-lista').innerHTML = '';
            document.getElementById('seccion-firma').style.display    = 'none';
            document.getElementById('seccion-enviar').style.display   = 'none';
            document.getElementById('resumen-calculos').style.display  = 'none';
            document.getElementById('tabla-calculos').innerHTML = '';
            document.getElementById('total-eur').textContent = '—';
            document.getElementById('dni-feedback').textContent = '';
            document.getElementById('dni-estado').innerHTML = '<i class="bi bi-dash-circle text-secondary"></i>';
            // Reiniciar variable de validación DNI en formulario.js
            if (typeof resetDniValidado === 'function') resetDniValidado();
            if (typeof limpiarFirma    === 'function') limpiarFirma();
        });
    });
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
