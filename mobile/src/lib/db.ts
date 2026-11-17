import * as SQLite from 'expo-sqlite';

/** Lo que se envía al servidor por cada simpatizante capturado. */
export type Captura = {
  uuid: string;
  _usuario: number; // quién lo capturó (para no mezclar cuentas en un mismo teléfono)
  capturado_en: string;
  nombre_completo: string;
  domicilio_completo: string;
  seccion_electoral: string;
  whatsapp: string;
  colonia: string;
  comentario_colonia: string;
  temas: string[];
  consentimiento: string; // '1' si aceptó recibir mensajes
  latitud?: string;
  longitud?: string;
  campana_id?: number;
};

export type ItemCola = {
  uuid: string;
  payload: Captura;
  estado: 'pendiente' | 'error';
  mensaje: string | null;
  creado: string;
};

type Fila = { uuid: string; payload: string; estado: string; mensaje: string | null; creado: string };

let abierta: Promise<SQLite.SQLiteDatabase> | null = null;

/** Base local del teléfono: guarda las capturas hasta que llegan al servidor. */
function base(): Promise<SQLite.SQLiteDatabase> {
  if (!abierta) {
    abierta = SQLite.openDatabaseAsync('captura.db').then(async (d) => {
      await d.execAsync(`
        PRAGMA journal_mode = WAL;
        CREATE TABLE IF NOT EXISTS cola (
          uuid TEXT PRIMARY KEY NOT NULL,
          payload TEXT NOT NULL,
          estado TEXT NOT NULL DEFAULT 'pendiente',
          mensaje TEXT,
          creado TEXT NOT NULL
        );
      `);
      return d;
    });
  }
  return abierta;
}

function aItem(f: Fila): ItemCola {
  return { uuid: f.uuid, payload: JSON.parse(f.payload) as Captura, estado: f.estado === 'error' ? 'error' : 'pendiente', mensaje: f.mensaje, creado: f.creado };
}

export async function encolar(c: Captura): Promise<void> {
  const d = await base();
  await d.runAsync('INSERT OR REPLACE INTO cola (uuid, payload, estado, mensaje, creado) VALUES (?, ?, ?, ?, ?)', [
    c.uuid,
    JSON.stringify(c),
    'pendiente',
    null,
    new Date().toISOString(),
  ]);
}

export async function listarCola(): Promise<ItemCola[]> {
  const d = await base();
  const filas = await d.getAllAsync<Fila>('SELECT uuid, payload, estado, mensaje, creado FROM cola ORDER BY creado ASC');
  return filas.map(aItem);
}

export async function obtenerItem(uuid: string): Promise<ItemCola | null> {
  const d = await base();
  const f = await d.getFirstAsync<Fila>('SELECT uuid, payload, estado, mensaje, creado FROM cola WHERE uuid = ?', [uuid]);
  return f ? aItem(f) : null;
}

export async function borrarItem(uuid: string): Promise<void> {
  const d = await base();
  await d.runAsync('DELETE FROM cola WHERE uuid = ?', [uuid]);
}

export async function marcarError(uuid: string, mensaje: string): Promise<void> {
  const d = await base();
  await d.runAsync("UPDATE cola SET estado = 'error', mensaje = ? WHERE uuid = ?", [mensaje, uuid]);
}

/** Deja un registro rechazado otra vez en espera (por ejemplo, tras corregirlo en el servidor). */
export async function reintentar(uuid: string): Promise<void> {
  const d = await base();
  await d.runAsync("UPDATE cola SET estado = 'pendiente', mensaje = NULL WHERE uuid = ?", [uuid]);
}
