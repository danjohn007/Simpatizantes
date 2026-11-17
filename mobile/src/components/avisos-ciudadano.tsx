import { Pressable, Text, View } from 'react-native';

import { useAuth } from '@/lib/auth';
import { useOnline } from '@/lib/hooks';
import { C } from '@/lib/theme';
import { Aviso } from './ui';

/** Franja de estado de la ciudadanía: sin conexión, sesión vencida o app por actualizar. */
export function AvisosCiudadano() {
  const { sesionVencida, actualizarApp, reautenticar } = useAuth();
  const enLinea = useOnline();
  return (
    <>
      {actualizarApp ? <Aviso tono="bad">Hay una versión nueva de la app. Actualícela para seguir enviando reportes.</Aviso> : null}
      {sesionVencida ? (
        <View style={{ backgroundColor: C.warnSoft, paddingHorizontal: 16, paddingVertical: 9, gap: 4 }}>
          <Text style={{ color: C.warn, fontSize: 13, fontWeight: '600' }}>Su sesión venció.</Text>
          <Pressable onPress={reautenticar} accessibilityRole="button">
            <Text style={{ color: C.brandInk, fontSize: 13, fontWeight: '800' }}>Iniciar sesión de nuevo →</Text>
          </Pressable>
        </View>
      ) : null}
      {!enLinea ? <Aviso tono="warn">Sin conexión: verá lo último que descargó. Para enviar un reporte necesita internet.</Aviso> : null}
    </>
  );
}
