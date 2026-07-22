/**
 * formulario.js
 * Lógica cliente: verificación AJAX del DNI, cálculos en tiempo real,
 * previsualización de imágenes y control de visibilidad de secciones.
 */
'use strict';

// ────────────────────────────────────────────────────────────
// UTILIDADES
// ────────────────────────────────────────────────────────────
function fmt(num, decimales = 2) {
    return new Intl.NumberFormat('es-ES', {
        minimumFractionDigits: decimales,
        maximumFractionDigits: decimales
    }).format(num);
}
function fmtEur(num) { return fmt(num) + ' €'; }
function fmtKg(num)  { return fmt(num) + ' Kg'; }
function dash() { return '<span class="text-muted">—</span>'; }

// ────────────────────────────────────────────────────────────
// VERIFICACIÓN DNI (AJAX)
// ────────────────────────────────────────────────────────────
const inputDNI     = document.getElementById('dni');
const estadoIcono  = document.getElementById('dni-estado');
const feedbackDNI  = document.getElementById('dni-feedback');
const inputNombre  = document.getElementById('nombre');

let timerDNI = null;
let dniValidado = false;

function resetDniValidado() { dniValidado = false; }

function setDniEstado(estado, mensaje = '') {
    // estado: 'checking' | 'ok' | 'error' | 'idle'
    feedbackDNI.textContent = mensaje;
    switch (estado) {
        case 'checking':
            estadoIcono.innerHTML = '<span class="spinner-border spinner-border-sm text-secondary"></span>';
            feedbackDNI.className = 'form-text text-muted';
            break;
        case 'ok':
            estadoIcono.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
            feedbackDNI.className = 'form-text text-success';
            dniValidado = true;
            break;
        case 'error':
            estadoIcono.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
            feedbackDNI.className = 'form-text text-danger';
            dniValidado = false;
            break;
        default:
            estadoIcono.innerHTML = '<i class="bi bi-dash-circle text-secondary"></i>';
            feedbackDNI.className = 'form-text text-muted';
            dniValidado = false;
    }
}

function verificarDNI(dni) {
    if (!/^[0-9]{8}[A-Za-z]$/.test(dni)) {
        setDniEstado('idle', 'Introduce 8 dígitos y 1 letra (ej: 12345678A)');
        return;
    }
    setDniEstado('checking', 'Verificando…');

    const formData = new FormData();
    formData.append('dni', dni.toUpperCase());

    fetch('verificar_dni.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.valido) {
                setDniEstado('ok', 'DNI autorizado ✓');
                // Autorellenar el nombre si el Excel lo devuelve
                if (data.nombre && inputNombre.value.trim() === '') {
                    inputNombre.value = data.nombre;
                }
            } else {
                setDniEstado('error', data.mensaje || 'DNI no autorizado');
            }
        })
        .catch(() => setDniEstado('error', 'Error de conexión al verificar el DNI'));
}

inputDNI.addEventListener('input', () => {
    dniValidado = false;
    clearTimeout(timerDNI);
    setDniEstado('idle');
    const val = inputDNI.value.trim().toUpperCase();
    inputDNI.value = val;
    if (val.length >= 9) {
        timerDNI = setTimeout(() => verificarDNI(val), 500);
    }
});

// ────────────────────────────────────────────────────────────
// PROVINCIAS Y MUNICIPIOS (GeoAPI proxy)
// ────────────────────────────────────────────────────────────
(function () {
    const selProv = document.getElementById('provincia');
    const selMun  = document.getElementById('municipio');

    // Cargar provincias al inicio
    fetch('geo.php?tipo=provincias')
        .then(r => r.json())
        .then(data => {
            selProv.innerHTML = '<option value="">— Selecciona provincia —</option>';
            data.forEach(p => {
                const opt = new Option(p.nombre, p.nombre);
                opt.dataset.cpro = p.cpro;
                selProv.appendChild(opt);
            });
        })
        .catch(() => {
            selProv.innerHTML = '<option value="">Error al cargar provincias</option>';
        });

    // Al cambiar provincia → cargar municipios
    selProv.addEventListener('change', function () {
        const selected = this.selectedOptions[0];
        const cpro     = selected ? selected.dataset.cpro : '';

        selMun.innerHTML  = '<option value="">— Selecciona municipio —</option>';
        selMun.disabled   = true;
        selMun.classList.remove('is-invalid');

        if (!cpro) return;

        selMun.innerHTML = '<option value="">Cargando municipios…</option>';

        fetch('geo.php?tipo=municipios&cpro=' + encodeURIComponent(cpro))
            .then(r => r.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                selMun.innerHTML = '<option value="">— Selecciona municipio —</option>';
                data.forEach(m => selMun.appendChild(new Option(m.nombre, m.nombre)));
                selMun.disabled = false;
            })
            .catch(() => {
                selMun.innerHTML = '<option value="">Error al cargar municipios</option>';
                selMun.disabled  = false;
            });
    });
})();

// ────────────────────────────────────────────────────────────
// MOSTRAR / OCULTAR SECCIONES SEGÚN TIPO DE INFORME
// ────────────────────────────────────────────────────────────
const selectTipo       = document.getElementById('tipo_informe');
const seccionModelo1   = document.getElementById('modelo1');
const seccionImagenes  = document.getElementById('seccion-imagenes');
const seccionFirma     = document.getElementById('seccion-firma');
const seccionAdjuntos  = document.getElementById('seccion-adjuntos');
const seccionEnviar    = document.getElementById('seccion-enviar');
const resumenCalcBlock = document.getElementById('resumen-calculos');

selectTipo.addEventListener('change', () => {
    const val = selectTipo.value;
    if (val === '1') {
        seccionModelo1.classList.remove('d-none');
        seccionImagenes.style.display  = '';
        seccionAdjuntos.style.display  = '';
        seccionFirma.style.display     = '';
        seccionEnviar.style.display    = '';
        calcular(); // actualizar cálculos al mostrar
    } else {
        seccionModelo1.classList.add('d-none');
        seccionImagenes.style.display  = 'none';
        seccionAdjuntos.style.display  = 'none';
        seccionFirma.style.display     = 'none';
        seccionEnviar.style.display    = 'none';
    }
});

// ────────────────────────────────────────────────────────────
// FIRMA DIGITAL (CANVAS)
// ────────────────────────────────────────────────────────────
(function () {
    const canvas  = document.getElementById('firma-canvas');
    const ctx     = canvas.getContext('2d');
    const btnBorrar = document.getElementById('btn-borrar-firma');
    let dibujando = false;
    let ultimoX = 0, ultimoY = 0;

    function posCanvas(e) {
        const r = canvas.getBoundingClientRect();
        const scaleX = canvas.width  / r.width;
        const scaleY = canvas.height / r.height;
        if (e.touches) {
            return {
                x: (e.touches[0].clientX - r.left) * scaleX,
                y: (e.touches[0].clientY - r.top)  * scaleY
            };
        }
        return {
            x: (e.clientX - r.left) * scaleX,
            y: (e.clientY - r.top)  * scaleY
        };
    }

    function iniciar(e) {
        dibujando = true;
        const p = posCanvas(e);
        ultimoX = p.x; ultimoY = p.y;
        ctx.beginPath();
        ctx.arc(p.x, p.y, 1, 0, Math.PI * 2);
        ctx.fillStyle = '#1a1a1a';
        ctx.fill();
        document.getElementById('firma-error').style.display = 'none';
    }

    function dibujar(e) {
        if (!dibujando) return;
        e.preventDefault();
        const p = posCanvas(e);
        ctx.beginPath();
        ctx.moveTo(ultimoX, ultimoY);
        ctx.lineTo(p.x, p.y);
        ctx.strokeStyle = '#1a1a1a';
        ctx.lineWidth   = 2;
        ctx.lineCap     = 'round';
        ctx.lineJoin    = 'round';
        ctx.stroke();
        ultimoX = p.x; ultimoY = p.y;
    }

    function terminar() { dibujando = false; }

    canvas.addEventListener('mousedown',  iniciar);
    canvas.addEventListener('mousemove',  dibujar);
    canvas.addEventListener('mouseup',    terminar);
    canvas.addEventListener('mouseleave', terminar);
    canvas.addEventListener('touchstart', iniciar,  { passive: true });
    canvas.addEventListener('touchmove',  dibujar,  { passive: false });
    canvas.addEventListener('touchend',   terminar);

    btnBorrar.addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        document.getElementById('firma_data').value = '';
        document.getElementById('firma-error').style.display = 'none';
    });

    // Exponer función para limpiar desde fuera (reset form)
    window.limpiarFirma = () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        document.getElementById('firma_data').value = '';
        document.getElementById('firma-error').style.display = 'none';
    };
})();

function firmaVacia() {
    const canvas = document.getElementById('firma-canvas');
    const blank  = document.createElement('canvas');
    blank.width  = canvas.width;
    blank.height = canvas.height;
    return canvas.toDataURL() === blank.toDataURL();
}

// ────────────────────────────────────────────────────────────
// CÁLCULOS EN TIEMPO REAL (MODELO 1)
// ────────────────────────────────────────────────────────────
const tablaBody       = document.getElementById('tabla-calculos');
const totalEurEl      = document.getElementById('total-eur');
const prevResultEl    = document.getElementById('prev_inicial_result');
const prodResultEl    = document.getElementById('prod_real_result');
const recResultEl     = document.getElementById('recoleccion_result');

const R   = CONFIG.rendimiento;
const PA  = CONFIG.precioAOVE;
const PC  = CONFIG.precioCalidad;
const SR  = CONFIG.sobrecosteRec;
const SP  = CONFIG.sobrecosteProd;

function valorInput(id) {
    const el = document.getElementById(id);
    const v  = parseFloat(el ? el.value : '') || 0;
    return v < 0 ? 0 : v;
}

function crearFila(concepto, kgAceituna, kgAceite, precioKg, totalEur, destacada = false) {
    const tr = document.createElement('tr');
    if (destacada) tr.classList.add('table-warning');
    const celdas = [
        concepto,
        kgAceituna !== null ? fmtKg(kgAceituna) : dash(),
        kgAceite   !== null ? fmtKg(kgAceite)   : dash(),
        precioKg   !== null ? fmtEur(precioKg)  : dash(),
        totalEur   !== null ? fmtEur(totalEur)  : dash(),
    ];
    celdas.forEach((c, i) => {
        const td = document.createElement('td');
        td.innerHTML = c;
        if (i > 0) td.classList.add('text-end');
        tr.appendChild(td);
    });
    return tr;
}

function calcular() {
    const prevInicialKg = valorInput('prev_inicial_kg');
    const hasPrevInicial = document.getElementById('prev_inicial_kg').value.trim() !== '';

    const prodRealKg    = valorInput('prod_real_kg');
    const recoleccionKg = valorInput('recoleccion_kg');
    const variosEur     = valorInput('varios_eur');

    // Previsión inicial — si vacía = 0, igual que Excel con D14 vacío
    const prevInicialKgCalc  = hasPrevInicial ? prevInicialKg : 0;
    const prevInicialAceite  = prevInicialKgCalc * R;   // G14
    const prevInicialEur     = prevInicialAceite  * PA; // I14

    // Producción real
    const prodRealAceite = prodRealKg   * R;   // G15
    const prodRealEur    = prodRealAceite * PA; // I15

    // Pérdidas — Excel: G16=G14-G15, I16=I14-I15 — SIEMPRE se calcula
    const perdidasAceiteKg = prevInicialAceite - prodRealAceite;  // G16
    const perdidasEur      = prevInicialEur    - prodRealEur;     // I16

    // Recolección
    const recoleccionAceite = recoleccionKg * R;

    // Calidad
    const calidadEur = recoleccionAceite * PC;

    // Sobrecoste recolección
    const sobrecosteRecEur  = recoleccionKg     * SR;

    // Sobrecoste producción
    const sobrecosteProdEur = recoleccionAceite * SP;

    // Total
    const totalEur = (perdidasEur ?? 0)
                   + calidadEur
                   + sobrecosteRecEur
                   + sobrecosteProdEur
                   + variosEur;

    // ── Actualizar resultados inline bajo los inputs ──────────
    if (hasPrevInicial && prevInicialKg > 0) {
        prevResultEl.textContent =
            `Aceite: ${fmtKg(prevInicialAceite)} → ${fmtEur(prevInicialEur)}`;
    } else {
        prevResultEl.textContent = '';
    }

    if (prodRealKg > 0) {
        prodResultEl.textContent =
            `Aceite: ${fmtKg(prodRealAceite)} → ${fmtEur(prodRealEur)}`;
    } else {
        prodResultEl.textContent = '';
    }

    if (recoleccionKg > 0) {
        recResultEl.textContent = `Aceite: ${fmtKg(recoleccionAceite)}`;
    } else {
        recResultEl.textContent = '';
    }

    // ── Rellenar tabla de resumen ─────────────────────────────
    tablaBody.innerHTML = '';
    const algoCalculado = prodRealKg > 0 || recoleccionKg > 0;

    if (!algoCalculado) {
        resumenCalcBlock.style.setProperty('display', 'none', 'important');
        return;
    }
    resumenCalcBlock.style.removeProperty('display');

    // Previsión inicial — siempre aparece (0 si no se rellenó)
    tablaBody.appendChild(crearFila(
        'Previsión inicial de producción',
        prevInicialKgCalc, prevInicialAceite, PA, prevInicialEur
    ));

    tablaBody.appendChild(crearFila(
        'Producción real',
        prodRealKg, prodRealAceite, PA, prodRealEur
    ));

    // Pérdidas — siempre calculada (I16 = I14 - I15)
    tablaBody.appendChild(crearFila(
        'Pérdidas en producción',
        null, perdidasAceiteKg, null, perdidasEur, true
    ));

    tablaBody.appendChild(crearFila(
        'Recolección aceituna',
        recoleccionKg, recoleccionAceite, null, null
    ));

    tablaBody.appendChild(crearFila(
        'Calidad de aceite (Virgen Extra / Lampante)',
        null, recoleccionAceite, PC, calidadEur
    ));

    tablaBody.appendChild(crearFila(
        'Sobrecoste de recolección',
        recoleccionKg, null, SR, sobrecosteRecEur
    ));

    tablaBody.appendChild(crearFila(
        'Sobrecoste de producción',
        null, recoleccionAceite, SP, sobrecosteProdEur
    ));

    if (variosEur > 0) {
        tablaBody.appendChild(crearFila(
            'Varios', null, null, null, variosEur
        ));
    }

    totalEurEl.textContent = fmtEur(totalEur);
}

// Escuchar cambios en todos los inputs de cálculo
document.querySelectorAll('.calc-input').forEach(el => {
    el.addEventListener('input', calcular);
});

// ────────────────────────────────────────────────────────────
// PREVISUALIZACIÓN DE IMÁGENES (con botón eliminar)
// ────────────────────────────────────────────────────────────
const inputImagenes   = document.getElementById('imagenes');
const previsualizador = document.getElementById('imagenes-preview');

// Array propio de archivos seleccionados (FileList es inmutable)
let archivosSeleccionados = [];

function renderPreviews() {
    previsualizador.innerHTML = '';
    archivosSeleccionados.forEach((archivo, idx) => {
        const reader = new FileReader();
        reader.onload = e => {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-3 img-thumb';
            col.dataset.idx = idx;
            col.innerHTML = `
                <img src="${e.target.result}" alt="Imagen ${idx + 1}">
                <span class="badge-num">${idx + 1}</span>
                <button type="button" class="btn-eliminar-img" title="Eliminar imagen" aria-label="Eliminar imagen ${idx + 1}">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            `;
            col.querySelector('.btn-eliminar-img').addEventListener('click', () => {
                archivosSeleccionados.splice(idx, 1);
                sincronizarInput();
                renderPreviews();
            });
            previsualizador.appendChild(col);
        };
        reader.readAsDataURL(archivo);
    });
}

function sincronizarInput() {
    // Reconstruye el FileList del input usando DataTransfer
    const dt = new DataTransfer();
    archivosSeleccionados.forEach(f => dt.items.add(f));
    inputImagenes.files = dt.files;
}

inputImagenes.addEventListener('change', () => {
    const nuevos = Array.from(inputImagenes.files);

    // Filtrar por tamaño antes de añadir
    const validos = [];
    for (const f of nuevos) {
        if (f.size > CONFIG.maxTamanoImg) {
            alert(`"${f.name}" supera el tamaño máximo de 8 MB y no se ha añadido.`);
        } else {
            validos.push(f);
        }
    }

    archivosSeleccionados = archivosSeleccionados.concat(validos);

    if (archivosSeleccionados.length > CONFIG.maxImagenes) {
        archivosSeleccionados = archivosSeleccionados.slice(0, CONFIG.maxImagenes);
        alert(`Solo se permiten hasta ${CONFIG.maxImagenes} imágenes. Se han conservado las primeras ${CONFIG.maxImagenes}.`);
    }

    sincronizarInput();
    renderPreviews();
});

// ────────────────────────────────────────────────────────────
// DOCUMENTOS ADJUNTOS
// ────────────────────────────────────────────────────────────
const inputAdjuntos = document.getElementById('adjuntos');
const listaAdjuntos = document.getElementById('adjuntos-lista');

inputAdjuntos.addEventListener('change', () => {
    listaAdjuntos.innerHTML = '';
    const archivos = Array.from(inputAdjuntos.files);

    const validos = archivos.filter(f => {
        if (f.size > CONFIG.maxTamanoAdjunto) {
            alert(`"${f.name}" supera el tamaño máximo de 8 MB y no se adjuntará.`);
            return false;
        }
        return true;
    });

    if (validos.length > CONFIG.maxAdjuntos) {
        alert(`Solo se permiten ${CONFIG.maxAdjuntos} archivos adjuntos. Se usarán los primeros ${CONFIG.maxAdjuntos}.`);
        validos.splice(CONFIG.maxAdjuntos);
    }

    validos.forEach(f => {
        const item = document.createElement('div');
        item.className = 'py-1 border-bottom d-flex align-items-center gap-2';
        const icon = f.type === 'application/pdf' ? 'bi-file-earmark-pdf text-danger' : 'bi-image text-success';
        item.innerHTML = `<i class="bi ${icon}"></i><span>${f.name}</span><span class="text-muted">(${(f.size / 1048576).toFixed(2)} MB)</span>`;
        listaAdjuntos.appendChild(item);
    });
});

// ────────────────────────────────────────────────────────────
// VALIDACIÓN ANTES DE ENVIAR
// ────────────────────────────────────────────────────────────
document.getElementById('form-borrascas').addEventListener('submit', function (e) {
    const errores = [];

    // DNI
    if (!dniValidado) {
        errores.push({ campo: 'dni', msg: 'El DNI no ha sido verificado o no está autorizado.' });
    }

    // Datos personales
    if (!document.getElementById('nombre').value.trim()) {
        errores.push({ campo: 'nombre', msg: 'El nombre y apellidos son obligatorios.' });
    }
    if (!document.getElementById('calle').value.trim()) {
        errores.push({ campo: 'calle', msg: 'La calle / vía es obligatoria.' });
    }
    if (!document.getElementById('numero').value.trim()) {
        errores.push({ campo: 'numero', msg: 'El número es obligatorio.' });
    }
    if (!document.getElementById('municipio').value.trim()) {
        errores.push({ campo: 'municipio', msg: 'El municipio es obligatorio.' });
    }
    if (!document.getElementById('provincia').value.trim()) {
        errores.push({ campo: 'provincia', msg: 'La provincia es obligatoria.' });
    }
    const cp = document.getElementById('codigo_postal').value.trim();
    if (!/^[0-9]{5}$/.test(cp)) {
        errores.push({ campo: 'codigo_postal', msg: 'El código postal debe tener 5 dígitos.' });
    }
    if (!document.getElementById('telefono').value.trim()) {
        errores.push({ campo: 'telefono', msg: 'El teléfono es obligatorio.' });
    }
    const emailVal = document.getElementById('email').value.trim();
    if (!emailVal || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
        errores.push({ campo: 'email', msg: 'El correo electrónico no es válido.' });
    }
    if (!document.getElementById('cooperativa').value) {
        errores.push({ campo: 'cooperativa', msg: 'Selecciona una cooperativa.' });
    }

    // Tipo de informe
    if (document.getElementById('tipo_informe').value !== '1') {
        errores.push({ campo: 'tipo_informe', msg: 'Selecciona un tipo de informe.' });
    }

    // Datos producción
    if (valorInput('prod_real_kg') <= 0) {
        errores.push({ campo: 'prod_real_kg', msg: 'La producción real es obligatoria y debe ser mayor que 0.' });
    }
    if (valorInput('recoleccion_kg') <= 0) {
        errores.push({ campo: 'recoleccion_kg', msg: 'La recolección de aceituna es obligatoria y debe ser mayor que 0.' });
    }

    if (errores.length > 0) {
        e.preventDefault();

        // Marcar campos inválidos
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        errores.forEach(({ campo }) => {
            const el = document.getElementById(campo);
            if (el) el.classList.add('is-invalid');
        });
        // Abrir modal con lista de errores
        const lista = document.getElementById('modal-errores-lista');
        lista.innerHTML = errores.map(({ msg }) => `<li class="mb-1">${msg}</li>`).join('');
        new bootstrap.Modal(document.getElementById('modalErrores')).show();

        // Al cerrar el modal, enfocar el primer campo inválido
        document.getElementById('modalErrores').addEventListener('hidden.bs.modal', () => {
            const primero = document.querySelector('.is-invalid');
            if (primero) {
                primero.scrollIntoView({ behavior: 'smooth', block: 'center' });
                primero.focus();
            }
        }, { once: true });

        return;
    }

    // Limpiar marcas de error si todo OK
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    document.getElementById('firma-canvas').style.borderColor = '';

    // Copiar firma al campo oculto solo si existe; si no, se deja el informe pendiente de firma
    document.getElementById('firma_data').value = firmaVacia()
        ? ''
        : document.getElementById('firma-canvas').toDataURL('image/png');

    // Deshabilitar el botón para evitar doble envío
    const btn = document.getElementById('btn-generar');
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando informe…';
});
