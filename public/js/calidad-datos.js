/*
 * Calidad de datos al capturar un simpatizante:
 *  - normaliza mientras se escribe (CURP y clave en mayúsculas, teléfonos a 10 dígitos)
 *  - revisa en el servidor formato, duplicados y diferencias entre datos, y muestra el resultado
 * El formulario debe llevar data-api (URL de verificación) y, al editar, data-id.
 */
(function () {
    'use strict';
    var form = document.getElementById('formSimpatizante');
    if (!form || !form.dataset.api) { return; }

    var CAMPOS = ['nombre_completo', 'sexo', 'fecha_nacimiento', 'clave_elector', 'curp',
                  'seccion_electoral', 'telefono', 'whatsapp', 'email'];
    var panel = document.getElementById('calidadPanel');
    if (!panel) {
        panel = document.createElement('div');
        panel.id = 'calidadPanel';
        panel.className = 'mb-3';
        panel.setAttribute('aria-live', 'polite');
        form.insertBefore(panel, form.firstElementChild);
    }
    var token = (form.querySelector('input[name="csrf_token"]') || {}).value || '';
    var temporizador = null, peticion = 0;

    function campo(nombre) { return form.elements[nombre] || null; }

    // ---- normalización local (el servidor vuelve a normalizar) ----
    function mayusculas(input) {
        var pos = input.selectionStart;
        var limpio = input.value.toUpperCase().replace(/[\s\-_.]+/g, '');
        if (limpio !== input.value) {
            input.value = limpio;
            try { input.setSelectionRange(pos, pos); } catch (e) { /* tipo sin selección */ }
        }
    }
    function telefono10(input) {
        var d = input.value.replace(/\D+/g, '');
        if (d.length === 13 && /^(521|044|045)/.test(d)) { d = d.slice(3); }
        else if (d.length === 12 && d.indexOf('52') === 0) { d = d.slice(2); }
        input.value = d.slice(0, 10);
    }

    ['curp', 'clave_elector'].forEach(function (n) {
        var el = campo(n);
        if (el) { el.addEventListener('input', function () { mayusculas(el); }); }
    });
    ['telefono', 'whatsapp'].forEach(function (n) {
        var el = campo(n);
        if (el) { el.addEventListener('blur', function () { telefono10(el); }); }
    });
    var seccion = campo('seccion_electoral');
    if (seccion) {
        seccion.addEventListener('blur', function () {
            var d = seccion.value.replace(/\D+/g, '');
            if (d && d.length < 4) { seccion.value = ('0000' + d).slice(-4); }
        });
    }

    // ---- revisión en el servidor ----
    function programar() {
        clearTimeout(temporizador);
        temporizador = setTimeout(revisar, 600);
    }
    CAMPOS.forEach(function (n) {
        var el = campo(n);
        if (!el) { return; }
        el.addEventListener('change', programar);
        if (n !== 'sexo' && n !== 'fecha_nacimiento') { el.addEventListener('blur', programar); }
    });

    function revisar() {
        var datos = new URLSearchParams();
        var hay = false;
        CAMPOS.forEach(function (n) {
            var el = campo(n);
            if (el && el.value) { datos.append(n, el.value); hay = true; }
        });
        if (form.dataset.id) { datos.append('id', form.dataset.id); }
        if (!hay) { pintar(null); return; }
        var esta = ++peticion;

        fetch(form.dataset.api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': token, 'Accept': 'application/json' },
            body: datos.toString()
        }).then(function (r) { return r.json(); })
          .then(function (res) { if (esta === peticion) { pintar(res); } })
          .catch(function () { /* sin red: el servidor valida de todos modos al guardar */ });
    }

    function nodo(tipo, texto, enlace, etiquetaEnlace) {
        var div = document.createElement('div');
        div.className = 'alert alert-' + tipo + ' py-2 px-3 mb-2 d-flex align-items-start gap-2';
        div.setAttribute('role', tipo === 'danger' ? 'alert' : 'status');
        var ico = document.createElement('i');
        ico.className = 'bi ' + (tipo === 'danger' ? 'bi-x-octagon-fill' : 'bi-exclamation-triangle-fill') + ' mt-1';
        var cuerpo = document.createElement('div');
        cuerpo.appendChild(document.createTextNode(texto));
        if (enlace) {
            cuerpo.appendChild(document.createTextNode(' '));
            var a = document.createElement('a');
            a.href = enlace; a.target = '_blank'; a.rel = 'noopener';
            a.textContent = etiquetaEnlace;
            cuerpo.appendChild(a);
        }
        div.appendChild(ico); div.appendChild(cuerpo);
        return div;
    }

    function pintar(res) {
        panel.textContent = '';
        if (!res || res.error) { return; }
        var base = form.dataset.ver || '';

        Object.keys(res.errores || {}).forEach(function (k) {
            panel.appendChild(nodo('danger', res.errores[k]));
        });
        (res.duplicados || []).forEach(function (d) {
            var detalle = d.texto + ': ' + d.nombre + ' (sección ' + d.seccion + (d.fecha ? ', capturado el ' + d.fecha : '') + ').';
            if (d.tipo === 'duro') {
                detalle += ' No se puede guardar un registro repetido.';
            } else {
                detalle += ' Revise que no sea la misma persona.';
            }
            panel.appendChild(nodo(d.tipo === 'duro' ? 'danger' : 'warning', detalle,
                d.id && base ? base + d.id : null, 'Ver registro'));
        });
        (res.avisos || []).forEach(function (a) {
            panel.appendChild(nodo('warning', a.texto));
        });
    }

    // Al abrir un registro existente, se revisa una vez para mostrar avisos
    if (form.dataset.id) { setTimeout(revisar, 300); }
})();
