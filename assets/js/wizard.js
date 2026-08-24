/**
 * wizard.js — controlador genérico de formularios por pasos.
 * Reutilizable para cualquier modelo: agrupa los bloques directos
 * de un contenedor [data-wizard] marcados con la clase "wizard-step"
 * y los muestra de uno en uno, con cabecera de progreso y navegación
 * Atrás / Siguiente. El último paso conserva su propio botón de envío.
 *
 * Uso en el HTML:
 *   <div data-wizard>
 *     <div class="wizard-step" data-step-title="Datos" data-step-icon="bi-person">...</div>
 *     <div class="wizard-step" data-step-title="Firma" data-step-icon="bi-pen">...(botón submit real aquí)...</div>
 *   </div>
 */
(function () {
    'use strict';

    function isFieldVisible(field) {
        return field.offsetParent !== null && !field.disabled;
    }

    function validateStep(step) {
        const required = step.querySelectorAll('[required]');
        for (const field of required) {
            if (!isFieldVisible(field)) continue;
            if (!field.checkValidity()) {
                field.reportValidity();
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
        }
        return true;
    }

    function initWizard(root) {
        const steps = Array.from(root.querySelectorAll(':scope > .wizard-step'));
        if (steps.length === 0) return;

        let current = 0;
        let furthest = 0;

        const progressMount = document.createElement('div');
        progressMount.className = 'wizard-progress';
        root.parentNode.insertBefore(progressMount, root);

        function renderProgress() {
            progressMount.innerHTML = '';
            const track = document.createElement('div');
            track.className = 'wizard-progress-track';

            steps.forEach((step, i) => {
                const title = step.dataset.stepTitle || ('Paso ' + (i + 1));
                const icon = step.dataset.stepIcon || '';

                const pill = document.createElement('button');
                pill.type = 'button';
                pill.className = 'wizard-pill'
                    + (i === current ? ' active' : '')
                    + (i < current ? ' done' : '')
                    + (i > furthest ? ' locked' : '');
                pill.disabled = i > furthest;

                const num = document.createElement('span');
                num.className = 'wizard-pill-num';
                num.innerHTML = i < current ? '<i class="bi bi-check-lg"></i>' : String(i + 1);

                const label = document.createElement('span');
                label.className = 'wizard-pill-label';
                label.textContent = title;

                pill.appendChild(num);
                pill.appendChild(label);
                pill.addEventListener('click', () => goTo(i));
                track.appendChild(pill);

                if (i < steps.length - 1) {
                    const line = document.createElement('div');
                    line.className = 'wizard-line' + (i < current ? ' done' : '');
                    track.appendChild(line);
                }
            });

            progressMount.appendChild(track);

            const compact = document.createElement('div');
            compact.className = 'wizard-compact-label';
            const title = steps[current].dataset.stepTitle || '';
            compact.innerHTML = 'Paso <strong>' + (current + 1) + '</strong> de <strong>' + steps.length + '</strong>: ' + title;
            progressMount.appendChild(compact);
        }

        function show(index) {
            steps.forEach((s, i) => { s.style.display = i === index ? '' : 'none'; });
            current = index;
            if (index > furthest) furthest = index;
            renderProgress();
            progressMount.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function goTo(index) {
            if (index === current) return;
            if (index > current && !validateStep(steps[current])) return;
            show(index);
        }

        function next() {
            if (!validateStep(steps[current])) return;
            if (current < steps.length - 1) show(current + 1);
        }

        function prev() {
            if (current > 0) show(current - 1);
        }

        steps.forEach((step, i) => {
            const isLast = i === steps.length - 1;
            const nav = document.createElement('div');
            nav.className = 'wizard-nav';

            if (i > 0) {
                const back = document.createElement('button');
                back.type = 'button';
                back.className = 'btn btn-outline-secondary wizard-btn-prev';
                back.innerHTML = '<i class="bi bi-arrow-left me-1"></i>Atrás';
                back.addEventListener('click', prev);
                nav.appendChild(back);
            } else {
                nav.appendChild(document.createElement('span'));
            }

            if (!isLast) {
                const fwd = document.createElement('button');
                fwd.type = 'button';
                fwd.className = 'btn wizard-btn-next';
                fwd.style.background = '#2d6a4f';
                fwd.style.color = '#fff';
                fwd.innerHTML = 'Siguiente <i class="bi bi-arrow-right ms-1"></i>';
                fwd.addEventListener('click', next);
                nav.appendChild(fwd);
            }

            step.appendChild(nav);
        });

        show(0);
    }

    function boot() {
        document.querySelectorAll('[data-wizard]').forEach(initWizard);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.BorrascasWizard = { init: initWizard };
})();
