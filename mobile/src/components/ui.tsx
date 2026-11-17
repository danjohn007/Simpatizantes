import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { LinearGradient } from 'expo-linear-gradient';
import { type ReactNode, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  type TextInputProps,
  View,
  type ViewStyle,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { C, R, sombra } from '@/lib/theme';
import { Destello } from './anim';

type Icono = keyof typeof Ionicons.glyphMap;

/** Espacio al final de cada pantalla para que la barra flotante no tape el contenido. */
export const ESPACIO_BARRA = 118;

/**
 * Pantalla con encabezado negro de esquinas redondeadas (con un destello naranja de fondo) y cuerpo que se desplaza.
 */
export function Pantalla({
  titulo,
  subtitulo,
  derecha,
  children,
  scroll = true,
  banner,
}: {
  titulo: string;
  subtitulo?: string;
  derecha?: ReactNode;
  children: ReactNode;
  scroll?: boolean;
  banner?: ReactNode;
}) {
  const insets = useSafeAreaInsets();
  return (
    <KeyboardAvoidingView style={estilos.pantalla} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <View style={[estilos.barra, { paddingTop: insets.top + 12 }]}>
        <View style={estilos.destelloFondo} pointerEvents="none" />
        <View style={estilos.destelloFondo2} pointerEvents="none" />
        <View style={{ flex: 1 }}>
          {subtitulo ? <Text style={estilos.subtitulo} numberOfLines={1}>{subtitulo}</Text> : null}
          <Text style={estilos.titulo} accessibilityRole="header" numberOfLines={1}>
            {titulo}
          </Text>
        </View>
        {derecha}
      </View>
      {banner}
      {scroll ? (
        <ScrollView
          contentContainerStyle={[estilos.cuerpo, { paddingBottom: ESPACIO_BARRA + insets.bottom }]}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="on-drag"
          showsVerticalScrollIndicator={false}>
          {children}
        </ScrollView>
      ) : (
        <View style={[estilos.cuerpo, { flex: 1 }]}>{children}</View>
      )}
    </KeyboardAvoidingView>
  );
}

/** Tarjeta blanca de sombra suave; con `titulo` lleva un encabezado con ícono. */
export function Tarjeta({ titulo, icono, children, estilo }: { titulo?: string; icono?: Icono; children: ReactNode; estilo?: ViewStyle }) {
  return (
    <View style={[estilos.tarjeta, estilo]}>
      {titulo ? (
        <View style={estilos.tarjetaTitulo}>
          {icono ? (
            <View style={estilos.iconoRedondo}>
              <Ionicons name={icono} size={16} color={C.brandInk} />
            </View>
          ) : null}
          <Text style={estilos.tarjetaTexto} accessibilityRole="header">{titulo}</Text>
        </View>
      ) : null}
      {children}
    </View>
  );
}

export function Etiqueta({ children }: { children: ReactNode }) {
  return <Text style={estilos.etiqueta}>{children}</Text>;
}

/** Campo de texto con ícono, borde naranja al enfocar, ayuda y mensaje de error. */
export function Campo({ etiqueta, icono, error, ayuda, ...props }: { etiqueta: string; icono?: Icono; error?: string; ayuda?: string } & TextInputProps) {
  const [foco, setFoco] = useState(false);
  const multilinea = !!props.multiline;
  return (
    <View style={{ gap: 6 }}>
      <Text style={estilos.lbl}>{etiqueta}</Text>
      <View
        style={[
          estilos.campoCaja,
          multilinea && { alignItems: 'flex-start', minHeight: 92 },
          foco && estilos.campoFoco,
          !!error && { borderColor: C.bad, backgroundColor: '#fffafa' },
        ]}>
        {icono ? <Ionicons name={icono} size={19} color={foco ? C.brand : '#9a8f84'} style={multilinea ? { marginTop: 13 } : undefined} /> : null}
        <TextInput
          placeholderTextColor="#a99f94"
          accessibilityLabel={etiqueta}
          {...props}
          onFocus={(e) => {
            setFoco(true);
            props.onFocus?.(e);
          }}
          onBlur={(e) => {
            setFoco(false);
            props.onBlur?.(e);
          }}
          style={[estilos.campoTexto, multilinea && { textAlignVertical: 'top', paddingTop: 13, minHeight: 90 }, props.style]}
        />
      </View>
      {error ? <Text style={{ color: C.bad, fontSize: 12.5, fontWeight: '600' }}>{error}</Text> : ayuda ? <Text style={{ color: C.muted, fontSize: 12.5 }}>{ayuda}</Text> : null}
    </View>
  );
}

type Variante = 'primario' | 'secundario' | 'enlace' | 'peligro' | 'oscuro';

export function Boton({
  texto,
  onPress,
  variante = 'primario',
  deshabilitado,
  cargando,
  pequeno,
  icono,
  brillo,
  estilo,
}: {
  texto: string;
  onPress: () => void;
  variante?: Variante;
  deshabilitado?: boolean;
  cargando?: boolean;
  pequeno?: boolean;
  icono?: Icono;
  /** Destello que cruza el botón de vez en cuando (como en el login web). */
  brillo?: boolean;
  estilo?: ViewStyle;
}) {
  const [ancho, setAncho] = useState(0);
  const inactivo = deshabilitado || cargando;
  const colores: Record<Variante, { fondo: string; texto: string; borde: string }> = {
    primario: { fondo: C.brand, texto: C.ink, borde: C.brand },
    secundario: { fondo: C.white, texto: C.fg, borde: '#e0d8cd' },
    enlace: { fondo: 'transparent', texto: C.brandInk, borde: 'transparent' },
    peligro: { fondo: C.white, texto: C.bad, borde: '#e8cfca' },
    oscuro: { fondo: 'rgba(255,255,255,0.06)', texto: C.white, borde: '#5a5047' },
  };
  const c = colores[variante];
  const contenido = cargando ? (
    <ActivityIndicator color={c.texto} />
  ) : (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
      {icono ? <Ionicons name={icono} size={pequeno ? 16 : 19} color={c.texto} /> : null}
      <Text style={[estilos.botonTexto, { color: c.texto }, pequeno && { fontSize: 13.5 }, variante === 'primario' && { fontWeight: '800' }]}>{texto}</Text>
    </View>
  );
  return (
    <Pressable
      onPress={() => {
        Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => undefined);
        onPress();
      }}
      disabled={inactivo}
      onLayout={(e) => setAncho(e.nativeEvent.layout.width)}
      accessibilityRole="button"
      accessibilityState={{ disabled: !!inactivo, busy: !!cargando }}
      style={({ pressed }) => [
        estilos.boton,
        { backgroundColor: c.fondo, borderColor: c.borde, opacity: inactivo ? 0.5 : 1, transform: [{ scale: pressed ? 0.97 : 1 }] },
        variante === 'primario' && !inactivo && sombra(6, 0.28, 12, '#ff8000'),
        pequeno && { paddingVertical: 9, paddingHorizontal: 14, borderRadius: 11 },
        variante === 'enlace' && { paddingHorizontal: 0, alignItems: 'flex-start' },
        estilo,
      ]}>
      {variante === 'primario' ? (
        <LinearGradient colors={[C.brandLight, C.brand]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
      ) : null}
      {brillo && variante === 'primario' && ancho > 0 ? <Destello ancho={ancho} /> : null}
      {contenido}
    </Pressable>
  );
}

/** Opción de selección múltiple: se rellena de naranja y muestra una palomita al activarse. */
export function Chip({ texto, activo, onPress, icono }: { texto: string; activo?: boolean; onPress?: () => void; icono?: Icono }) {
  return (
    <Pressable
      onPress={() => {
        Haptics.selectionAsync().catch(() => undefined);
        onPress?.();
      }}
      accessibilityRole="button"
      accessibilityState={{ selected: !!activo }}
      style={({ pressed }) => [estilos.chip, activo && { backgroundColor: C.brand, borderColor: C.brand }, pressed && { opacity: 0.8 }]}>
      {activo ? <Ionicons name="checkmark" size={15} color={C.ink} /> : icono ? <Ionicons name={icono} size={15} color={C.brandInk} /> : null}
      <Text style={[estilos.chipTexto, activo && { color: C.ink, fontWeight: '800' }]}>{texto}</Text>
    </Pressable>
  );
}

/** Tarjeta destacada: degradado oscuro con un destello naranja, para el dato principal de la pantalla. */
export function Heroe({ children }: { children: ReactNode }) {
  return (
    <View style={[estilos.heroe, sombra(8, 0.25, 16, '#000000')]}>
      <LinearGradient colors={[C.ink, C.inkSoft]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
      <View style={{ position: 'absolute', right: -30, top: -40, width: 140, height: 140, borderRadius: 70, backgroundColor: C.brand, opacity: 0.22 }} />
      <View style={{ position: 'absolute', right: 52, bottom: -34, width: 70, height: 70, borderRadius: 35, backgroundColor: C.brand, opacity: 0.12 }} />
      {children}
    </View>
  );
}

/** Fila con interruptor dentro de una tarjeta. */
export function FilaSwitch({ texto, detalle, valor, onCambio, icono }: { texto: string; detalle?: string; valor: boolean; onCambio: (v: boolean) => void; icono?: Icono }) {
  return (
    <Pressable onPress={() => onCambio(!valor)} accessibilityRole="switch" accessibilityState={{ checked: valor }} style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
      {icono ? (
        <View style={estilos.iconoRedondo}>
          <Ionicons name={icono} size={16} color={C.brandInk} />
        </View>
      ) : null}
      <View style={{ flex: 1 }}>
        <Text style={{ color: C.fg, fontSize: 14.5, fontWeight: '600' }}>{texto}</Text>
        {detalle ? <Text style={{ color: C.muted, fontSize: 12.5 }}>{detalle}</Text> : null}
      </View>
      <Switch value={valor} onValueChange={onCambio} trackColor={{ true: C.brand, false: '#cfc7bd' }} thumbColor={C.white} ios_backgroundColor="#cfc7bd" />
    </Pressable>
  );
}

export type Tono = 'ok' | 'warn' | 'bad' | 'mute';

export function Pastilla({ texto, tono = 'mute' }: { texto: string; tono?: Tono }) {
  const t = {
    ok: { f: C.okSoft, c: C.ok },
    warn: { f: C.warnSoft, c: C.warn },
    bad: { f: C.badSoft, c: C.bad },
    mute: { f: C.mute, c: '#5e5650' },
  }[tono];
  return (
    <View style={{ backgroundColor: t.f, borderRadius: R.pill, paddingHorizontal: 10, paddingVertical: 3, alignSelf: 'flex-start' }}>
      <Text style={{ color: t.c, fontSize: 11.5, fontWeight: '700' }}>{texto}</Text>
    </View>
  );
}

export function Barra({ pct, color = C.brand, fondo = C.mute }: { pct: number; color?: string; fondo?: string }) {
  return (
    <View style={{ height: 10, backgroundColor: fondo, borderRadius: R.pill, overflow: 'hidden' }} accessibilityRole="progressbar" accessibilityValue={{ min: 0, max: 100, now: Math.round(pct) }}>
      <View style={{ width: `${Math.max(0, Math.min(100, pct))}%`, height: '100%', backgroundColor: color, borderRadius: R.pill }} />
    </View>
  );
}

/** Aviso fijo bajo el encabezado (sesión vencida, sin conexión, actualizar la app…). */
export function Aviso({ tono, children }: { tono: 'warn' | 'bad' | 'ok'; children: ReactNode }) {
  const t = { warn: [C.warnSoft, C.warn, 'alert-circle'], bad: [C.badSoft, C.bad, 'warning'], ok: [C.okSoft, C.ok, 'checkmark-circle'] }[tono] as [string, string, Icono];
  return (
    <View style={{ backgroundColor: t[0], paddingHorizontal: 16, paddingVertical: 9, flexDirection: 'row', gap: 8, alignItems: 'center' }}>
      <Ionicons name={t[2]} size={16} color={t[1]} />
      <Text style={{ color: t[1], fontSize: 13, fontWeight: '600', flex: 1 }}>{children}</Text>
    </View>
  );
}

export const estilos = StyleSheet.create({
  pantalla: { flex: 1, backgroundColor: C.bg },
  barra: {
    backgroundColor: C.ink,
    paddingHorizontal: 18,
    paddingBottom: 20,
    borderBottomLeftRadius: 28,
    borderBottomRightRadius: 28,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    overflow: 'hidden',
    zIndex: 2,
  },
  destelloFondo: { position: 'absolute', right: -34, top: -44, width: 150, height: 150, borderRadius: 75, backgroundColor: C.brand, opacity: 0.2 },
  destelloFondo2: { position: 'absolute', right: 46, top: -20, width: 56, height: 56, borderRadius: 28, backgroundColor: C.brand, opacity: 0.12 },
  subtitulo: { color: '#f3b777', fontSize: 12, fontWeight: '700', letterSpacing: 0.8, textTransform: 'uppercase', marginBottom: 2 },
  titulo: { color: C.white, fontSize: 24, fontWeight: '800', letterSpacing: -0.3 },
  cuerpo: { padding: 16, gap: 14 },
  tarjeta: { backgroundColor: C.card, borderRadius: 18, padding: 16, gap: 12, ...sombra(4, 0.07, 14) },
  heroe: { borderRadius: 24, padding: 18, gap: 8, overflow: 'hidden' },
  tarjetaTitulo: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  tarjetaTexto: { fontSize: 15.5, fontWeight: '800', color: C.fg, flex: 1 },
  iconoRedondo: { width: 30, height: 30, borderRadius: 15, backgroundColor: C.brandSoft, alignItems: 'center', justifyContent: 'center' },
  etiqueta: { fontSize: 11.5, fontWeight: '700', letterSpacing: 1, textTransform: 'uppercase', color: C.muted },
  lbl: { fontSize: 13, fontWeight: '700', color: C.fg },
  campoCaja: { flexDirection: 'row', alignItems: 'center', gap: 10, borderWidth: 1.5, borderColor: '#e6dfd5', borderRadius: 14, paddingHorizontal: 14, backgroundColor: '#fbfaf8' },
  campoFoco: { borderColor: C.brand, backgroundColor: C.white, ...sombra(3, 0.18, 8, '#ff8000') },
  campoTexto: { flex: 1, paddingVertical: 13, fontSize: 16, color: C.fg },
  boton: { borderWidth: 1.5, borderRadius: 14, paddingVertical: 15, paddingHorizontal: 16, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  botonTexto: { fontSize: 16, fontWeight: '700' },
  chip: { flexDirection: 'row', alignItems: 'center', gap: 5, borderWidth: 1.5, borderColor: '#e0d8cd', backgroundColor: C.white, borderRadius: R.pill, paddingVertical: 8, paddingHorizontal: 14 },
  chipTexto: { fontSize: 14, fontWeight: '600', color: C.fg },
});
