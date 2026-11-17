import { Alert, Text, View } from 'react-native';

import { AvisosEquipo } from '@/components/avisos';
import { Boton, Pantalla, Pastilla, Tarjeta } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { API_URL, APP_VERSION } from '@/lib/config';
import { useCola } from '@/lib/hooks';
import { C } from '@/lib/theme';

export default function Perfil() {
  const { usuario, salir } = useAuth();
  const { pendientes, conError } = useCola();
  const iniciales = (usuario?.nombre ?? '?').split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '').join('');
  const sinEnviar = pendientes + conError;

  function cerrar() {
    const aviso = sinEnviar
      ? `Hay ${sinEnviar} registro(s) sin enviar. Seguirán guardados en el teléfono y se enviarán cuando vuelva a entrar con esta misma cuenta.`
      : 'Se cerrará la sesión en este teléfono.';
    Alert.alert('¿Cerrar sesión?', aviso, [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Cerrar sesión', style: 'destructive', onPress: () => salir() },
    ]);
  }

  return (
    <Pantalla titulo="Perfil" banner={<AvisosEquipo />}>
      <Tarjeta>
        <View style={{ flexDirection: 'row', gap: 12, alignItems: 'center' }}>
          <View style={{ width: 48, height: 48, borderRadius: 24, backgroundColor: C.ink, alignItems: 'center', justifyContent: 'center' }}>
            <Text style={{ color: C.brand, fontWeight: '800', fontSize: 17 }}>{iniciales}</Text>
          </View>
          <View style={{ flex: 1 }}>
            <Text style={{ fontWeight: '800', color: C.fg, fontSize: 17 }}>{usuario?.nombre}</Text>
            <Text style={{ color: C.muted }}>
              {usuario?.rol ? usuario.rol.charAt(0).toUpperCase() + usuario.rol.slice(1) : ''}
              {usuario?.campana ? ` · ${usuario.campana}` : ''}
            </Text>
          </View>
        </View>
      </Tarjeta>

      <Tarjeta titulo="Estado" icono="pulse-outline">
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <Text style={{ color: C.fg }}>Registros por enviar</Text>
          <Pastilla texto={String(sinEnviar)} tono={sinEnviar ? 'warn' : 'ok'} />
        </View>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <Text style={{ color: C.fg }}>Sesión guardada de forma segura</Text>
          <Pastilla texto="Llavero del teléfono" tono="ok" />
        </View>
      </Tarjeta>

      <Boton texto="Cerrar sesión" variante="secundario" onPress={cerrar} />
      <Text style={{ color: C.muted, fontSize: 12, textAlign: 'center' }}>
        Versión {APP_VERSION} · {API_URL.replace(/^https?:\/\//, '')}
      </Text>
    </Pantalla>
  );
}
