import { router } from 'expo-router';
import { RefreshControl, ScrollView, Text, View } from 'react-native';
import { useMemo } from 'react';

import { AvisosCiudadano } from '@/components/avisos-ciudadano';
import { Boton, Heroe, Pantalla, Tarjeta } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { type Aviso, type Evento, type Reporte, partesFecha, useCiudadanoApi, useDatos } from '@/lib/ciudadano';
import { C } from '@/lib/theme';

export default function Inicio() {
  const { ciudadano } = useAuth();
  const api = useCiudadanoApi();
  const cargarEventos = useMemo(() => () => api.eventos(), [api]);
  const cargarReportes = useMemo(() => () => api.reportes(), [api]);
  const eventos = useDatos<{ eventos: Evento[]; avisos: Aviso[] }>('c_eventos', cargarEventos, { eventos: [], avisos: [] });
  const reportes = useDatos<Reporte[]>('c_reportes', cargarReportes, []);

  const proximo = eventos.datos.eventos[0];
  const atendido = reportes.datos.find((r) => r.estado === 'atendido' || r.estado === 'cerrado');
  const f = proximo ? partesFecha(proximo.inicio) : null;

  async function recargar() {
    await Promise.all([eventos.recargar(), reportes.recargar()]);
  }

  return (
    <Pantalla titulo={`Hola, ${ciudadano?.nombre.split(' ')[0] ?? ''}`} subtitulo={ciudadano?.colonia ?? 'Tu colonia'} banner={<AvisosCiudadano />} scroll={false}>
      <ScrollView contentContainerStyle={{ gap: 14, paddingBottom: 130 }} showsVerticalScrollIndicator={false} refreshControl={<RefreshControl refreshing={eventos.cargando && reportes.cargando} onRefresh={recargar} tintColor={C.brand} />}>
        <Heroe>
          <Text style={{ color: '#e6dccf', fontSize: 13 }}>Tu colonia</Text>
          <Text style={{ color: C.white, fontSize: 21, fontWeight: '800' }}>¿Algo no funciona en tu colonia? Avísanos.</Text>
          <Boton texto="Reportar un problema" icono="megaphone-outline" onPress={() => router.push('/reportar')} brillo />
        </Heroe>

        <Tarjeta titulo="Próximo evento en tu zona" icono="calendar-outline">
          {proximo && f ? (
            <View style={{ flexDirection: 'row', gap: 12 }}>
              <View style={{ width: 52, backgroundColor: C.ink, borderRadius: 12, alignItems: 'center', paddingVertical: 6 }}>
                <Text style={{ color: C.brand, fontSize: 20, fontWeight: '800' }}>{f.dia}</Text>
                <Text style={{ color: C.white, fontSize: 10.5, textTransform: 'uppercase' }}>{f.mes}</Text>
              </View>
              <View style={{ flex: 1 }}>
                <Text style={{ fontWeight: '800', color: C.fg }}>{proximo.titulo}</Text>
                <Text style={{ color: C.muted }}>{proximo.lugar} · {f.hora}</Text>
              </View>
            </View>
          ) : (
            <Text style={{ color: C.muted }}>{eventos.cargando ? 'Buscando eventos…' : 'Por ahora no hay eventos en tu zona. Cuando publiquen uno, lo verás aquí.'}</Text>
          )}
          <Boton texto="Ver todos los eventos →" variante="enlace" onPress={() => router.push('/eventos')} />
        </Tarjeta>

        <Tarjeta titulo="Avisos" icono="notifications-outline">
          <View style={{ gap: 12 }}>
            {eventos.datos.avisos.map((a) => (
              <View key={a.id}>
                <Text style={{ fontWeight: '700', color: C.fg }}>{a.titulo}</Text>
                <Text style={{ color: C.muted }}>{a.texto}</Text>
              </View>
            ))}
            {atendido ? (
              <View>
                <Text style={{ fontWeight: '700', color: C.fg }}>Tu reporte {atendido.folio} fue atendido</Text>
                <Text style={{ color: C.muted }}>Gracias por avisar. Puedes ver la respuesta en Mis reportes.</Text>
              </View>
            ) : null}
            {!eventos.datos.avisos.length && !atendido ? <Text style={{ color: C.muted }}>No hay avisos por ahora.</Text> : null}
          </View>
        </Tarjeta>
        {eventos.error && !eventos.datos.eventos.length ? <Text style={{ color: C.muted, textAlign: 'center', fontSize: 12.5 }}>{eventos.error}</Text> : null}
      </ScrollView>
    </Pantalla>
  );
}
