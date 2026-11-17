import { useNetworkState } from 'expo-network';
import { useCallback, useEffect, useState } from 'react';

import { type ItemCola, listarCola } from './db';
import { suscribir } from './sync';

/** true si hay conexión (se asume que sí mientras el teléfono no diga lo contrario). */
export function useOnline(): boolean {
  const estado = useNetworkState();
  return estado.isConnected !== false && estado.isInternetReachable !== false;
}

/** Lista de capturas guardadas en el teléfono, siempre al día. */
export function useCola(): { cola: ItemCola[]; pendientes: number; conError: number; refrescar: () => void } {
  const [cola, setCola] = useState<ItemCola[]>([]);
  const refrescar = useCallback(() => {
    listarCola()
      .then(setCola)
      .catch(() => undefined);
  }, []);
  useEffect(() => {
    refrescar();
    return suscribir(refrescar);
  }, [refrescar]);
  return {
    cola,
    pendientes: cola.filter((i) => i.estado === 'pendiente').length,
    conError: cola.filter((i) => i.estado === 'error').length,
    refrescar,
  };
}
