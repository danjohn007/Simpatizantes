import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { AuthProvider, useAuth } from '@/lib/auth';
import { ToastProvider } from '@/lib/toast';

SplashScreen.preventAutoHideAsync().catch(() => undefined);

/**
 * Un solo inicio de sesión, tres posibles caras. El tipo de cuenta decide qué grupo de pantallas existe:
 * mientras no se cumple la condición, las otras pantallas ni siquiera se pueden abrir.
 */
function Puerta() {
  const { estado } = useAuth();

  useEffect(() => {
    if (estado !== 'cargando') SplashScreen.hideAsync().catch(() => undefined);
  }, [estado]);

  if (estado === 'cargando') return null;

  return (
    <>
      <StatusBar style="light" />
      <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: '#f5f2ee' } }}>
        <Stack.Protected guard={estado === 'sin_sesion'}>
          <Stack.Screen name="(auth)" />
        </Stack.Protected>
        <Stack.Protected guard={estado === 'equipo'}>
          <Stack.Screen name="(equipo)" />
        </Stack.Protected>
        <Stack.Protected guard={estado === 'ciudadano'}>
          <Stack.Screen name="(ciudadano)" />
        </Stack.Protected>
      </Stack>
    </>
  );
}

export default function RaizLayout() {
  return (
    <SafeAreaProvider>
      <AuthProvider>
        <ToastProvider>
          <Puerta />
        </ToastProvider>
      </AuthProvider>
    </SafeAreaProvider>
  );
}
