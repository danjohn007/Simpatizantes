import { type Ionicons } from '@expo/vector-icons';
import Storage from 'expo-sqlite/kv-store';
import { useCallback, useEffect, useMemo, useState } from 'react';

import { api, RedError } from './api';
import { useAuth } from './auth';
import { API_URL, APP_VERSION } from './config';

/**
 * Atención ciudadana: reportes, denuncias, eventos y avisos. Todo vive en el servidor; el teléfono solo guarda
 * la última copia para poder consultarla sin conexión.
 */

export type EstadoReporte = 'recibido' | 'revision' | 'atendido' | 'cerrado';
export const ESTADOS: Record<EstadoReporte, string> = { recibido: 'Recibido', revision: 'En revisión', atendido: 'Atendido', cerrado: 'Cerrado' };
export const tonoEstado = (e: EstadoReporte) => (e === 'atendido' || e === 'cerrado' ? 'ok' : e === 'revision' ? 'warn' : 'mute') as 'ok' | 'warn' | 'mute';

export type Reporte = {
  id: number;
  folio: string;
  tipo: 'reporte' | 'denuncia';
  categoria: string;
  categoria_nombre: string;
  descripcion: string;
  estado: EstadoReporte;
  anonimo: boolean;
  fecha: string;
  fotos: number;
};
export type Movimiento = { estado: EstadoReporte; mensaje: string | null; created_at: string };
export type DetalleReporte = Omit<Reporte, 'fotos'> & { fotos: number[]; historial: Movimiento[]; latitud: number | null; longitud: number | null };
export type Evento = {
  id: number;
  titulo: string;
  descripcion: string | null;
  lugar: string;
  inicio: string;
  fin: string | null;
  municipio: string | null;
  colonia: string | null;
  asistentes: number;
  asisto: boolean;
};
export type Aviso = { id: number; titulo: string; texto: string; fecha: string };
export type DenunciaAnonima = { folio: string; codigo: string; fecha: string };

export type Categoria = { id: string; nombre: string; icono: keyof typeof Ionicons.glyphMap };
export const CATEGORIAS: Categoria[] = [
  { id: 'baches', nombre: 'Baches', icono: 'construct-outline' },
  { id: 'alumbrado', nombre: 'Alumbrado', icono: 'bulb-outline' },
  { id: 'agua', nombre: 'Agua y drenaje', icono: 'water-outline' },
  { id: 'basura', nombre: 'Basura', icono: 'trash-outline' },
  { id: 'seguridad', nombre: 'Seguridad', icono: 'shield-outline' },
  { id: 'otro', nombre: 'Otro', icono: 'ellipsis-horizontal' },
];

/** Fecha corta en español de México: "14 nov" y "5:00 pm". */
export function partesFecha(iso: string): { dia: string; mes: string; hora: string } {
  const d = new Date(iso.replace(' ', 'T'));
  return {
    dia: String(d.getDate()),
    mes: d.toLocaleDateString('es-MX', { month: 'short' }).replace('.', ''),
    hora: d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit' }),
  };
}
export const fechaLarga = (iso: string) => new Date(iso.replace(' ', 'T')).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });

// ---------------------------------------------------------------- almacenamiento local

function leer<T>(clave: string, defecto: T): T {
  try {
    const v = Storage.getItemSync(clave);
    return v ? (JSON.parse(v) as T) : defecto;
  } catch {
    return defecto;
  }
}
const guardar = (clave: string, valor: unknown) => Storage.setItemSync(clave, JSON.stringify(valor));

/** Folios y códigos de las denuncias anónimas: sin cuenta, es la única forma de consultar su avance. */
export const leerAnonimas = () => leer<DenunciaAnonima[]>('c_anonimos', []);
export function guardarAnonima(d: DenunciaAnonima) {
  guardar('c_anonimos', [d, ...leerAnonimas().filter((x) => x.folio !== d.folio)]);
}

// ---------------------------------------------------------------- llamadas al servidor

export class ErrorServidor extends Error {
  constructor(
    mensaje: string,
    public status: number,
  ) {
    super(mensaje);
  }
}

/** Mensaje claro para mostrar al usuario a partir de cualquier error. */
export const mensajeError = (e: unknown) => (e instanceof RedError ? 'Sin conexión. Revise su internet e intente de nuevo.' : e instanceof Error ? e.message : 'Ocurrió un error. Intente de nuevo.');

async function pedir<T>(ruta: string, opts: Parameters<typeof api>[1]): Promise<T> {
  const r = await api<T & { ok?: boolean; error?: string }>(ruta, opts);
  if (r.status === 200 && (r.data as { ok?: boolean }).ok !== false) return r.data as T;
  throw new ErrorServidor((r.data as { error?: string }).error ?? 'No se pudo completar la acción.', r.status);
}

/** URL (con encabezados de sesión) para mostrar una foto del reporte. */
export function fotoFuente(id: number, token: string | null, codigo?: string) {
  const headers: Record<string, string> = { 'X-App-Version': APP_VERSION };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
    headers['X-App-Token'] = token;
  }
  return { uri: `${API_URL}/public/api/app/reporte-foto.php?id=${id}${codigo ? `&codigo=${encodeURIComponent(codigo)}` : ''}`, headers };
}

/** Funciones de la atención ciudadana ya ligadas a la sesión. Si el servidor dice que la sesión venció, lo avisa a la app. */
export function useCiudadanoApi() {
  const { token, marcarSesionVencida, marcarActualizar } = useAuth();
  return useMemo(() => {
    const protegido = async <T>(f: () => Promise<T>): Promise<T> => {
      try {
        return await f();
      } catch (e) {
        if (e instanceof ErrorServidor && e.status === 401) marcarSesionVencida();
        if (e instanceof ErrorServidor && e.status === 426) marcarActualizar();
        throw e;
      }
    };
    return {
      token,
      reportes: () => protegido(() => pedir<{ reportes: Reporte[] }>('reportes.php', { token }).then((r) => r.reportes)),
      detalle: (folio: string) => protegido(() => pedir<{ reporte: DetalleReporte }>(`reportes.php?folio=${encodeURIComponent(folio)}`, { token }).then((r) => r.reporte)),
      /** Un reporte normal necesita sesión; una denuncia anónima se manda sin identificar a la persona. */
      crear: (d: { categoria: string; descripcion: string; latitud?: number; longitud?: number; anonimo?: boolean }) =>
        protegido(() => pedir<{ folio: string; codigo: string | null }>('reportes.php', { token: d.anonimo ? null : token, body: d })),
      subirFoto: async (folio: string, uri: string, codigo?: string | null) => {
        const form = new FormData();
        form.append('folio', folio);
        if (codigo) form.append('codigo', codigo);
        // En React Native los archivos se adjuntan como { uri, name, type }
        form.append('foto', { uri, name: 'foto.jpg', type: 'image/jpeg' } as unknown as Blob);
        return protegido(() => pedir<{ id: number }>('reporte-foto.php', { token: codigo ? null : token, form, timeoutMs: 90000 }));
      },
      seguimiento: (folio: string, codigo: string) =>
        pedir<{ reporte: Omit<DetalleReporte, 'fotos' | 'id' | 'tipo' | 'categoria' | 'descripcion' | 'anonimo'> & { fotos?: number[] } }>('seguimiento.php', { body: { folio, codigo } }).then((r) => r.reporte),
      eventos: () => protegido(() => pedir<{ eventos: Evento[]; avisos: Aviso[] }>('eventos.php', { token })),
      asistencia: (eventoId: number) => protegido(() => pedir<{ asisto: boolean }>('eventos.php', { token, body: { evento_id: eventoId } }).then((r) => r.asisto)),
      perfil: () => protegido(() => pedir<{ ciudadano: { quiere_sumarse: boolean } }>('ciudadano-me.php', { token }).then((r) => r.ciudadano)),
      sumarme: () => protegido(() => pedir<object>('ciudadano-cuenta.php', { token, body: { accion: 'sumarme' } })),
    };
  }, [token, marcarSesionVencida, marcarActualizar]);
}

// ---------------------------------------------------------------- datos con copia local

const oyentes = new Map<string, Set<() => void>>();

/** Avisa que los datos de `clave` cambiaron (por ejemplo, tras enviar un reporte) para que las pantallas se refresquen. */
export function invalidar(clave: string) {
  oyentes.get(clave)?.forEach((f) => f());
}

/**
 * Carga datos del servidor y guarda la última copia: sin conexión se sigue viendo lo ya descargado.
 * `cargar` debe ser estable (useCallback / useMemo).
 */
export function useDatos<T>(clave: string, cargar: () => Promise<T>, vacio: T) {
  const [datos, setDatos] = useState<T>(() => leer<T>(clave, vacio));
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState('');

  const aplicar = useCallback(
    (d: T) => {
      setDatos(d);
      setError('');
      setCargando(false);
      guardar(clave, d);
    },
    [clave],
  );
  const fallar = useCallback((e: unknown) => {
    setError(mensajeError(e));
    setCargando(false);
  }, []);

  useEffect(() => {
    let vivo = true;
    cargar()
      .then((d) => vivo && aplicar(d))
      .catch((e) => vivo && fallar(e));
    return () => {
      vivo = false;
    };
  }, [cargar, aplicar, fallar]);

  const recargar = useCallback(async () => {
    setCargando(true);
    try {
      aplicar(await cargar());
    } catch (e) {
      fallar(e);
    }
  }, [cargar, aplicar, fallar]);

  useEffect(() => {
    const set = oyentes.get(clave) ?? new Set();
    set.add(recargar);
    oyentes.set(clave, set);
    return () => {
      set.delete(recargar);
    };
  }, [clave, recargar]);

  return { datos, cargando, error, recargar, setDatos };
}
