import { useEffect, useState } from 'react';
import { Alert, Text, View } from 'react-native';

import { AvisosCiudadano } from '@/components/avisos-ciudadano';
import { Boton, Pantalla, Tarjeta } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { APP_VERSION } from '@/lib/config';
import { mensajeError, useCiudadanoApi } from '@/lib/ciudadano';
import { C } from '@/lib/theme';
import { useToast } from '@/lib/toast';

export default function Cuenta() {
  const { ciudadano, salir, eliminarCuenta } = useAuth();
  const api = useCiudadanoApi();
  const toast = useToast();
  const [sumado, setSumado] = useState(false);
  const [enviando, setEnviando] = useState(false);
  const iniciales = (ciudadano?.nombre ?? '?').split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '').join('');

  useEffect(() => {
    let vivo = true;
    api.perfil().then((p) => vivo && setSumado(p.quiere_sumarse)).catch(() => {});
    return () => {
      vivo = false;
    };
  }, [api]);

  async function sumarme() {
    setEnviando(true);
    try {
      await api.sumarme();
      setSumado(true);
      toast('Solicitud enviada. Un coordinador la revisará.');
    } catch (e) {
      toast(mensajeError(e));
    } finally {
      setEnviando(false);
    }
  }

  function eliminar() {
    Alert.alert('¿Eliminar tu cuenta?', 'Se borrarán tus datos personales del servidor y de este teléfono. Tus reportes se conservan sin tu nombre. Esta acción no se puede deshacer.', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Eliminar mi cuenta',
        style: 'destructive',
        onPress: async () => {
          const r = await eliminarCuenta();
          if (!r.ok) toast(r.error ?? 'No se pudo eliminar la cuenta.');
        },
      },
    ]);
  }

  return (
    <Pantalla titulo="Mi cuenta" banner={<AvisosCiudadano />}>
      <Tarjeta>
        <View style={{ flexDirection: 'row', gap: 12, alignItems: 'center' }}>
          <View style={{ width: 48, height: 48, borderRadius: 24, backgroundColor: C.ink, alignItems: 'center', justifyContent: 'center' }}>
            <Text style={{ color: C.brand, fontWeight: '800', fontSize: 17 }}>{iniciales}</Text>
          </View>
          <View style={{ flex: 1 }}>
            <Text style={{ fontWeight: '800', color: C.fg, fontSize: 17 }}>{ciudadano?.nombre}</Text>
            <Text style={{ color: C.muted }}>{ciudadano?.correo}</Text>
            {ciudadano?.colonia || ciudadano?.municipio ? <Text style={{ color: C.muted }}>{[ciudadano?.colonia, ciudadano?.municipio].filter(Boolean).join(', ')}</Text> : null}
          </View>
        </View>
      </Tarjeta>

      <Tarjeta titulo="Participación" icono="people-outline">
        <Text style={{ color: C.fg, fontSize: 14 }}>Si quieres, puedes sumarte como simpatizante. Es opcional y solo ocurre si lo pides.</Text>
        <Boton texto={sumado ? '✓ Solicitud enviada' : 'Quiero sumarme como simpatizante'} variante="secundario" deshabilitado={sumado} cargando={enviando} onPress={sumarme} />
      </Tarjeta>

      <Tarjeta titulo="Mis datos" icono="lock-closed-outline">
        <Boton texto="Eliminar mi cuenta" variante="peligro" onPress={eliminar} />
      </Tarjeta>

      <Boton texto="Cerrar sesión" variante="secundario" onPress={() => salir()} />
      <Text style={{ color: C.muted, fontSize: 12, textAlign: 'center' }}>Versión {APP_VERSION}</Text>
    </Pantalla>
  );
}
