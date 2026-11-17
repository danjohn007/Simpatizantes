/*
 * Captura en campo: guarda cada registro en el teléfono (IndexedDB) y lo envía al servidor
 * en cuanto hay señal. Cada captura lleva un uuid; el servidor lo usa para no duplicar si el
 * envío se reintenta.
 */
(function () {
    'use strict';
    var form = document.getElementById('formSimpatizante');
    if (!form) { return; }

    var $ = function (id) { return document.getElementById(id); };
    var CAMPOS = ['nombre_completo', 'domicilio_completo', 'seccion_electoral', 'whatsapp', 'telefono', 'email', 'clave_elector',
                  'curp', 'sexo', 'fecha_nacimiento', 'ciudad', 'observaciones'];
    var ubicacion = null;       // {lat, lng, precision, en}
    var sincronizando = false;
    var temporizador = null;

    // ------------------------------------------------------------ almacenamiento local
    var dbPromesa = null;
    function db() {
        if (dbPromesa) { return dbPromesa; }
        dbPromesa = new Promise(function (ok, fallo) {
            if (!window.indexedDB) { fallo(new Error('Este navegador no permite guardar sin conexión.')); return; }
            var r = indexedDB.open('captura-campo', 1);
            r.onupgradeneeded = function () { r.result.createObjectStore('cola', { keyPath: 'uuid' }); };
            r.onsuccess = function () { ok(r.result); };
            r.onerror = function () { fallo(r.error); };
        });
        return dbPromesa;
    }
    function tx(modo, fn) {
        return db().then(function (d) {
            return new Promise(function (ok, fallo) {
                var t = d.transaction('cola', modo), st = t.objectStore('cola'), res = fn(st);
                t.oncomplete = function () { ok(res && res.result !== undefined ? res.result : undefined); };
                t.onerror = function () { fallo(t.error); };
            });
        });
    }
    var guardarItem = function (item) { return tx('readwrite', function (s) { return s.put(item); }); };
    var borrarItem = function (uuid) { return tx('readwrite', function (s) { return s.delete(uuid); }); };
    var todos = function () { return tx('readonly', function (s) { return s.getAll(); }).then(function (l) { return (l || []).sort(function (a, b) { return a.creado < b.creado ? -1 : 1; }); }); };

    function uuid() {
        if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
        var b = new Uint8Array(16); (window.crypto || window.msCrypto).getRandomValues(b);
        b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
        var h = Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
        return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
    }

    // ------------------------------------------------------------ avisos
    var toastTimer = null;
    function aviso(texto, ms) {
        var t = $('toast'); t.textContent = texto; t.hidden = false;
        clearTimeout(toastTimer); toastTimer = setTimeout(function () { t.hidden = true; }, ms || 3500);
    }
    function estadoRed() {
        var en = navigator.onLine, chip = $('chipRed');
        chip.className = 'chip ' + (en ? 'on' : 'off');
        chip.innerHTML = '<i class="bi ' + (en ? 'bi-wifi' : 'bi-wifi-off') + '"></i><span>' + (en ? 'En línea' : 'Sin conexión') + '</span>';
    }

    // ------------------------------------------------------------ ubicación
    function pintarUbicacion(estado, texto) {
        $('locTexto').textContent = texto;
        var i = $('locIcono');
        i.className = 'bi ' + (estado === 'ok' ? 'bi-geo-alt-fill text-success' : estado === 'error' ? 'bi-geo-alt text-danger' : 'bi-geo-alt text-muted');
    }
    function pedirUbicacion(silencioso) {
        if (!navigator.geolocation) { pintarUbicacion('error', 'Este teléfono no ofrece ubicación'); return Promise.resolve(null); }
        pintarUbicacion('espera', 'Obteniendo ubicación…');
        return new Promise(function (ok) {
            navigator.geolocation.getCurrentPosition(function (p) {
                ubicacion = { lat: p.coords.latitude, lng: p.coords.longitude, precision: Math.round(p.coords.accuracy), en: Date.now() };
                pintarUbicacion('ok', 'Ubicación obtenida (±' + ubicacion.precision + ' m)' + (ubicacion.precision > 100 ? ' · poca precisión, salga a cielo abierto' : ''));
                $('btnUbicacion').textContent = 'Actualizar';
                ok(ubicacion);
            }, function (e) {
                var msg = e.code === 1 ? 'Permiso de ubicación negado. Actívelo en los ajustes del navegador.' : 'No se pudo obtener la ubicación';
                if (!silencioso || e.code !== 1) { pintarUbicacion('error', msg); } else { pintarUbicacion('espera', 'Ubicación sin obtener'); }
                ok(null);
            }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
        });
    }
    $('btnUbicacion').addEventListener('click', function () { pedirUbicacion(false); });
    // Si el permiso ya se concedió antes, se obtiene sola; si no, solo al tocar el botón
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then(function (p) {
            if (p.state === 'granted') { pedirUbicacion(true); }
        }).catch(function () {});
    }

    // ------------------------------------------------------------ guardar
    function leerFormulario() {
        var r = {};
        CAMPOS.forEach(function (n) { var el = form.elements[n]; r[n] = el ? el.value.trim() : ''; });
        var camp = form.elements.campana_id; if (camp) { r.campana_id = camp.value; }
        r.consentimiento = (form.elements.consentimiento && form.elements.consentimiento.checked) ? '1' : '';
        return r;
    }
    function limpiar() {
        CAMPOS.forEach(function (n) { var el = form.elements[n]; if (el) { el.value = ''; } });
        if (form.elements.consentimiento) { form.elements.consentimiento.checked = false; }
        $('calidadPanel').textContent = '';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        form.elements.nombre_completo.focus();
    }

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var d = leerFormulario();
        var falta = [];
        if (!d.nombre_completo || d.nombre_completo.indexOf(' ') < 0) { falta.push('nombre y apellido'); }
        if (!d.domicilio_completo) { falta.push('domicilio'); }
        if (!/^\d{1,4}$/.test(d.seccion_electoral)) { falta.push('sección (4 dígitos)'); }
        if (falta.length) { aviso('Falta: ' + falta.join(', ')); return; }
        d.seccion_electoral = ('0000' + d.seccion_electoral).slice(-4);

        // Ubicación: la más reciente (hasta 10 min); si ya hay permiso se intenta refrescar sin hacer esperar
        var guardar = function (u) {
            var item = Object.assign({ uuid: uuid(), creado: new Date().toISOString(), capturado_en: new Date().toISOString(), estado: 'pendiente' }, d);
            if (u && Date.now() - u.en < 600000) { item.latitud = u.lat.toFixed(7); item.longitud = u.lng.toFixed(7); }
            guardarItem(item).then(function () {
                limpiar();
                aviso(navigator.onLine ? 'Guardado. Enviando…' : 'Guardado en el teléfono. Se enviará al volver la señal.');
                return pintarCola();
            }).then(sincronizar).catch(function (e) { aviso('No se pudo guardar: ' + e.message, 6000); });
        };
        guardar(ubicacion);
    });

    // ------------------------------------------------------------ cola en pantalla
    function nodoItem(it) {
        var fila = document.createElement('div'); fila.className = 'cola-item';
        var txt = document.createElement('div'); txt.className = 'txt';
        var b = document.createElement('b'); b.textContent = it.nombre_completo;
        var s = document.createElement('span'); s.className = 'small ' + (it.estado === 'error' ? 'text-danger' : 'text-muted');
        s.textContent = it.estado === 'error' ? (it.mensaje || 'No se pudo enviar') : 'Sección ' + it.seccion_electoral + ' · esperando envío';
        txt.appendChild(b); txt.appendChild(s); fila.appendChild(txt);
        if (it.estado === 'error') {
            var ed = document.createElement('button'); ed.type = 'button'; ed.className = 'btn btn-soft btn-sm'; ed.textContent = 'Corregir';
            ed.addEventListener('click', function () { corregir(it); });
            fila.appendChild(ed);
        }
        var x = document.createElement('button'); x.type = 'button'; x.className = 'btn btn-sm btn-outline-danger'; x.setAttribute('aria-label', 'Descartar');
        x.innerHTML = '<i class="bi bi-trash"></i>';
        x.addEventListener('click', function () {
            if (confirm('¿Descartar este registro? No se enviará.')) { borrarItem(it.uuid).then(pintarCola); }
        });
        fila.appendChild(x);
        return fila;
    }
    function pintarCola() {
        return todos().then(function (lista) {
            $('numCola').textContent = lista.length;
            $('cardCola').hidden = lista.length === 0;
            var cont = $('listaCola'); cont.textContent = '';
            lista.forEach(function (it) { cont.appendChild(nodoItem(it)); });
            return lista;
        }).catch(function () { return []; });
    }
    function corregir(it) {
        CAMPOS.forEach(function (n) { var el = form.elements[n]; if (el) { el.value = it[n] || ''; } });
        if (form.elements.campana_id && it.campana_id) { form.elements.campana_id.value = it.campana_id; }
        if (form.elements.consentimiento) { form.elements.consentimiento.checked = it.consentimiento === '1'; }
        borrarItem(it.uuid).then(pintarCola);
        aviso('Corrija los datos y guarde de nuevo.');
        window.scrollTo({ top: 0, behavior: 'smooth' });
        var masDatos = $('masDatos'); if (masDatos && (it.curp || it.clave_elector || it.email)) { masDatos.classList.add('show'); }
    }

    // ------------------------------------------------------------ envío al servidor
    function tokenFresco() {
        return fetch(form.dataset.csrfUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (r) { if (r.status === 401) { throw { sesion: true }; } return r.json(); })
            .then(function (j) { return j.token; });
    }
    function enviarUno(item, token) {
        var cuerpo = Object.assign({}, item); delete cuerpo.estado; delete cuerpo.mensaje; delete cuerpo.creado;
        return fetch(form.dataset.sync, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': token },
            body: JSON.stringify(cuerpo)
        }).then(function (r) {
            if (r.status === 401 || r.status === 403) { throw { sesion: true }; }
            return r.json().then(function (j) { j.http = r.status; return j; }, function () { return { ok: false, permanente: false, http: r.status }; });
        });
    }

    function sincronizar() {
        if (sincronizando || !navigator.onLine) { return Promise.resolve(); }
        sincronizando = true;
        $('btnSync').disabled = true;
        var enviados = 0, rechazados = 0;

        return todos().then(function (lista) {
            var pendientes = lista.filter(function (i) { return i.estado !== 'error'; });
            if (!pendientes.length) { return; }
            return tokenFresco().then(function (token) {
                var cadena = Promise.resolve();
                pendientes.forEach(function (it) {
                    cadena = cadena.then(function () {
                        return enviarUno(it, token).then(function (res) {
                            if (res.ok) { enviados++; return borrarItem(it.uuid).then(function () { if (res.avisos && res.avisos.length) { aviso(res.avisos[0], 7000); } }); }
                            if (res.permanente) { rechazados++; it.estado = 'error'; it.mensaje = res.error || 'Datos no válidos'; return guardarItem(it); }
                            throw { temporal: true };   // falla del servidor: se reintenta luego
                        });
                    });
                });
                return cadena;
            });
        }).then(function () {
            $('avisoSesion').hidden = true;
            if (enviados) { aviso(enviados === 1 ? 'Registro enviado ✔' : enviados + ' registros enviados ✔'); }
            if (rechazados) { aviso(rechazados + ' registro(s) necesitan corrección (ver la lista).', 6000); }
        }).catch(function (e) {
            if (e && e.sesion) { $('avisoSesion').hidden = false; }
            // red caída o falla temporal: se queda en cola
        }).then(function () {
            sincronizando = false; $('btnSync').disabled = false;
            return pintarCola();
        }).then(programar);
    }

    // Reintento periódico mientras haya algo pendiente
    function programar(lista) {
        clearTimeout(temporizador);
        var hay = (lista || []).some(function (i) { return i.estado !== 'error'; });
        if (hay) { temporizador = setTimeout(sincronizar, 45000); }
    }

    $('btnSync').addEventListener('click', function () {
        if (!navigator.onLine) { aviso('Sin conexión por ahora.'); return; }
        sincronizar();
    });
    window.addEventListener('online', function () { estadoRed(); sincronizar(); });
    window.addEventListener('offline', estadoRed);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { sincronizar(); } });

    // ------------------------------------------------------------ arranque
    estadoRed();
    pintarCola().then(function (l) { if (l.length) { sincronizar(); } });

    if ('serviceWorker' in navigator && window.CAPTURA) {
        navigator.serviceWorker.register(window.CAPTURA.sw, { scope: window.CAPTURA.scope }).catch(function () {});
    }
})();
