import { type ReactNode, useEffect } from 'react';
import { type StyleProp, StyleSheet, type ViewStyle } from 'react-native';
import Animated, {
  cancelAnimation,
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withRepeat,
  withSequence,
  withTiming,
} from 'react-native-reanimated';

/** La misma curva suave que usa el login web. */
export const SUAVE = Easing.bezier(0.22, 1, 0.36, 1);

/** Aparece subiendo y desvaneciéndose (como `rise` en la web). */
export function Entrada({ children, retraso = 0, distancia = 26, duracion = 800, style }: { children: ReactNode; retraso?: number; distancia?: number; duracion?: number; style?: StyleProp<ViewStyle> }) {
  const p = useSharedValue(0);
  useEffect(() => {
    p.value = withDelay(retraso, withTiming(1, { duration: duracion, easing: SUAVE }));
  }, [p, retraso, duracion]);
  const animado = useAnimatedStyle(() => ({ opacity: p.value, transform: [{ translateY: (1 - p.value) * distancia }] }));
  return <Animated.View style={[animado, style]}>{children}</Animated.View>;
}

/** Anillo que late alrededor del logo (como `pulseRing` en la web). */
export function AnilloPulso({ tamano, color = '#ff8000', radio = 12 }: { tamano: number; color?: string; radio?: number }) {
  const t = useSharedValue(0);
  useEffect(() => {
    t.value = withRepeat(withTiming(1, { duration: 2600, easing: Easing.out(Easing.quad) }), -1, false);
    return () => cancelAnimation(t);
  }, [t]);
  const anillo = useAnimatedStyle(() => ({ opacity: 0.55 * (1 - t.value), transform: [{ scale: 1 + t.value * 0.7 }] }));
  return <Animated.View pointerEvents="none" style={[{ position: 'absolute', width: tamano, height: tamano, borderRadius: radio, borderWidth: 2, borderColor: color }, anillo]} />;
}

/** Destello que cruza un botón cada cierto tiempo (como `shine` en la web). Va dentro de un contenedor con overflow hidden. */
export function Destello({ ancho }: { ancho: number }) {
  const t = useSharedValue(0);
  useEffect(() => {
    t.value = withRepeat(withSequence(withDelay(2200, withTiming(1, { duration: 900, easing: Easing.inOut(Easing.quad) })), withTiming(0, { duration: 0 })), -1, false);
    return () => cancelAnimation(t);
  }, [t]);
  const estilo = useAnimatedStyle(() => ({ transform: [{ translateX: -80 + t.value * (ancho + 160) }, { skewX: '-20deg' }] }));
  return <Animated.View pointerEvents="none" style={[estilos.destello, estilo]} />;
}

/** Sacudida horizontal para señalar un error (como `shake` en la web). Cambie `clave` para repetirla. */
export function Sacudida({ clave, children, style }: { clave: number; children: ReactNode; style?: StyleProp<ViewStyle> }) {
  const x = useSharedValue(0);
  useEffect(() => {
    if (!clave) return;
    x.value = withSequence(withTiming(-8, { duration: 70 }), withTiming(8, { duration: 90 }), withTiming(-6, { duration: 80 }), withTiming(6, { duration: 80 }), withTiming(0, { duration: 60 }));
  }, [clave, x]);
  const estilo = useAnimatedStyle(() => ({ transform: [{ translateX: x.value }] }));
  return <Animated.View style={[estilo, style]}>{children}</Animated.View>;
}

const estilos = StyleSheet.create({
  destello: { position: 'absolute', left: 0, top: 0, bottom: 0, width: 46, backgroundColor: 'rgba(255,255,255,0.55)' },
});
