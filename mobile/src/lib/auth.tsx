import * as Device from 'expo-device';
import * as SecureStore from 'expo-secure-store';
import Storage from 'expo-sqlite/kv-store';
import { createContext, type ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Platform } from 'react-native';

import { api, RedError } from './api';

export type Usuario = { id: number; nombre: string; rol: string; campana_id: number | null; campana: string | null };
export type Meta = { id: number; objetivo: number; registrados: number; validados: number; desde: string; hasta: string; seccion: string | null };
export type Tema = { clave: string; etiqueta: string };
export type Me = {
  usuario: Usuario;
  metas: Meta[];
  zonas: string[];
  temas: Tema[];
  campanas: { id: number; nombre: string }[];
  version_minima: string;
};
export type Ciudadano = { id: number; nombre: string; correo: string; municipio: string | null; colonia: string | null };

type Estado = 'cargando' | 'sin_sesion' | 'equipo' | 'ciudadano';

export type DatosRegistro = { nombre: string; correo: string; password: string; municipio: string; colonia: string };

type Contexto = {
  estado: Estado;
  usuario: Usuario | null;
  ciudadano: Ciudadano | null;
  token: string | null;
  me: Me | null;
  /** El servidor ya no reconoce la sesión: lo capturado sigue guardado, pero hay que volver a entrar para enviarlo. */
  sesionVencida: boolean;
  /** El servidor pide una versión más nueva de la app. */
  actualizarApp: boolean;
  /** Inicia sesión: el servidor responde si es una cuenta del equipo o de la ciudadanía y la app abre la cara correspondiente. */
  login: (usuario: string, password: string) => Promise<{ ok: boolean; error?: string }>;
  registrarCiudadano: (d: DatosRegistro) => Promise<{ ok: boolean; error?: string }>;
  salir: () => Promise<void>;
  /** Borra la cuenta de ciudadano en el servidor y cierra la sesión. */
  eliminarCuenta: () => Promise<{ ok: boolean; error?: string }>;
  reautenticar: () => Promise<void>;
  refrescarMe: () => Promise<void>;
  marcarSesionVencida: () => void;
  marcarActualizar: () => void;
};

const AuthContext = createContext<Contexto | null>(null);

const K = { token: 'token', tipo: 'tipo', usuario: 'usuario', me: 'me', ciudadano: 'ciudadano' } as const;

/** Datos del ciudadano guardados en el teléfono para consultarlos sin conexión (se borran al salir). */
const CLAVES_CIUDADANO = ['c_reportes', 'c_eventos', 'c_anonimos', 'c_perfil'];

function leerJSON<T>(clave: string): T | null {
  try {
    const v = Storage.getItemSync(clave);
    return v ? (JSON.parse(v) as T) : null;
  } catch {
    return null;
  }
}

const dispositivo = () => ({ dispositivo: Device.deviceName ?? Device.modelName ?? 'Dispositivo', plataforma: Platform.OS });

export function AuthProvider({ children }: { children: ReactNode }) {
  const [estado, setEstado] = useState<Estado>('cargando');
  const [usuario, setUsuario] = useState<Usuario | null>(null);
  const [ciudadano, setCiudadano] = useState<Ciudadano | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [me, setMe] = useState<Me | null>(null);
  const [sesionVencida, setSesionVencida] = useState(false);
  const [actualizarApp, setActualizarApp] = useState(false);

  const refrescarEquipo = useCallback(async (t: string) => {
    try {
      const r = await api<Me & { ok?: boolean }>('me.php', { token: t });
      if (r.status === 200 && r.data.usuario) {
        setMe(r.data);
        setUsuario(r.data.usuario);
        Storage.setItemSync(K.me, JSON.stringify(r.data));
        Storage.setItemSync(K.usuario, JSON.stringify(r.data.usuario));
        setSesionVencida(false);
      } else if (r.status === 401) {
        setSesionVencida(true);
      } else if (r.status === 426) {
        setActualizarApp(true);
      }
    } catch (e) {
      if (!(e instanceof RedError)) throw e; // sin señal: se sigue con lo guardado
    }
  }, []);

  const refrescarCiudadano = useCallback(async (t: string) => {
    try {
      const r = await api<{ ciudadano?: Ciudadano }>('ciudadano-me.php', { token: t });
      if (r.status === 200 && r.data.ciudadano) {
        setCiudadano(r.data.ciudadano);
        Storage.setItemSync(K.ciudadano, JSON.stringify(r.data.ciudadano));
        setSesionVencida(false);
      } else if (r.status === 401) {
        setSesionVencida(true);
      } else if (r.status === 426) {
        setActualizarApp(true);
      }
    } catch (e) {
      if (!(e instanceof RedError)) throw e;
    }
  }, []);

  // Arranque: se restaura la sesión guardada, incluso sin conexión
  useEffect(() => {
    let vivo = true;
    (async () => {
      const tipo = Storage.getItemSync(K.tipo);
      const t = tipo ? await SecureStore.getItemAsync(K.token) : null;
      if (tipo === 'equipo' && t) {
        const u = leerJSON<Usuario>(K.usuario);
        if (u && vivo) {
          setToken(t);
          setUsuario(u);
          setMe(leerJSON<Me>(K.me));
          setEstado('equipo');
          refrescarEquipo(t);
          return;
        }
      } else if (tipo === 'ciudadano' && t) {
        const c = leerJSON<Ciudadano>(K.ciudadano);
        if (c && vivo) {
          setToken(t);
          setCiudadano(c);
          setEstado('ciudadano');
          refrescarCiudadano(t);
          return;
        }
      }
      if (vivo) setEstado('sin_sesion');
    })().catch(() => vivo && setEstado('sin_sesion'));
    return () => {
      vivo = false;
    };
  }, [refrescarEquipo, refrescarCiudadano]);

  /** Guarda la sesión que devolvió el servidor y abre la cara que corresponde. */
  const abrirSesion = useCallback(
    async (data: { token?: string; tipo?: string; usuario?: Usuario; ciudadano?: Ciudadano }) => {
      if (!data.token) return false;
      await SecureStore.setItemAsync(K.token, data.token);
      setToken(data.token);
      setSesionVencida(false);
      if (data.tipo === 'ciudadano' && data.ciudadano) {
        Storage.setItemSync(K.tipo, 'ciudadano');
        Storage.setItemSync(K.ciudadano, JSON.stringify(data.ciudadano));
        setCiudadano(data.ciudadano);
        setEstado('ciudadano');
        return true;
      }
      if (data.usuario) {
        Storage.setItemSync(K.tipo, 'equipo');
        Storage.setItemSync(K.usuario, JSON.stringify(data.usuario));
        setUsuario(data.usuario);
        setEstado('equipo');
        refrescarEquipo(data.token);
        return true;
      }
      return false;
    },
    [refrescarEquipo],
  );

  const login = useCallback(
    async (nombre: string, password: string) => {
      try {
        const r = await api<{ ok?: boolean; token?: string; tipo?: string; usuario?: Usuario; ciudadano?: Ciudadano; error?: string }>('login.php', {
          body: { usuario: nombre.trim(), password, ...dispositivo() },
        });
        if (r.status === 200 && r.data.ok && (await abrirSesion(r.data))) return { ok: true };
        if (r.status === 426) {
          setActualizarApp(true);
          return { ok: false, error: r.data.error ?? 'Actualice la app para continuar.' };
        }
        return { ok: false, error: r.data.error ?? 'No se pudo iniciar sesión. Intente de nuevo.' };
      } catch (e) {
        if (e instanceof RedError) return { ok: false, error: 'Sin conexión. La primera vez se necesita internet para iniciar sesión.' };
        return { ok: false, error: 'No se pudo iniciar sesión.' };
      }
    },
    [abrirSesion],
  );

  const registrarCiudadano = useCallback(
    async (d: DatosRegistro) => {
      try {
        const r = await api<{ ok?: boolean; token?: string; tipo?: string; ciudadano?: Ciudadano; error?: string }>('ciudadano-registro.php', {
          body: { nombre: d.nombre, correo: d.correo, password: d.password, municipio: d.municipio, colonia: d.colonia, acepto: true, ...dispositivo() },
        });
        if (r.status === 200 && r.data.ok && (await abrirSesion(r.data))) return { ok: true };
        if (r.status === 426) setActualizarApp(true);
        return { ok: false, error: r.data.error ?? 'No se pudo crear la cuenta. Intente de nuevo.' };
      } catch (e) {
        if (e instanceof RedError) return { ok: false, error: 'Sin conexión. Se necesita internet para crear la cuenta.' };
        return { ok: false, error: 'No se pudo crear la cuenta.' };
      }
    },
    [abrirSesion],
  );

  const limpiarSesion = useCallback(async () => {
    await SecureStore.deleteItemAsync(K.token).catch(() => undefined);
    [K.tipo, K.usuario, K.me, K.ciudadano, ...CLAVES_CIUDADANO].forEach((k) => Storage.removeItemSync(k));
    setToken(null);
    setUsuario(null);
    setCiudadano(null);
    setMe(null);
    setSesionVencida(false);
    setEstado('sin_sesion');
  }, []);

  const salir = useCallback(async () => {
    if (token) {
      try {
        await api('logout.php', { method: 'POST', token });
      } catch {
        /* sin señal: la sesión del servidor vence sola */
      }
    }
    await limpiarSesion();
  }, [token, limpiarSesion]);

  const eliminarCuenta = useCallback(async () => {
    if (!token) return { ok: false, error: 'No hay una sesión activa.' };
    try {
      const r = await api<{ ok?: boolean; error?: string }>('ciudadano-cuenta.php', { token, body: { accion: 'eliminar' } });
      if (r.status === 200 && r.data.ok) {
        await limpiarSesion();
        return { ok: true };
      }
      return { ok: false, error: r.data.error ?? 'No se pudo eliminar la cuenta.' };
    } catch {
      return { ok: false, error: 'Sin conexión. Se necesita internet para eliminar la cuenta.' };
    }
  }, [token, limpiarSesion]);

  /** Vuelve a la pantalla de inicio de sesión sin tocar lo capturado (las capturas pendientes del equipo siguen en el teléfono). */
  const reautenticar = useCallback(async () => {
    await SecureStore.deleteItemAsync(K.token).catch(() => undefined);
    [K.tipo, K.me].forEach((k) => Storage.removeItemSync(k));
    setToken(null);
    setUsuario(null);
    setCiudadano(null);
    setMe(null);
    setSesionVencida(false);
    setEstado('sin_sesion');
  }, []);

  const refrescarMe = useCallback(async () => {
    if (token && estado === 'equipo') await refrescarEquipo(token);
  }, [token, estado, refrescarEquipo]);

  const valor = useMemo<Contexto>(
    () => ({
      estado,
      usuario,
      ciudadano,
      token,
      me,
      sesionVencida,
      actualizarApp,
      login,
      registrarCiudadano,
      salir,
      eliminarCuenta,
      reautenticar,
      refrescarMe,
      marcarSesionVencida: () => setSesionVencida(true),
      marcarActualizar: () => setActualizarApp(true),
    }),
    [estado, usuario, ciudadano, token, me, sesionVencida, actualizarApp, login, registrarCiudadano, salir, eliminarCuenta, reautenticar, refrescarMe],
  );

  return <AuthContext.Provider value={valor}>{children}</AuthContext.Provider>;
}

export function useAuth(): Contexto {
  const c = useContext(AuthContext);
  if (!c) throw new Error('useAuth debe usarse dentro de AuthProvider');
  return c;
}
