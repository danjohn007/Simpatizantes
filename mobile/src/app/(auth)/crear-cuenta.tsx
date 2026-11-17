import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useState } from 'react';
import { Dimensions, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Entrada } from '@/components/anim';
import { Cartel } from '@/components/cartel';
import { Boton, Campo, FilaSwitch } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { C, sombra } from '@/lib/theme';

/** Alta de ciudadano: la cuenta se crea en el servidor y la sesión queda iniciada. */
export default function CrearCuenta() {
  const { registrarCiudadano } = useAuth();
  const insets = useSafeAreaInsets();
  const [{ height }] = useState(() => Dimensions.get('window'));
  const alto = Math.min(290, height * 0.34);
  const [f, setF] = useState({ nombre: '', correo: '', password: '', municipio: '', colonia: '' });
  const [acepto, setAcepto] = useState(false);
  const [errores, setErrores] = useState<{ nombre?: string; correo?: string; password?: string; acepto?: string; general?: string }>({});
  const [cargando, setCargando] = useState(false);
  const campo = (k: keyof typeof f) => (v: string) => setF((p) => ({ ...p, [k]: v }));

  async function crear() {
    const e: typeof errores = {};
    if (f.nombre.trim().length < 2) e.nombre = 'Escriba su nombre.';
    if (!/^\S+@\S+\.\S+$/.test(f.correo.trim())) e.correo = 'Escriba un correo válido.';
    if (f.password.length < 8) e.password = 'La contraseña debe tener al menos 8 caracteres.';
    if (!acepto) e.acepto = 'Debe aceptar el aviso de privacidad para continuar.';
    setErrores(e);
    if (Object.keys(e).length > 0) return;

    setCargando(true);
    const r = await registrarCiudadano({ ...f, nombre: f.nombre.trim(), correo: f.correo.trim() });
    setCargando(false);
    if (!r.ok) setErrores({ general: r.error });
    // Si salió bien, el cambio de estado abre solo la pantalla de la ciudadanía
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: C.ink }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <Cartel alto={alto}>
        <Pressable onPress={() => router.back()} accessibilityRole="button" accessibilityLabel="Volver" style={[estilos.volver, { top: insets.top + 8 }]}>
          <Ionicons name="chevron-back" size={22} color={C.white} />
        </Pressable>
      </Cartel>

      <View style={estilos.hoja}>
        <ScrollView contentContainerStyle={{ padding: 24, paddingBottom: insets.bottom + 28, gap: 14 }} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
          <Entrada>
            <View style={{ gap: 4 }}>
              <Text style={estilos.titulo} accessibilityRole="header">Crea tu cuenta</Text>
              <Text style={estilos.lead}>Para reportar problemas de tu colonia y enterarte de los eventos de tu zona.</Text>
            </View>
          </Entrada>
          <Entrada retraso={80}>
            <Campo etiqueta="Nombre" icono="person" value={f.nombre} onChangeText={campo('nombre')} placeholder="Tu nombre" autoComplete="off" error={errores.nombre} />
          </Entrada>
          <Entrada retraso={140}>
            <Campo etiqueta="Correo electrónico" icono="mail" value={f.correo} onChangeText={campo('correo')} placeholder="tu@correo.com" keyboardType="email-address" autoCapitalize="none" autoComplete="off" autoCorrect={false} error={errores.correo} />
          </Entrada>
          <Entrada retraso={200}>
            <Campo etiqueta="Contraseña" icono="lock-closed" value={f.password} onChangeText={campo('password')} placeholder="Mínimo 8 caracteres" secureTextEntry autoComplete="off" textContentType="none" error={errores.password} />
          </Entrada>
          <Entrada retraso={260}>
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <View style={{ flex: 1 }}>
                <Campo etiqueta="Municipio" icono="business" value={f.municipio} onChangeText={campo('municipio')} placeholder="Querétaro" />
              </View>
              <View style={{ flex: 1 }}>
                <Campo etiqueta="Colonia" icono="home" value={f.colonia} onChangeText={campo('colonia')} placeholder="Tu colonia" />
              </View>
            </View>
            <Text style={{ color: C.muted, fontSize: 12.5, marginTop: 4 }}>Con esto te mostramos los eventos y avisos de tu zona.</Text>
          </Entrada>
          <Entrada retraso={320}>
            <View style={estilos.privacidad}>
              <FilaSwitch icono="shield-checkmark" texto="Acepto el aviso de privacidad" detalle="Mis datos solo se usan para atender mis reportes y avisarme de eventos." valor={acepto} onCambio={setAcepto} />
              {errores.acepto ? <Text style={{ color: C.bad, fontSize: 12.5, fontWeight: '600', marginTop: 8 }}>{errores.acepto}</Text> : null}
            </View>
          </Entrada>
          {errores.general ? (
            <View style={estilos.error} accessibilityLiveRegion="polite">
              <Ionicons name="alert-circle" size={18} color={C.bad} />
              <Text style={{ color: C.bad, fontSize: 14, flex: 1, fontWeight: '600' }}>{errores.general}</Text>
            </View>
          ) : null}
          <Entrada retraso={380}>
            <Boton texto="Crear mi cuenta" icono="checkmark-circle-outline" onPress={crear} cargando={cargando} brillo />
          </Entrada>
        </ScrollView>
      </View>
    </KeyboardAvoidingView>
  );
}

const estilos = StyleSheet.create({
  volver: { position: 'absolute', left: 16, width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(17,17,17,0.55)', alignItems: 'center', justifyContent: 'center' },
  hoja: { flex: 1, marginTop: -28, backgroundColor: C.paper, borderTopLeftRadius: 30, borderTopRightRadius: 30, overflow: 'hidden', ...sombra(-6, 0.25, 18, '#000000') },
  titulo: { fontSize: 28, fontWeight: '800', color: C.ink, letterSpacing: -0.5 },
  lead: { color: C.muted, fontSize: 15 },
  privacidad: { backgroundColor: C.white, borderRadius: 16, padding: 14, ...sombra(3, 0.06, 10) },
  error: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: C.badSoft, borderRadius: 12, padding: 12 },
});
