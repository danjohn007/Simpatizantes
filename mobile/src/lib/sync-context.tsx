import { createContext, type ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { AppState } from 'react-native';

import { useAuth } from './auth';
import { REINTENTO_MS } from './config';
import { useCola, useOnline } from './hooks';
import { estaSincronizando, sincronizar, suscribir } from './sync';
import { useToast } from './toast';

type Ctx = { sincronizarAhora: () => Promise<void>; sincronizando: boolean };
const SyncCtx = createContext<Ctx>({ sincronizarAhora: async () => undefined, sincronizando: false });

/**
 * Envía lo pendiente al servidor: al abrir la app, al volver la señal, al regresar de segundo plano
 * y cada cierto tiempo mientras haya algo por enviar. Nunca bloquea la captura.
 */
export function SyncProvider({ children }: { children: ReactNode }) {
  const { token, usuario, sesionVencida, marcarSesionVencida, marcarActualizar } = useAuth();
  const en_linea = useOnline();
  const { pendientes } = useCola();
  const toast = useToast();
  const [sincronizando, setSincronizando] = useState(false);
  const ultimoAviso = useRef('');

  const sincronizarAhora = useCallback(async () => {
    if (!token || !usuario) return;
    const r = await sincronizar(token, usuario.id);
    if (r.estado === 'sesion') marcarSesionVencida();
    if (r.estado === 'actualizar') marcarActualizar();
    if (r.enviados > 0) toast(r.enviados === 1 ? 'Registro enviado ✔' : `${r.enviados} registros enviados ✔`);
    if (r.avisos.length && r.avisos[0] !== ultimoAviso.current) {
      ultimoAviso.current = r.avisos[0];
      toast(r.avisos[0], 6000);
    }
    if (r.rechazados > 0) toast(`${r.rechazados} registro(s) necesitan corrección. Véalos en Pendientes.`, 5000);
  }, [token, usuario, marcarSesionVencida, marcarActualizar, toast]);

  // El indicador "enviando" sigue los avisos del módulo de sincronización
  useEffect(() => suscribir(() => setSincronizando(estaSincronizando())), []);

  // Al abrir y al recuperar la señal
  useEffect(() => {
    if (en_linea && pendientes > 0 && !sesionVencida) sincronizarAhora();
  }, [en_linea, pendientes, sesionVencida, sincronizarAhora]);

  // Reintento periódico mientras haya algo pendiente
  useEffect(() => {
    if (!en_linea || pendientes === 0 || sesionVencida) return;
    const reloj = setInterval(() => sincronizarAhora(), REINTENTO_MS);
    return () => clearInterval(reloj);
  }, [en_linea, pendientes, sesionVencida, sincronizarAhora]);

  // Al regresar de segundo plano
  useEffect(() => {
    const sub = AppState.addEventListener('change', (s) => {
      if (s === 'active') sincronizarAhora();
    });
    return () => sub.remove();
  }, [sincronizarAhora]);

  const valor = useMemo(() => ({ sincronizarAhora, sincronizando }), [sincronizarAhora, sincronizando]);
  return <SyncCtx.Provider value={valor}>{children}</SyncCtx.Provider>;
}

export const useSync = () => useContext(SyncCtx);
