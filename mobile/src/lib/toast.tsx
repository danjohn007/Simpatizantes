import { createContext, type ReactNode, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { C, R } from './theme';

const Ctx = createContext<(texto: string, ms?: number) => void>(() => undefined);

/** Aviso breve en la parte baja de la pantalla. */
export function ToastProvider({ children }: { children: ReactNode }) {
  const [texto, setTexto] = useState('');
  const reloj = useRef<ReturnType<typeof setTimeout> | null>(null);
  const insets = useSafeAreaInsets();

  const mostrar = useCallback((t: string, ms = 3200) => {
    setTexto(t);
    if (reloj.current) clearTimeout(reloj.current);
    reloj.current = setTimeout(() => setTexto(''), ms);
  }, []);
  useEffect(() => () => { if (reloj.current) clearTimeout(reloj.current); }, []);

  return (
    <Ctx.Provider value={mostrar}>
      {children}
      {texto ? (
        <View pointerEvents="none" style={[estilos.caja, { bottom: insets.bottom + 84 }]} accessibilityLiveRegion="polite">
          <Text style={estilos.texto}>{texto}</Text>
        </View>
      ) : null}
    </Ctx.Provider>
  );
}

export const useToast = () => useContext(Ctx);

const estilos = StyleSheet.create({
  caja: { position: 'absolute', left: 16, right: 16, backgroundColor: C.ink, borderRadius: R.button, paddingVertical: 12, paddingHorizontal: 16, zIndex: 50 },
  texto: { color: C.white, fontWeight: '600', fontSize: 14 },
});
