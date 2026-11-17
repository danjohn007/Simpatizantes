import { RefreshControl, ScrollView, Text, View } from 'react-native';
import { useState } from 'react';

import { AvisosEquipo } from '@/components/avisos';
import { Barra, Heroe, Pantalla, Pastilla, Tarjeta } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { useCola } from '@/lib/hooks';
import { C } from '@/lib/theme';

function fecha(d: string) {
  const [a, m, dia] = d.split('-');
  return `${dia}/${m}/${a}`;
}

export default function Metas() {
  const { me, refrescarMe } = useAuth();
  const { pendientes } = useCola();
  const [recargando, setRecargando] = useState(false);

  async function recargar() {
    setRecargando(true);
    await refrescarMe();
    setRecargando(false);
  }

  const metas = me?.metas ?? [];
  const zonas = me?.zonas ?? [];

  return (
    <Pantalla titulo="Mi avance" banner={<AvisosEquipo />} scroll={false}>
      <ScrollView contentContainerStyle={{ gap: 12, paddingBottom: 28 }} refreshControl={<RefreshControl refreshing={recargando} onRefresh={recargar} tintColor={C.brand} />}>
        {metas.length === 0 ? (
          <Tarjeta>
            <Text style={{ fontWeight: '700', color: C.fg }}>Sin metas vigentes</Text>
            <Text style={{ color: C.muted }}>Cuando su coordinador le asigne una meta, aparecerá aquí. Deslice hacia abajo para actualizar.</Text>
          </Tarjeta>
        ) : (
          metas.map((m) => {
            const pct = Math.min(100, Math.round((m.registrados * 100) / Math.max(1, m.objetivo)));
            return (
              <Heroe key={m.id}>
                <Text style={{ color: '#e6dccf', fontSize: 13 }}>
                  Su meta del {fecha(m.desde)} al {fecha(m.hasta)}
                  {m.seccion ? ` · sección ${m.seccion}` : ''}
                </Text>
                <Text style={{ color: C.white, fontSize: 24, fontWeight: '800' }}>{m.registrados} de {m.objetivo} registros</Text>
                <Barra pct={pct} color={pct >= 100 ? '#6fc395' : C.brand} fondo="#3a332c" />
                <Text style={{ color: '#e6dccf', fontSize: 13 }}>
                  {pct}% completado · {m.validados} validados{pendientes ? ` · ${pendientes} por enviar` : ''}
                </Text>
              </Heroe>
            );
          })
        )}

        <Tarjeta titulo="Mis secciones" icono="map-outline">
          {zonas.length ? (
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
              {zonas.map((z) => (
                <Pastilla key={z} texto={z} />
              ))}
            </View>
          ) : (
            <Text style={{ color: C.muted }}>Sin secciones asignadas: puede capturar en cualquier sección.</Text>
          )}
          <Text style={{ color: C.muted, fontSize: 13 }}>Si registra fuera de sus secciones, la app le avisa.</Text>
        </Tarjeta>
      </ScrollView>
    </Pantalla>
  );
}
