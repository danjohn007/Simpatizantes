import Constants from 'expo-constants';

/** Dirección del sistema web (sin barra final). Se cambia con EXPO_PUBLIC_API_URL al compilar. */
export const API_URL = (process.env.EXPO_PUBLIC_API_URL ?? 'https://idactivos.digital/simpatizantes').replace(/\/+$/, '');

/** Versión de la app (la usa el servidor para exigir actualizaciones). */
export const APP_VERSION: string = Constants.expoConfig?.version ?? '1.0.0';

/** Tiempo máximo de espera de una petición. */
export const TIMEOUT_MS = 20000;

/** Cada cuánto se reintenta enviar lo pendiente mientras la app está abierta. */
export const REINTENTO_MS = 45000;
