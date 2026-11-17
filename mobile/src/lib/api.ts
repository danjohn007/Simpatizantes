import { API_URL, APP_VERSION, TIMEOUT_MS } from './config';

/** No se pudo hablar con el servidor (sin señal, tiempo agotado, servidor caído). */
export class RedError extends Error {
  constructor(mensaje = 'Sin conexión con el servidor') {
    super(mensaje);
    this.name = 'RedError';
  }
}

export type Respuesta<T> = { status: number; data: T };

type Opciones = { method?: 'GET' | 'POST'; body?: unknown; form?: FormData; token?: string | null; timeoutMs?: number };

/**
 * Llama a la API de la app (public/api/app/<ruta>).
 * Devuelve siempre el código HTTP y el JSON; lanza RedError solo si no hubo respuesta.
 * El token va en "Authorization" y también en "X-App-Token" (algunos hostings quitan la primera).
 */
export async function api<T = Record<string, any>>(ruta: string, opts: Opciones = {}): Promise<Respuesta<T>> {
  const controlador = new AbortController();
  const reloj = setTimeout(() => controlador.abort(), opts.timeoutMs ?? TIMEOUT_MS);
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-App-Version': APP_VERSION,
  };
  if (opts.body !== undefined && !opts.form) headers['Content-Type'] = 'application/json';
  if (opts.token) {
    headers.Authorization = `Bearer ${opts.token}`;
    headers['X-App-Token'] = opts.token;
  }
  try {
    const res = await fetch(`${API_URL}/public/api/app/${ruta}`, {
      method: opts.method ?? (opts.body !== undefined || opts.form ? 'POST' : 'GET'),
      headers,
      // Con `form` (archivos) el tipo de contenido lo fija el sistema con su límite; con `body` se envía JSON
      body: opts.form ?? (opts.body !== undefined ? JSON.stringify(opts.body) : undefined),
      signal: controlador.signal,
    });
    let data = {} as T;
    try {
      data = (await res.json()) as T;
    } catch {
      /* respuesta sin JSON (por ejemplo, una página de error del hosting) */
    }
    return { status: res.status, data };
  } catch {
    throw new RedError();
  } finally {
    clearTimeout(reloj);
  }
}
