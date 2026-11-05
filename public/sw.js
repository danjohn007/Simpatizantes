/*
 * Service worker de la captura en campo.
 * - La pantalla captura.php: primero la red; sin señal, la última copia guardada.
 * - Estilos, scripts, íconos y fuentes: se sirven de la copia local y se actualizan en segundo plano.
 * - Nada más se guarda (ni la API ni otras pantallas con datos de simpatizantes).
 */
var VERSION = 'captura-v1';
var ESTATICOS = /\/public\/(css|js|vendor|img)\//;

self.addEventListener('install', function (e) { self.skipWaiting(); });

self.addEventListener('activate', function (e) {
    e.waitUntil(
        caches.keys().then(function (claves) {
            return Promise.all(claves.filter(function (k) { return k !== VERSION; }).map(function (k) { return caches.delete(k); }));
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (e) {
    var req = e.request;
    if (req.method !== 'GET') { return; }
    var url = new URL(req.url);
    if (url.origin !== self.location.origin) { return; }

    // Pantalla de captura (navegación)
    if (req.mode === 'navigate' && /\/public\/captura\.php$/.test(url.pathname)) {
        e.respondWith(
            fetch(req).then(function (resp) {
                if (resp && resp.ok && !resp.redirected) {
                    var copia = resp.clone();
                    caches.open(VERSION).then(function (c) { c.put(url.pathname, copia); });
                }
                return resp;
            }).catch(function () {
                return caches.match(url.pathname).then(function (c) {
                    return c || new Response(
                        '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
                        '<body style="font-family:sans-serif;padding:2rem"><h2>Sin conexión</h2>' +
                        '<p>Abra esta pantalla una vez con señal para poder usarla sin conexión.</p>',
                        { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
                });
            })
        );
        return;
    }

    // Recursos estáticos: copia local + actualización en segundo plano
    if (ESTATICOS.test(url.pathname)) {
        e.respondWith(
            caches.open(VERSION).then(function (c) {
                return c.match(req).then(function (hit) {
                    var red = fetch(req).then(function (resp) {
                        if (resp && resp.ok) { c.put(req, resp.clone()); }
                        return resp;
                    }).catch(function () { return hit; });
                    return hit || red;
                });
            })
        );
    }
});
