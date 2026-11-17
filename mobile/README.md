# ID Simpatizantes (app móvil)

App nativa (React Native + Expo) con **un solo inicio de sesión y dos caras**:

- **Equipo** (capturistas y demás cuentas del sistema): captura de simpatizantes en campo, **funciona sin conexión**,
  con ubicación, consentimiento y la pregunta "¿qué le gustaría mejorar en su colonia?". Usa los mismos usuarios,
  contraseñas y base de datos del sistema web (`idactivos.digital/simpatizantes`).
- **Ciudadano**: reportes, denuncias (con opción anónima), eventos y avisos. *Versión de demostración*: sus datos
  viven solo en el teléfono hasta que exista el módulo de atención ciudadana en el servidor.

## Probar en el teléfono

```bash
npm install
npx expo start          # escanee el QR con Expo Go
```

Expo Go sirve para desarrollo. Para TestFlight y las tiendas se compila con EAS (abajo).

La dirección del sistema se toma de `EXPO_PUBLIC_API_URL` (por defecto `https://idactivos.digital/simpatizantes`).
Para probar contra otro servidor: `EXPO_PUBLIC_API_URL=https://mi-servidor npx expo start`.

## Enviar a TestFlight

Requiere una cuenta de Apple Developer (99 USD/año) y estar dentro de la organización de Expo (`impactos-digitales`).

```bash
eas login
eas build --platform ios --profile production    # pide iniciar sesión en Apple la primera vez; EAS crea certificados y perfiles
eas submit --platform ios --latest               # sube la compilación a App Store Connect / TestFlight
```

Antes de la primera compilación, revise en `app.json`:

- `ios.bundleIdentifier` (hoy `com.impactosdigitales.idsimpatizantes`): **no se puede cambiar después** de publicar.
- `name`, íconos (`assets/images`) y textos de permisos.

Compilación para probar entre el equipo sin pasar por la tienda: `eas build --platform ios --profile preview`
(requiere registrar los dispositivos con `eas device:create`).

## Servidor

Antes de usar la app, en el sistema web ejecute `database/app_movil.sql` (después de los scripts de Sprint 2 al 5).
Para la cara ciudadana ejecute también `database/atencion_ciudadana.sql` (después de `app_movil.sql`).
La API de la app vive en `public/api/app/`: `login`, `logout`, `me`, `sincronizar`, `verificar` (equipo) y `ciudadano-registro`, `ciudadano-me`, `ciudadano-cuenta`, `reportes`, `reporte-foto`, `seguimiento`, `eventos` (ciudadanía).
El mismo login decide la cara: usuario del equipo, o correo de ciudadano. Reportes, denuncias, fotos, asistencia a eventos y cuenta se guardan en el servidor; el teléfono solo conserva la última copia para consultarla sin conexión.
Las denuncias anónimas no llevan cuenta, ubicación ni foto: se consultan con folio y código (guardados solo en el teléfono).

- Autenticación: token opaco de 30 días por dispositivo (en la base solo se guarda su hash), enviado en
  `Authorization: Bearer` y en `X-App-Token`. Se guarda en el llavero del teléfono (`expo-secure-store`).
- Sin conexión: cada captura se guarda en SQLite con un `uuid`; se envía al volver la señal y el servidor no duplica
  si se reintenta. Los registros rechazados (duplicado, dato inválido) quedan en *Pendientes* con el motivo para corregirlos.
- `X-App-Version`: el servidor puede exigir una versión mínima (`configuracion.app_version_minima`).

## Estructura

```
src/app/(auth)        inicio de sesión y alta de ciudadano
src/app/(equipo)      captura, pendientes, metas, perfil
src/app/(ciudadano)   inicio, reportar, mis reportes, eventos, cuenta
src/lib               api, auth, sqlite (cola), sincronización, tema
```

## Verificación

```bash
npx tsc --noEmit && npx expo lint && npx expo-doctor
```
