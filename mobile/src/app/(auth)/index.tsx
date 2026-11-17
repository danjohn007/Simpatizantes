import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Dimensions, KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, View } from 'react-native';
import Animated, { useAnimatedStyle, useSharedValue, withDelay, withSequence, withTiming } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AnilloPulso, Entrada, Sacudida, SUAVE } from '@/components/anim';
import { Cartel } from '@/components/cartel';
import { Boton, Campo } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { APP_VERSION } from '@/lib/config';
import { C, sombra } from '@/lib/theme';

const DURACION = 1300; // mismo tiempo que la entrada del login web

export default function Entrada_() {
  const { login } = useAuth();
  const insets = useSafeAreaInsets();
  // Medidas fijas al abrir: si cambiaran al salir el teclado, la pantalla se reacomodaría y el teclado se cerraría
  const [{ width, height }] = useState(() => Dimensions.get('window'));
  const altoCartel = Math.min(width * 1.2, height * 0.46);
  const salto = height * 0.12;

  const [usuario, setUsuario] = useState('');
  const [password, setPassword] = useState('');
  const [verPassword, setVerPassword] = useState(false);
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState('');
  const [sacudir, setSacudir] = useState(0);

  // Entrada: el cartel baja desde arriba y el formulario sube desde abajo hasta unirse (como joinLeft / joinRight en la web)
  const cartel = useSharedValue(0);
  const formulario = useSharedValue(0);
  const costura = useSharedValue(0);
  useEffect(() => {
    cartel.value = withTiming(1, { duration: DURACION, easing: SUAVE });
    formulario.value = withDelay(120, withTiming(1, { duration: DURACION, easing: SUAVE }));
    costura.value = withDelay(1050, withSequence(withTiming(1, { duration: 450 }), withTiming(0, { duration: 650 })));
  }, [cartel, formulario, costura]);

  const estiloCartel = useAnimatedStyle(() => ({
    opacity: Math.min(1, cartel.value / 0.6),
    transform: [{ translateY: -salto * (1 - cartel.value) }, { scale: 0.94 + 0.06 * cartel.value }],
    borderBottomLeftRadius: 32 * (1 - cartel.value),
    borderBottomRightRadius: 32 * (1 - cartel.value),
  }));
  const estiloFormulario = useAnimatedStyle(() => ({
    opacity: Math.min(1, formulario.value / 0.6),
    transform: [{ translateY: salto * (1 - formulario.value) }, { scale: 0.94 + 0.06 * formulario.value }],
  }));
  const estiloImagen = useAnimatedStyle(() => ({ transform: [{ scale: 1.08 - 0.08 * cartel.value }] }));
  const estiloCostura = useAnimatedStyle(() => ({ opacity: costura.value, transform: [{ scaleX: 0.1 + 0.9 * costura.value }] }));

  async function entrar() {
    if (!usuario.trim() || !password) {
      setError('Escriba su usuario (o correo) y su contraseña.');
      setSacudir((n) => n + 1);
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning).catch(() => undefined);
      return;
    }
    setError('');
    setCargando(true);
    const r = await login(usuario, password);
    setCargando(false);
    if (!r.ok) {
      setError(r.error ?? 'No se pudo iniciar sesión.');
      setSacudir((n) => n + 1);
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error).catch(() => undefined);
    }
    // Si salió bien, el cambio de estado abre solo la pantalla del equipo
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: C.ink }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      {/* Cartel */}
      <Animated.View style={[estilos.cartelCaja, { height: altoCartel }, estiloCartel]}>
        <Cartel alto={altoCartel} estiloImagen={estiloImagen}>
          <View style={[estilos.marca, { bottom: 46 }]}>
            <Entrada retraso={1300} distancia={18}>
              <View style={estilos.marcaFila}>
                <View style={estilos.logoCaja}>
                  <AnilloPulso tamano={46} radio={13} />
                  <View style={estilos.logo}>
                    <Ionicons name="person-circle" size={26} color={C.brand} />
                  </View>
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={estilos.marcaNombre}>ID Simpatizantes</Text>
                  <Text style={estilos.marcaPie}>Acceso seguro para el equipo de campaña</Text>
                </View>
              </View>
            </Entrada>
          </View>
        </Cartel>
      </Animated.View>

      {/* Formulario: una hoja que sube y se une al cartel */}
      <Animated.View style={[estilos.hoja, { top: altoCartel - 30 }, estiloFormulario]}>
        <ScrollView
          contentContainerStyle={{ paddingHorizontal: 24, paddingTop: 24, paddingBottom: insets.bottom + 20, gap: 12 }}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}>
          <Entrada retraso={1000} distancia={22}>
            <View style={{ gap: 4 }}>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
                <View style={{ width: 28, height: 3, borderRadius: 2, backgroundColor: C.brand }} />
                <Text style={estilos.sobretitulo}>Bienvenido</Text>
              </View>
              <Text style={estilos.titulo} accessibilityRole="header">Inicia sesión</Text>
              <Text style={estilos.lead}>Entra para registrar simpatizantes y ver tu avance.</Text>
            </View>
          </Entrada>

          <Entrada retraso={1100}>
            <Campo
              etiqueta="Usuario o correo"
              icono="person"
              value={usuario}
              onChangeText={setUsuario}
              autoCapitalize="none"
              autoCorrect={false}
              textContentType="none"
              autoComplete="off"
              placeholder="Usuario del equipo o tu correo"
            />
          </Entrada>
          <Entrada retraso={1200}>
            <View>
              <Campo
                etiqueta="Contraseña"
                icono="lock-closed"
                value={password}
                onChangeText={setPassword}
                secureTextEntry={!verPassword}
                textContentType="none"
                autoComplete="off"
                placeholder="Ingrese su contraseña"
                onSubmitEditing={entrar}
              />
              <Text onPress={() => setVerPassword(!verPassword)} style={estilos.ver} accessibilityRole="button" accessibilityLabel={verPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}>
                {verPassword ? 'Ocultar' : 'Mostrar'}
              </Text>
            </View>
          </Entrada>

          {error ? (
            <Sacudida clave={sacudir}>
              <View style={estilos.error} accessibilityLiveRegion="polite">
                <Ionicons name="alert-circle" size={18} color={C.bad} />
                <Text style={{ color: C.bad, fontSize: 14, flex: 1, fontWeight: '600' }}>{error}</Text>
              </View>
            </Sacudida>
          ) : null}

          <Entrada retraso={1300}>
            <Boton texto="Entrar" icono="log-in-outline" onPress={entrar} cargando={cargando} brillo />
          </Entrada>
          <Entrada retraso={1400}>
            <View style={{ gap: 12 }}>
              <View style={estilos.separador}>
                <View style={estilos.linea} />
                <Text style={{ color: C.muted, fontSize: 12.5 }}>¿Eres vecino o vecina?</Text>
                <View style={estilos.linea} />
              </View>
              <Boton texto="Crear cuenta de ciudadano" icono="people-outline" variante="secundario" onPress={() => router.push('/crear-cuenta')} />
            </View>
          </Entrada>
          <Entrada retraso={1500} distancia={14}>
            <Text style={estilos.pie}>
              <Ionicons name="shield-checkmark" size={13} color={C.muted} /> Conexión segura · Versión {APP_VERSION}
            </Text>
          </Entrada>
        </ScrollView>
      </Animated.View>

      {/* La "costura": un destello naranja donde se unen las dos mitades */}
      <Animated.View pointerEvents="none" style={[estilos.costura, { top: altoCartel - 33 }, estiloCostura]}>
        <LinearGradient colors={['transparent', C.brand, 'transparent']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={{ flex: 1 }} />
      </Animated.View>
    </KeyboardAvoidingView>
  );
}

const estilos = StyleSheet.create({
  cartelCaja: { position: 'absolute', top: 0, left: 0, right: 0, overflow: 'hidden', backgroundColor: '#ff9100' },
  marca: { position: 'absolute', left: 22, right: 22 },
  marcaFila: { flexDirection: 'row', alignItems: 'center', gap: 14 },
  logoCaja: { width: 48, height: 48, alignItems: 'center', justifyContent: 'center' },
  logo: { width: 48, height: 48, borderRadius: 13, backgroundColor: C.ink, borderWidth: 2, borderColor: C.brand, alignItems: 'center', justifyContent: 'center' },
  marcaNombre: { color: C.white, fontWeight: '800', fontSize: 17 },
  marcaPie: { color: 'rgba(255,255,255,0.82)', fontSize: 12.5, marginTop: 1 },
  hoja: { position: 'absolute', left: 0, right: 0, bottom: 0, backgroundColor: C.paper, borderTopLeftRadius: 30, borderTopRightRadius: 30, overflow: 'hidden', ...sombra(-6, 0.25, 18, '#000000') },
  sobretitulo: { fontSize: 12.5, fontWeight: '800', letterSpacing: 2, color: C.brandInk, textTransform: 'uppercase' },
  titulo: { fontSize: 31, fontWeight: '800', color: C.ink, letterSpacing: -0.6 },
  lead: { color: C.muted, fontSize: 15 },
  ver: { position: 'absolute', right: 14, top: 36, color: C.brandInk, fontWeight: '800', fontSize: 13, paddingVertical: 8, paddingLeft: 8 },
  error: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: C.badSoft, borderRadius: 12, padding: 12 },
  separador: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  linea: { flex: 1, height: 1, backgroundColor: C.line },
  pie: { color: C.muted, fontSize: 12, textAlign: 'center' },
  costura: { position: 'absolute', left: 0, right: 0, height: 6, ...sombra(0, 0.9, 14, '#ff8000') },
});
