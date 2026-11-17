import { api } from './api';
import { borrarItem, listarCola, marcarError } from './db';

export type ResultadoSync = {
  /** ok: terminó; sin_red: falta señal o falló el servidor (se reintenta); sesion: hay que iniciar sesión; actualizar: la app es muy vieja */
  estado: 'ok' | 'sin_red' | 'sesion' | 'actualizar';
  enviados: number;
  rechazados: number;
  avisos: string[];
};

const oyentes = new Set<() => void>();

/** Las pantallas se suscriben para refrescar contadores cuando cambia la cola. */
export function suscribir(fn: () => void): () => void {
  oyentes.add(fn);
  return () => {
    oyentes.delete(fn);
  };
}

export function avisarCambios(): void {
  oyentes.forEach((f) => f());
}

let enCurso: Promise<ResultadoSync> | null = null;

/** ¿Hay un envío en marcha? */
export function estaSincronizando(): boolean {
  return enCurso !== null;
}

/**
 * Envía al servidor lo que esté pendiente de este usuario. Si ya hay un envío en marcha, comparte su resultado,
 * así nunca se mandan dos veces las mismas capturas. Cada captura lleva su uuid: aunque se repita el envío,
 * el servidor no crea un registro doble.
 */
export function sincronizar(token: string, usuarioId: number): Promise<ResultadoSync> {
  if (!enCurso) {
    enCurso = ejecutar(token, usuarioId).finally(() => {
      enCurso = null;
      avisarCambios();
    });
    avisarCambios();
  }
  return enCurso;
}

async function ejecutar(token: string, usuarioId: number): Promise<ResultadoSync> {
  const resultado: ResultadoSync = { estado: 'ok', enviados: 0, rechazados: 0, avisos: [] };
  const pendientes = (await listarCola()).filter((i) => i.estado === 'pendiente' && i.payload._usuario === usuarioId);

  for (const item of pendientes) {
    const { _usuario: _omitido, ...cuerpo } = item.payload;
    let r;
    try {
      r = await api<{ ok?: boolean; permanente?: boolean; error?: string; avisos?: string[] }>('sincronizar.php', { token, body: cuerpo });
    } catch {
      resultado.estado = 'sin_red';
      break;
    }
    if (r.status === 401) {
      resultado.estado = 'sesion';
      break;
    }
    if (r.status === 426) {
      resultado.estado = 'actualizar';
      break;
    }
    if (r.status === 200 && r.data.ok) {
      await borrarItem(item.uuid);
      resultado.enviados++;
      if (r.data.avisos?.length) resultado.avisos.push(...r.data.avisos);
      continue;
    }
    if (r.status === 422 && r.data.permanente) {
      await marcarError(item.uuid, r.data.error ?? 'Datos no válidos');
      resultado.rechazados++;
      continue;
    }
    // Falla del servidor o respuesta inesperada: se queda en cola y se reintenta después
    resultado.estado = 'sin_red';
    break;
  }
  return resultado;
}
