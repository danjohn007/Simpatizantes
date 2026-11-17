import { router } from 'expo-router';
import { Alert, Text, View } from 'react-native';

import { AvisosEquipo } from '@/components/avisos';
import { Boton, Pantalla, Pastilla, Tarjeta } from '@/components/ui';
import { borrarItem, reintentar } from '@/lib/db';
import { useCola, useOnline } from '@/lib/hooks';
import { avisarCambios } from '@/lib/sync';
import { useSync } from '@/lib/sync-context';
import { C } from '@/lib/theme';

export default function Pendientes() {
  const { cola } = useCola();
  const enLinea = useOnline();
  const { sincronizarAhora, sincronizando } = useSync();

  function descartar(uuid: string, nombre: string) {
    Alert.alert('¿Descartar este registro?', `${nombre} no se enviará.`, [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Descartar',
        style: 'destructive',
        onPress: async () => {
          await borrarItem(uuid);
          avisarCambios();
        },
      },
    ]);
  }

  return (
    <Pantalla titulo="Por enviar" banner={<AvisosEquipo />}>
      {cola.length === 0 ? (
        <Tarjeta>
          <Text style={{ fontWeight: '700', color: C.fg, textAlign: 'center' }}>Nada pendiente</Text>
          <Text style={{ color: C.muted, textAlign: 'center' }}>Todo lo capturado ya llegó al servidor.</Text>
        </Tarjeta>
      ) : (
        <>
          <Boton texto={sincronizando ? 'Enviando…' : 'Enviar ahora'} onPress={sincronizarAhora} cargando={sincronizando} deshabilitado={!enLinea} />
          {cola.map((it) => (
            <Tarjeta key={it.uuid}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
                <Text style={{ fontWeight: '700', color: C.fg, flex: 1 }} numberOfLines={1}>{it.payload.nombre_completo}</Text>
                <Pastilla texto={it.estado === 'error' ? 'Requiere corrección' : 'Esperando envío'} tono={it.estado === 'error' ? 'bad' : 'warn'} />
              </View>
              <Text style={{ color: C.muted }}>
                Sección {it.payload.seccion_electoral} · {new Date(it.payload.capturado_en).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' })}
              </Text>
              {it.estado === 'error' ? <Text style={{ color: C.bad, fontSize: 13.5 }}>{it.mensaje}</Text> : null}
              <View style={{ flexDirection: 'row', gap: 8 }}>
                {it.estado === 'error' ? (
                  <>
                    <Boton texto="Corregir" pequeno variante="secundario" onPress={() => router.push({ pathname: '/', params: { corregir: it.uuid } })} />
                    <Boton
                      texto="Reintentar"
                      pequeno
                      variante="secundario"
                      onPress={async () => {
                        await reintentar(it.uuid);
                        avisarCambios();
                        sincronizarAhora();
                      }}
                    />
                  </>
                ) : null}
                <Boton texto="Descartar" pequeno variante="peligro" onPress={() => descartar(it.uuid, it.payload.nombre_completo)} />
              </View>
            </Tarjeta>
          ))}
        </>
      )}
    </Pantalla>
  );
}
