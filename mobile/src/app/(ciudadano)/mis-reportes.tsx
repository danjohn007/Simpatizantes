import { router } from 'expo-router';
import { useMemo } from 'react';
import { Pressable, RefreshControl, ScrollView, Text, View } from 'react-native';

import { AvisosCiudadano } from '@/components/avisos-ciudadano';
import { Pantalla, Pastilla, Tarjeta } from '@/components/ui';
import { ESTADOS, leerAnonimas, type Reporte, tonoEstado, useCiudadanoApi, useDatos } from '@/lib/ciudadano';
import { C } from '@/lib/theme';

export default function MisReportes() {
  const api = useCiudadanoApi();
  const cargar = useMemo(() => () => api.reportes(), [api]);
  const { datos, cargando, error, recargar } = useDatos<Reporte[]>('c_reportes', cargar, []);
  // Las denuncias anónimas no pertenecen a la cuenta: se consultan con su folio y código guardados en este teléfono
  const anonimas = leerAnonimas();

  return (
    <Pantalla titulo="Mis reportes" subtitulo="Seguimiento" banner={<AvisosCiudadano />} scroll={false}>
      <ScrollView contentContainerStyle={{ gap: 14, paddingBottom: 130 }} showsVerticalScrollIndicator={false} refreshControl={<RefreshControl refreshing={cargando} onRefresh={recargar} tintColor={C.brand} />}>
        {datos.length === 0 ? (
          <Tarjeta>
            <Text style={{ fontWeight: '700', color: C.fg, textAlign: 'center' }}>{cargando ? 'Cargando…' : 'Aún no has hecho reportes'}</Text>
            <Text style={{ color: C.muted, textAlign: 'center' }}>{error || 'Cuando reportes algo, lo verás aquí con su folio y su estado.'}</Text>
          </Tarjeta>
        ) : (
          <Tarjeta>
            {datos.map((r, i) => (
              <Pressable
                key={r.folio}
                onPress={() => router.push({ pathname: '/reporte/[folio]', params: { folio: r.folio } })}
                accessibilityRole="button"
                style={{ paddingVertical: 10, gap: 4, borderTopWidth: i ? 1 : 0, borderTopColor: C.line }}>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
                  <Text style={{ fontWeight: '800', color: C.fg, flex: 1 }}>{r.categoria_nombre} · {r.folio}</Text>
                  <Pastilla texto={ESTADOS[r.estado]} tono={tonoEstado(r.estado)} />
                </View>
                <Text style={{ color: C.muted }} numberOfLines={2}>{r.descripcion}</Text>
              </Pressable>
            ))}
          </Tarjeta>
        )}

        {anonimas.length > 0 ? (
          <Tarjeta titulo="Mis denuncias anónimas" icono="eye-off-outline">
            {anonimas.map((a, i) => (
              <Pressable
                key={a.folio}
                onPress={() => router.push({ pathname: '/reporte/[folio]', params: { folio: a.folio, codigo: a.codigo } })}
                accessibilityRole="button"
                style={{ paddingVertical: 8, borderTopWidth: i ? 1 : 0, borderTopColor: C.line }}>
                <Text style={{ fontWeight: '800', color: C.fg }}>{a.folio}</Text>
                <Text style={{ color: C.muted, fontSize: 12.5 }}>Toca para ver el avance</Text>
              </Pressable>
            ))}
          </Tarjeta>
        ) : null}
      </ScrollView>
    </Pantalla>
  );
}
