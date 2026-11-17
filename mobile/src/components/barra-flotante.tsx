import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { type BottomTabBarProps } from 'expo-router/js-tabs';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import Animated, { LinearTransition, ZoomIn } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { C, sombra } from '@/lib/theme';

type Icono = keyof typeof Ionicons.glyphMap;

/** Íconos por pantalla: [normal, relleno cuando está activa]. */
export type IconosBarra = Record<string, [Icono, Icono]>;

/**
 * Barra de navegación flotante, estilo Instagram: una píldora oscura que "flota" sobre el contenido,
 * con íconos; la pestaña activa se rellena de naranja y muestra su nombre.
 */
export function BarraFlotante({ state, descriptors, emitter, navigateToTab, iconos }: BottomTabBarProps & { iconos: IconosBarra }) {
  const insets = useSafeAreaInsets();
  return (
    <View pointerEvents="box-none" style={[estilos.contenedor, { bottom: Math.max(insets.bottom, 10) + 6 }]}>
      <View style={estilos.barra}>
        {state.routes.map((ruta, i) => {
          const { options } = descriptors[ruta.key];
          // Las pantallas ocultas (detalles) no llevan botón
          if ((options as { href?: unknown }).href === null) return null;
          const activa = state.index === i;
          const par = iconos[ruta.name] ?? (['ellipse-outline', 'ellipse'] as [Icono, Icono]);
          const etiqueta = typeof options.title === 'string' ? options.title : ruta.name;
          const insignia = options.tabBarBadge;

          function pulsar() {
            Haptics.selectionAsync().catch(() => undefined);
            const evento = emitter.emit({ type: 'tabPress', target: ruta.key, canPreventDefault: true });
            if (!activa && !evento.defaultPrevented) navigateToTab(ruta.key);
          }

          return (
            <Pressable
              key={ruta.key}
              onPress={pulsar}
              accessibilityRole="tab"
              accessibilityLabel={etiqueta}
              accessibilityState={{ selected: activa }}
              hitSlop={6}>
              <Animated.View layout={LinearTransition.springify().damping(20).stiffness(220)} style={[estilos.item, activa && estilos.itemActivo]}>
                <View>
                  <Ionicons name={activa ? par[1] : par[0]} size={23} color={activa ? C.ink : '#e9e1d6'} />
                  {insignia !== undefined && insignia !== null && insignia !== '' ? (
                    <View style={estilos.insignia}>
                      <Text style={estilos.insigniaTexto}>{insignia}</Text>
                    </View>
                  ) : null}
                </View>
                {activa ? (
                  <Animated.Text entering={ZoomIn.duration(180)} numberOfLines={1} style={estilos.etiqueta}>
                    {etiqueta}
                  </Animated.Text>
                ) : null}
              </Animated.View>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  contenedor: { position: 'absolute', left: 0, right: 0, alignItems: 'center' },
  barra: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: C.ink,
    borderRadius: 34,
    padding: 7,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.08)',
    ...sombra(10, 0.38, 20, '#000000'),
  },
  item: { flexDirection: 'row', alignItems: 'center', gap: 7, height: 46, minWidth: 46, paddingHorizontal: 12, borderRadius: 24, justifyContent: 'center' },
  itemActivo: { backgroundColor: C.brand, paddingHorizontal: 16 },
  etiqueta: { color: C.ink, fontWeight: '800', fontSize: 13.5 },
  insignia: { position: 'absolute', top: -7, right: -9, minWidth: 17, height: 17, borderRadius: 9, backgroundColor: C.brand, borderWidth: 2, borderColor: C.ink, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 3 },
  insigniaTexto: { color: C.ink, fontSize: 10, fontWeight: '800' },
});
