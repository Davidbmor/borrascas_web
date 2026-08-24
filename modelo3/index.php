<?php
declare(strict_types=1);
require_once __DIR__ . '/config_m3.php';
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$informeOk = null;
if (!empty($_SESSION['informe_ok_m3'])) {
    $informeOk = $_SESSION['informe_ok_m3'];
    unset($_SESSION['informe_ok_m3']);
}

$formError = null;
if (!empty($_SESSION['form_error'])) {
    $formError = $_SESSION['form_error'];
    unset($_SESSION['form_error']);
}

$oldData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

function old(string $key, string $default = '', array $data = []): string {
    return htmlspecialchars((string)($data[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

function oldSelect(string $key, string $value, array $data = [], bool $isDefault = false): string {
    if (isset($data[$key])) {
        return (string)$data[$key] === $value ? 'selected' : '';
    }
    return $isDefault ? 'selected' : '';
}

function oldCheck(string $key, string $value, array $data = [], bool $isDefault = false): string {
    if (isset($data[$key]) && is_array($data[$key])) {
        return in_array($value, $data[$key], true) ? 'checked' : '';
    }
    return $isDefault ? 'checked' : '';
}

$pageTitle  = 'Modelo 3 – Evaluación de Daños por Borrascas';
$modelLabel = M3_LABEL . ' – ' . M3_TITULO;
$backUrl    = '../landing.php';
$assetBase  = '../';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="container pb-5">

    <?php if ($formError): ?>
        <div id="alerta-errores" class="alert alert-danger mb-4 shadow-sm fw-semibold" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($formError) ?>
        </div>
    <?php else: ?>
        <div id="alerta-errores" class="alert alert-danger d-none mb-4 shadow-sm fw-semibold" role="alert"></div>
    <?php endif; ?>

    <!-- Título + número de expediente -->
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3">
        <div>
            <h2 class="h4 fw-bold mb-1">Informe Técnico: Evaluación de Daños por Borrascas</h2>
            <p class="text-muted mb-0"><?= htmlspecialchars(M3_TITULO_CAMPANA) ?> &middot; <?= htmlspecialchars(M3_PROVINCIA) ?></p>
        </div>
        <div class="num-expediente-badge">
            <i class="bi bi-hash"></i>M2-<?= date('Y') ?>-XXXX
        </div>
    </div>

    <!-- ÍNDICE DE NAVEGACIÓN (Sin números duplicados) -->
    <div class="indice-informe mb-4">
        <h3><i class="bi bi-list-ol me-2"></i>Contenido del informe</h3>
        <ol>
            <li><a href="#sec-solicitante" class="text-decoration-none">Datos del solicitante</a></li>
            <li><a href="#sec-objeto" class="text-decoration-none">Objeto del informe</a></li>
            <li><a href="#sec-explotacion" class="text-decoration-none">Datos de la explotación</a></li>
            <li><a href="#sec-contexto" class="text-decoration-none">Introducción y contexto meteorológico <small class="text-muted">(Texto técnico e ilustraciones oficiales)</small></a></li>
            <li><a href="#sec-metodologia" class="text-decoration-none">Metodología y fuentes de información <small class="text-muted">(Análisis agronómico y fórmulas de cálculo)</small></a></li>
            <li><a href="#sec-descripcion" class="text-decoration-none">Descripción de daños</a></li>
            <li><a href="#sec-valoracion" class="text-decoration-none">Valoración de daños y pérdida de renta</a></li>
            <li><a href="#sec-conclusion" class="text-decoration-none">Conclusión</a></li>
            <li><a href="#sec-anexo" class="text-decoration-none">Anexo: fotografías del cultivo afectado</a></li>
        </ol>
    </div>

    <form id="form-m3" method="POST" action="procesar.php" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- 1. DATOS DEL SOLICITANTE ════════════════════════════ -->
        <div id="sec-solicitante" class="card shadow-sm mb-4">
            <div class="card-header section-header">
                <i class="bi bi-building me-2"></i>1. Datos del solicitante (Persona física o Empresa)
            </div>
            <div class="card-body p-4">

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold">Nombre / Razón Social <span class="text-danger">*</span></label>
                        <input type="text" name="razon_social" id="razon_social" class="form-control" required value="<?= old('razon_social', '', $oldData) ?>"
                               placeholder="Nombre completo del titular o de la empresa">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label fw-semibold">Tipo Doc. <span class="text-danger">*</span></label>
                        <select name="tipo_doc" id="tipo_doc" class="form-select" required>
                            <option value="DNI" <?= oldSelect('tipo_doc', 'DNI', $oldData, true) ?>>DNI / NIF</option>
                            <option value="CIF" <?= oldSelect('tipo_doc', 'CIF', $oldData) ?>>CIF (Empresas)</option>
                            <option value="NIE" <?= oldSelect('tipo_doc', 'NIE', $oldData) ?>>NIE (Extranjeros)</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Nº Documento <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="cif_nif" id="cif_nif" class="form-control text-uppercase" value="<?= old('cif_nif', '', $oldData) ?>"
                                   placeholder="12345678Z / A12345678" required maxlength="12">
                            <span class="input-group-text" id="cif-estado"><i class="bi bi-dash-circle text-secondary"></i></span>
                        </div>
                        <div id="cif-feedback" class="form-text"></div>
                    </div>
                </div>

                <div id="bloque-representante" class="row g-3 mb-4 border-top pt-3 d-none">
                    <div class="col-12">
                        <span class="fw-semibold text-muted small text-uppercase"><i class="bi bi-person-badge me-1"></i>Datos de Representación Legal (Empresas / CIF)</span>
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label fw-semibold">Nombre y Apellidos del Representante <span class="text-danger rep-req">*</span></label>
                        <input type="text" name="representante_nombre" id="representante_nombre" class="form-control" value="<?= old('representante_nombre', '', $oldData) ?>"
                               placeholder="Nombre completo del representante legal">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">DNI/NIE Representante <span class="text-danger rep-req">*</span></label>
                        <input type="text" name="representante_dni" id="representante_dni" class="form-control text-uppercase" value="<?= old('representante_dni', '', $oldData) ?>"
                               maxlength="10" placeholder="12345678Z">
                    </div>
                </div>

                <h6 class="fw-bold text-muted text-uppercase small mb-3 border-top pt-3">Domicilio</h6>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold">Calle / Avenida <span class="text-danger">*</span></label>
                        <input type="text" name="calle" class="form-control" required value="<?= old('calle', '', $oldData) ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-semibold">Nº <span class="text-danger">*</span></label>
                        <input type="text" name="numero" class="form-control" required value="<?= old('numero', '', $oldData) ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-semibold">Bloque/Portal</label>
                        <input type="text" name="bloque" class="form-control" value="<?= old('bloque', '', $oldData) ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-semibold">Piso/Puerta</label>
                        <input type="text" name="piso" class="form-control" value="<?= old('piso', '', $oldData) ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-semibold">C.P. <span class="text-danger">*</span></label>
                        <input type="text" name="codigo_postal" class="form-control" value="<?= old('codigo_postal', '', $oldData) ?>"
                               maxlength="5" pattern="\d{5}" required placeholder="18000">
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold">Provincia <span class="text-danger">*</span></label>
                        <select name="provincia" id="m3-provincia" class="form-select" required data-saved="<?= old('provincia', '', $oldData) ?>">
                            <option value="">Cargando provincias…</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold">Municipio <span class="text-danger">*</span></label>
                        <select name="municipio" id="m3-municipio" class="form-select" required disabled data-saved="<?= old('municipio', '', $oldData) ?>">
                            <option value="">Selecciona primero una provincia…</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Teléfono móvil <span class="text-danger">*</span></label>
                        <input type="tel" name="telefono" class="form-control" required value="<?= old('telefono', '', $oldData) ?>">
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold">Correo electrónico <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required value="<?= old('email', '', $oldData) ?>">
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
                    El texto del objeto se genera automáticamente en el PDF amparado en el Real Decreto-ley 5/2026, de 17 de febrero. Indica la ubicación de la explotación.
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold">Localidad de la explotación <span class="text-danger">*</span></label>
                        <input type="text" name="localidad_exp" class="form-control" required value="<?= old('localidad_exp', '', $oldData) ?>"
                               placeholder="Localidad donde se ubica">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Comarca <span class="text-danger">*</span></label>
                        <input type="text" name="comarca" class="form-control" required value="<?= old('comarca', '', $oldData) ?>"
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
                        <input type="text" name="reafa" class="form-control" required value="<?= old('reafa', '', $oldData) ?>"
                               placeholder="Código de explotación REAFA">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Provincia <span class="text-danger">*</span></label>
                        <select name="exp_provincia" id="m3-exp-provincia" class="form-select" required data-saved="<?= old('exp_provincia', '', $oldData) ?>">
                            <option value="">Cargando…</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Municipio <span class="text-danger">*</span></label>
                        <select name="exp_municipio" id="m3-exp-municipio" class="form-select" required disabled data-saved="<?= old('exp_municipio', '', $oldData) ?>">
                            <option value="">Primero elige provincia…</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Localidad <span class="text-danger">*</span></label>
                        <input type="text" name="exp_localidad" class="form-control" required value="<?= old('exp_localidad', '', $oldData) ?>">
                    </div>
                </div>

                <h6 class="fw-bold text-muted text-uppercase small mb-3">Cultivo</h6>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-semibold">Cultivo</label>
                        <input type="text" class="form-control bg-light" value="Espárrago Verde" readonly>
                        <input type="hidden" name="cultivo" value="Espárrago Verde">
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold">Variedad <span class="text-danger">*</span></label>
                        <input type="text" name="variedad" class="form-control" required value="<?= old('variedad', '', $oldData) ?>"
                               placeholder="">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Edad del cultivo (años)</label>
                        <input type="number" name="edad_cultivo" class="form-control" value="<?= old('edad_cultivo', '', $oldData) ?>"
                               min="1" max="999" placeholder="Ej: 40">
                    </div>
                </div>

                <h6 class="fw-bold text-muted text-uppercase small mb-3">Superficie y sistema</h6>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <label class="form-label fw-semibold">Secano (ha) <span class="text-danger">*</span></label>
                        <input type="number" name="sup_secano" id="sup_secano"
                               class="form-control" min="0" step="0.01" value="<?= old('sup_secano', '0', $oldData) ?>" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Sistema secano</label>
                        <select name="sup_secano_tipo" class="form-select">
                            <option value="">Seleccionar…</option>
                            <?php foreach (M3_SISTEMAS_CULTIVO as $k => $v): ?>
                                <option value="<?= $k ?>" <?= oldSelect('sup_secano_tipo', $k, $oldData) ?>><?= htmlspecialchars($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fw-semibold">Regadío (ha) <span class="text-danger">*</span></label>
                        <input type="number" name="sup_regadio" id="sup_regadio"
                               class="form-control" min="0" step="0.01" value="<?= old('sup_regadio', '0', $oldData) ?>" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Sistema regadío</label>
                        <select name="sup_regadio_tipo" class="form-select">
                            <option value="">Seleccionar…</option>
                            <?php foreach (M3_SISTEMAS_CULTIVO as $k => $v): ?>
                                <option value="<?= $k ?>" <?= oldSelect('sup_regadio_tipo', $k, $oldData) ?>><?= htmlspecialchars($v) ?></option>
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
                            <?php foreach (M3_SISTEMAS_CULTIVO as $k => $v): ?>
                                <option value="<?= $k ?>" <?= oldSelect('sistema_cultivo', $k, $oldData) ?>><?= htmlspecialchars($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- RESUMEN TÉCNICO EN FORMULARIO (Secciones 4 y 5 fijas) -->
        <div id="sec-contexto" class="card shadow-sm mb-4 bg-light border">
            <div class="card-body p-3 small text-muted">
                <i class="bi bi-info-circle-fill me-2 text-success"></i>
                <strong>Análisis Meteorológico y Metodología:</strong> El informe PDF incluirá automáticamente la redacción técnica del temporal de borrascas de enero/febrero de 2026, datos climatológicos AEMET/RAIF, hidrogramas, la estructura de 5 componentes de daño agronómico para olivar, fórmulas de cálculo y referencias bibliográficas.
            </div>
        </div>

        <!-- 6. DESCRIPCIÓN DE DAÑOS ═════════════════════════════ -->
        <div id="sec-descripcion" class="card shadow-sm mb-4">
            <div class="card-header section-header">
                <i class="bi bi-card-checklist me-2"></i>6. Descripción de daños observados en la explotación
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">Marca los daños sufridos en tu parcela o explotación:</p>

                <h6 class="fw-bold text-success small text-uppercase mb-2">Daños Agronómicos y en Cultivo</h6>
                <div class="row g-2 mb-4">
                    <div class="col-12 col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="danos_agronimicos[]" value="Inundación" id="d1" <?= oldCheck('danos_agronimicos', 'Inundación', $oldData, true) ?>>
                            <label class="form-check-label" for="d1">Inundación de parcelas</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="danos_agronimicos[]" value="Saturación hídrica" id="d2" <?= oldCheck('danos_agronimicos', 'Saturación hídrica', $oldData, true) ?>>
                            <label class="form-check-label" for="d2">Saturación hídrica del suelo (exceso de humedad)</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="danos_agronimicos[]" value="Caída de fruto" id="d3" <?= oldCheck('danos_agronimicos', 'Caída de fruto', $oldData, true) ?>>
                            <label class="form-check-label" for="d3">Brotación prematura con turiones deformes</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="danos_agronimicos[]" value="Necrosis" id="d4" <?= oldCheck('danos_agronimicos', 'Necrosis', $oldData) ?>>
                            <label class="form-check-label" for="d4">Pérdida de garra por asfixia y podredumbre</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="danos_agronimicos[]" value="Plagas y enfermedades" id="d5" <?= oldCheck('danos_agronimicos', 'Plagas y enfermedades', $oldData, true) ?>>
                            <label class="form-check-label" for="d5">Aparición de hongos (Fusarium spp., Phytophthora spp.)</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="danos_agronimicos[]" value="Daños en árboles" id="d6" <?= oldCheck('danos_agronimicos', 'Daños en árboles', $oldData) ?>>
                            <label class="form-check-label" for="d6">Retraso o pérdida del primer corte de primavera</label>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-success small text-uppercase mb-2">Daños Estructurales en la Explotación</h6>
                <div class="row g-2 mb-3">
                    <div class="col-12 col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="danos_estructurales[]" value="Accesos y cárcavas" id="e1" <?= oldCheck('danos_estructurales', 'Accesos y cárcavas', $oldData, true) ?>>
                            <label class="form-check-label" for="e1">Deterioro de accesos, erosión, cárcavas</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="danos_estructurales[]" value="Riego" id="e2" <?= oldCheck('danos_estructurales', 'Riego', $oldData) ?>>
                            <label class="form-check-label" for="e2">Daños en infraestructura de riego (cabezal, tuberías, acequias)</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="danos_estructurales[]" value="Naves" id="e3" <?= oldCheck('danos_estructurales', 'Naves', $oldData) ?>>
                            <label class="form-check-label" for="e3">Daños en naves de almacenamiento o instalaciones</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="danos_estructurales[]" value="Vallado" id="e4" <?= oldCheck('danos_estructurales', 'Vallado', $oldData) ?>>
                            <label class="form-check-label" for="e4">Daños en cerramientos o vallado perimetral</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

                <!-- 7. VALORACIÓN DE DAÑOS Y PÉRDIDA DE RENTA ════════════ -->
        <div id="sec-valoracion" class="card shadow-sm mb-4">
            <div class="card-header section-header">
                <i class="bi bi-calculator-fill me-2"></i>7. Valoración de daños y pérdida de renta
            </div>
            <div class="card-body p-4">

                <!-- Campaña 2025/2026 (Campaña Afectada Actual) -->
                <h6 class="fw-bold text-success text-uppercase small mb-3">
                    <i class="bi bi-calendar-event me-1"></i>Campaña 2025/2026 (Campaña Afectada Actual)
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Producción estimada (Kg espárrago)</label>
                        <div class="input-group">
                            <input type="number" name="prod_estimada_kg" class="form-control" placeholder="Ej: 10000" min="0" step="1" value="<?= old('prod_estimada_kg', '', $oldData) ?>">
                            <span class="input-group-text">Kg</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Producción real recolectada (Kg espárrago)</label>
                        <div class="input-group">
                            <input type="number" name="prod_real_m3_kg" class="form-control" placeholder="Ej: 5000" min="0" step="1" value="<?= old('prod_real_m3_kg', '', $oldData) ?>">
                            <span class="input-group-text">Kg</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Producción de menor calidad / destrío</label>
                        <div class="input-group">
                            <input type="number" name="menor_calidad_valor" id="menor_calidad_valor" class="form-control"
                                   placeholder="Ej: 2000" min="0" max="10000000" step="0.01" value="<?= old('menor_calidad_valor', '', $oldData) ?>">
                            <select name="menor_calidad_tipo" id="menor_calidad_tipo" class="form-select" style="max-width:110px;">
                                <option value="kg" <?= oldSelect('menor_calidad_tipo', 'kg', $oldData, true) ?>>Kilos (Kg)</option>
                                <option value="pct" <?= oldSelect('menor_calidad_tipo', 'pct', $oldData) ?>>% (Máx. 100%)</option>
                            </select>
                        </div>
                        <div class="form-text">Turiones deformes o manchados entregados.</div>
                    </div>
                </div>

                <!-- Campaña 2026/2027 (Próxima Cosecha - Daño Diferido) -->
                <h6 class="fw-bold text-success text-uppercase small mb-3 border-top pt-3">
                    <i class="bi bi-calendar-plus me-1"></i>Campaña 2026/2027 (Próxima Cosecha &ndash; Daño Diferido)
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Producción prevista próxima campaña (Kg)</label>
                        <div class="input-group">
                            <input type="number" name="prod_prevista_prox_kg" class="form-control" placeholder="Ej: 10000" min="0" step="1" value="<?= old('prod_prevista_prox_kg', '', $oldData) ?>">
                            <span class="input-group-text">Kg</span>
                        </div>
                        <div class="form-text">Previsión en caso de que NO hubiese habido borrascas.</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Nivel de afección por inundación / encharcamiento</label>
                        <select name="nivel_afeccion" class="form-select">
                            <option value="Baja" <?= oldSelect('nivel_afeccion', 'Baja', $oldData) ?>>Baja (encharcamiento &lt;10 días)</option>
                            <option value="Moderada" <?= oldSelect('nivel_afeccion', 'Moderada', $oldData) ?>>Moderada (encharcamientos repetidos)</option>
                            <option value="Alta" <?= oldSelect('nivel_afeccion', 'Alta', $oldData, true) ?>>Alta (encharcamiento &gt;20-30 días)</option>
                            <option value="Muy alta" <?= oldSelect('nivel_afeccion', 'Muy alta', $oldData) ?>>Muy alta (inundación prolongada)</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Drenaje de parcelas inundadas</label>
                        <select name="drenaje_parcelas" class="form-select">
                            <option value="Bueno" <?= oldSelect('drenaje_parcelas', 'Bueno', $oldData) ?>>Bueno (arenosos/labrados)</option>
                            <option value="Malo" <?= oldSelect('drenaje_parcelas', 'Malo', $oldData, true) ?>>Malo (arcillosos/compactados)</option>
                        </select>
                    </div>
                </div>

                <!-- Sobrecostes Extraordinarios Justificados -->
                <h6 class="fw-bold text-success text-uppercase small mb-3 border-top pt-3">
                    <i class="bi bi-receipt me-1"></i>Sobrecostes Extraordinarios Justificados
                </h6>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold">Sobrecostes extraordinarios de la explotación (€)</label>
                        <div class="input-group">
                            <input type="number" name="sobrecostes_extra_eur" class="form-control" placeholder="Ej: 2500.00" min="0" step="0.01" value="<?= old('sobrecostes_extra_eur', '', $oldData) ?>">
                            <span class="input-group-text">€</span>
                        </div>
                        <div class="form-text">Indica el importe en euros correspondiente a sobrecostes extraordinarios (reposición de marras, fungicidas extras, arreglo de caminos). Deberán estar respaldados por facturas a adjuntar en el siguiente apartado.</div>
                    </div>
                </div>

            </div>
        </div>
        <!-- DOCUMENTOS ADJUNTOS / FACTURAS ════════════════════════════ -->
        <div class="card shadow-sm mb-4" id="seccion-adjuntos">
            <div class="card-header section-header">
                <i class="bi bi-paperclip me-2"></i>Documentos adjuntos y facturas
                <span class="badge bg-secondary ms-2">Opcional · máx. <?= MAX_ADJUNTOS ?> archivos · 8&nbsp;MB c/u</span>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-2">
                    Adjunta justificantes de gasto, facturas de tratamientos o reparación de caminos, certificaciones o documentos de apoyo al informe.<br>
                    Se aceptan <strong>PDF e imágenes</strong> (JPG, PNG, WebP).
                </p>
                <div class="mb-3">
                    <label for="adjuntos" class="form-label fw-semibold">Selecciona archivos o facturas</label>
                    <input type="file" id="adjuntos" name="adjuntos[]"
                           class="form-control"
                           accept="image/jpeg,image/png,image/webp,application/pdf"
                           multiple>
                    <div id="adjuntos-lista" class="mt-2 small"></div>
                </div>
            </div>
        </div>

        <div id="sec-anexo" class="card shadow-sm mb-4">
            <div class="card-header section-header">
                <i class="bi bi-images me-2"></i>9. Anexo: fotografías del cultivo afectado
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-3">Adjunta fotografías que acrediten los daños visibles en la explotación (opcional).</p>
                <input type="file" name="imagenes[]" id="imagenes-m3"
                       class="form-control mb-3"
                       accept="image/jpeg,image/png,image/webp" multiple>
                <div id="preview-imagenes-m3" class="d-flex flex-wrap gap-2"></div>
                <div class="form-text mt-2">
                    Máximo <?= MAX_IMAGENES ?> imágenes · hasta <?= number_format(MAX_TAMANO_IMG / 1048576, 0) ?> MB cada una.
                </div>
            </div>
        </div>

        <!-- FIRMA ════════════════════════════════════════════════ -->
        <div class="card shadow-sm mb-4">
            <div class="card-header section-header bg-white fw-semibold text-dark">
                <i class="bi bi-pen me-2 text-success"></i>Firma del solicitante <span class="badge bg-secondary ms-2">Opcional</span>
            </div>
            <div class="card-body p-4 text-center">
                <p class="text-muted small mb-3">Si el solicitante firma ahora, el documento se guardará firmado. Si no, se podrá firmar después desde el panel de administración.</p>
                <canvas id="firma-canvas-m3"
                        style="width:100%;max-width:700px;height:180px;border:2px solid #c3dac8;border-radius:.5rem;background:#fff;cursor:crosshair;touch-action:none;display:block;margin:0 auto;"></canvas>
                <input type="hidden" name="firma_data" id="firma_data_m3">
                <button type="button" class="btn btn-outline-secondary btn-sm mt-3" id="limpiar-firma-m3">
                    <i class="bi bi-eraser me-1"></i>Limpiar firma
                </button>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
            <div class="text-muted small">Los campos con <span class="text-danger">*</span> son obligatorios.</div>
            <button type="submit" class="btn btn-primary btn-lg px-5" id="btn-enviar-m3">
                <i class="bi bi-send me-2"></i>Enviar informe
            </button>
        </div>
    </form>
</main>

<!-- MODAL ÉXITO MODELO 2 -->
<div class="modal fade" id="modalExitoM3" tabindex="-1" aria-labelledby="modalExitoM3Label" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5 px-4">
                <div class="mb-3" style="font-size:3.5rem;color:#2d6a4f;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h4 class="fw-bold mb-2">Informe tramitado correctamente</h4>
                <p class="text-muted mb-1">
                    El informe de <strong id="exito-nombre"><?= $informeOk ? htmlspecialchars($informeOk['nombre'] ?? '') : '' ?></strong>
                    ha sido registrado con éxito.
                </p>
                <p class="text-muted small mb-4">Expediente: <code id="exito-exp"><?= $informeOk ? htmlspecialchars($informeOk['expediente'] ?? '') : '' ?></code></p>
                <button type="button" class="btn btn-success px-5" data-bs-dismiss="modal">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo informe
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Carga de Provincias y Municipios unificada y robusta con restauración de valor guardado
function vincularGeo(provSel, munSel) {
    const savedProv = provSel.dataset.saved || '';
    const savedMun  = munSel.dataset.saved || '';

    fetch('geo.php?tipo=provincias')
        .then(r => r.json())
        .then(data => {
            provSel.innerHTML = '<option value="">— Selecciona provincia —</option>';
            if (Array.isArray(data)) {
                data.forEach(p => {
                    const o = document.createElement('option');
                    o.value = p.nombre;
                    o.textContent = p.nombre;
                    o.dataset.cpro = p.cpro;
                    if (savedProv && (savedProv === p.nombre || savedProv === p.cpro)) {
                        o.selected = true;
                    }
                    provSel.appendChild(o);
                });
            }
            if (provSel.value) {
                cargarMunicipios(provSel, munSel, savedMun);
            }
        }).catch(() => { provSel.innerHTML = '<option value="">Error al cargar provincias</option>'; });

    function cargarMunicipios(pSel, mSel, targetMun) {
        mSel.disabled = true;
        mSel.innerHTML = '<option value="">Cargando municipios…</option>';
        const selected = pSel.selectedOptions[0];
        const cpro = selected?.dataset?.cpro || '';
        const val  = pSel.value;

        if (!cpro && !val) {
            mSel.innerHTML = '<option value="">— Elige primero provincia —</option>';
            return;
        }

        const url = 'geo.php?tipo=municipios&cpro=' + encodeURIComponent(cpro) + '&provincia=' + encodeURIComponent(val);
        fetch(url)
            .then(r => r.json())
            .then(data => {
                mSel.innerHTML = '<option value="">— Selecciona municipio —</option>';
                const items = Array.isArray(data) ? data : [];
                items.forEach(m => {
                    const nom = (typeof m === 'string') ? m : (m.nombre || m.cmun || m);
                    const opt = new Option(nom, nom);
                    if (targetMun && targetMun === nom) {
                        opt.selected = true;
                    }
                    mSel.appendChild(opt);
                });
                mSel.disabled = false;
            }).catch(() => {
                mSel.innerHTML = '<option value="">Error al cargar municipios</option>';
                mSel.disabled = false;
            });
    }

    provSel.addEventListener('change', function () {
        cargarMunicipios(this, munSel, '');
    });
}

vincularGeo(document.getElementById('m3-provincia'),     document.getElementById('m3-municipio'));
vincularGeo(document.getElementById('m3-exp-provincia'), document.getElementById('m3-exp-municipio'));

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
calcSup();

// Canvas firma (opcional)
(function () {
    const canvas = document.getElementById('firma-canvas-m3');
    const hidden = document.getElementById('firma_data_m3');
    const ctx = canvas.getContext('2d');
    let drawing = false, hasInk = false;

    function pos(e) {
        const r = canvas.getBoundingClientRect();
        const pt = e.touches ? e.touches[0] : e;
        return {
            x: (pt.clientX - r.left) * (canvas.width / r.width),
            y: (pt.clientY - r.top)  * (canvas.height / r.height)
        };
    }

    function start(e) {
        drawing = true;
        const p = pos(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }
    function move(e) {
        if (!drawing) return;
        e.preventDefault();
        const p = pos(e);
        ctx.lineTo(p.x, p.y);
        ctx.strokeStyle = '#1b4332';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.stroke();
        hasInk = true;
    }
    function stop() { drawing = false; }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', stop);
    canvas.addEventListener('mouseleave', stop);
    canvas.addEventListener('touchstart', start, { passive: true });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', stop);

    document.getElementById('limpiar-firma-m3').addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hidden.value = '';
        hasInk = false;
    });

    document.getElementById('form-m3').addEventListener('submit', function (e) {
        if (hasInk) {
            hidden.value = canvas.toDataURL('image/png');
        } else {
            hidden.value = '';
        }
        const btn = document.getElementById('btn-enviar-m3');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando informe…';
    });
})();

// Preview imágenes
document.getElementById('imagenes-m3').addEventListener('change', function () {
    const wrap = document.getElementById('preview-imagenes-m3');
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
});

// Validación dinámica del formato según Tipo Doc (DNI / CIF / NIE)
(function () {
    const selectTipo = document.getElementById('tipo_doc');
    const inputCif   = document.getElementById('cif_nif');
    const inputRazon = document.getElementById('razon_social');
    const estadoCif  = document.getElementById('cif-estado');
    const feedbackCif = document.getElementById('cif-feedback');

    if (!inputCif) return;

    function validarFormato(val, tipo) {
        val = val.trim().toUpperCase();
        if (tipo === 'DNI') {
            return /^[0-9]{8}[A-Z]$/.test(val);
        } else if (tipo === 'CIF') {
            return /^[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J]$/.test(val);
        } else if (tipo === 'NIE') {
            return /^[XYZ][0-9]{7}[A-Z]$/.test(val);
        }
        return false;
    }

    function comprobar() {
        const val = inputCif.value.trim().toUpperCase();
        inputCif.value = val;
        const tipo = selectTipo ? selectTipo.value : 'DNI';

        if (val.length < 8) {
            if (estadoCif) estadoCif.innerHTML = '<i class="bi bi-dash-circle text-secondary"></i>';
            if (feedbackCif) { feedbackCif.textContent = ''; feedbackCif.className = 'form-text'; }
            inputCif.classList.remove('is-valid', 'is-invalid');
            return;
        }

        if (validarFormato(val, tipo)) {
            if (estadoCif) estadoCif.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
            if (feedbackCif) {
                feedbackCif.textContent = 'Formato de ' + tipo + ' correcto ✓';
                feedbackCif.className = 'form-text text-success fw-bold';
            }
            inputCif.classList.add('is-valid');
            inputCif.classList.remove('is-invalid');
        } else {
            if (estadoCif) estadoCif.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
            if (feedbackCif) {
                feedbackCif.textContent = 'Formato incorrecto para ' + tipo;
                feedbackCif.className = 'form-text text-danger fw-bold';
            }
            inputCif.classList.add('is-invalid');
            inputCif.classList.remove('is-valid');
        }
    }

    let timer = null;
    inputCif.addEventListener('input', function () {
        comprobar();
        clearTimeout(timer);
        const val = this.value.trim().toUpperCase();
        if (val.length >= 8) {
            timer = setTimeout(() => {
                const fd = new FormData();
                fd.append('dni', val);
                fetch('verificar_dni.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.exito && data.nombre && inputRazon && inputRazon.value.trim() === '') {
                            inputRazon.value = data.nombre;
                        }
                    })
                    .catch(() => {});
            }, 400);
        }
    });

    if (selectTipo) {
        selectTipo.addEventListener('change', comprobar);
    }
    comprobar();

    // Alternar visibilidad del bloque de representación según Tipo Doc
    function toggleRepresentante() {
        const selectTipo = document.getElementById('tipo_doc');
        const bloqueRep  = document.getElementById('bloque-representante');
        const repNomInput = document.getElementById('representante_nombre');
        const repDniInput = document.getElementById('representante_dni');

        if (selectTipo && bloqueRep) {
            if (selectTipo.value === 'CIF') {
                bloqueRep.classList.remove('d-none');
                if (repNomInput) repNomInput.required = true;
                if (repDniInput) repDniInput.required = true;
            } else {
                bloqueRep.classList.add('d-none');
                if (repNomInput) { repNomInput.required = false; repNomInput.classList.remove('is-invalid'); }
                if (repDniInput) { repDniInput.required = false; repDniInput.classList.remove('is-invalid'); }
            }
        }
    }
    if (selectTipo) {
        selectTipo.addEventListener('change', toggleRepresentante);
    }
    toggleRepresentante();

})();
</script>

<?php if ($informeOk): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = new bootstrap.Modal(document.getElementById('modalExitoM3'));
        modal.show();
        document.getElementById('modalExitoM3').addEventListener('hidden.bs.modal', () => {
            document.getElementById('form-m3').reset();
            window.location.href = 'index.php';
        });
    });
</script>
<?php endif; ?>

<?php if ($formError): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const errEl = document.getElementById('alerta-errores');
        if (errEl) {
            errEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

// Documentos Adjuntos (Preview / Lista)
(function() {
    const inputAdjuntos = document.getElementById('adjuntos');
    const listaAdjuntos = document.getElementById('adjuntos-lista');
    if (!inputAdjuntos || !listaAdjuntos) return;

    inputAdjuntos.addEventListener('change', () => {
        listaAdjuntos.innerHTML = '';
        const archivos = Array.from(inputAdjuntos.files);
        const validos = archivos.filter(f => {
            if (f.size > 8388608) {
                alert(`"${f.name}" supera el tamaño máximo de 8 MB y no se adjuntará.`);
                return false;
            }
            return true;
        });

        if (validos.length > 5) {
            alert(`Solo se permiten 5 archivos adjuntos. Se usarán los primeros 5.`);
            validos.splice(5);
        }

        validos.forEach(f => {
            const item = document.createElement('div');
            item.className = 'py-1 border-bottom d-flex align-items-center gap-2';
            const icon = f.type === 'application/pdf' ? 'bi-file-earmark-pdf text-danger' : 'bi-image text-success';
            item.innerHTML = `<i class="bi ${icon}"></i><span>${f.name}</span><span class="text-muted">(${(f.size / 1048576).toFixed(2)} MB)</span>`;
            listaAdjuntos.appendChild(item);
        });
    });
})();

</script>
<?php endif; ?>

</body>
</html>
