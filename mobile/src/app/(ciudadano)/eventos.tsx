import { useMemo, useState } from 'react';
import { RefreshControl, ScrollView, Text, View } from 'react-native';

import { AvisosCiudadano } from '@/components/avisos-ciudadano';
import { Boton, Pantalla, Pastilla, Tarjeta } from '@/components/ui';
import { type Aviso, type Evento, invalidar, mensajeError, partesFecha, useCiudadanoApi, useDatos } from '@/lib/ciudadano';
import { C } from '@/lib/theme';
import { useToast } from '@/lib/toast';

export default function Eventos() {
  const api = useCiudadanoApi();
  const toast = useToast();
  const cargar = useMemo(() => () => api.eventos(), [api]);
  const { datos, cargando, error, recargar, setDatos } = useDatos<{ eventos: Evento[]; avisos: Aviso[] }>('c_eventos', cargar, { eventos: [], avisos: [] });
  const [ocupado, setOcupado] = useState<number | null>(null);

  async function alternar(e: Evento) {
    setOcupado(e.id);
    try {
      const asisto = await api.asistencia(e.id);
      setDatos({ ...datos, eventos: datos.eventos.map((x) => (x.id === e.id ? { ...x, asisto, asistentes: x.asistentes + (asisto ? 1 : -1) } : x)) });
      invalidar('c_eventos');
    } catch (err) {
      toast(mensajeError(err));
    } finally {
      setOcupado(null);
    }
  }

  return (
    <Pantalla titulo="Eventos de mi zona" banner={<AvisosCiudadano />} scroll={false}>
      <ScrollView contentContainerStyle={{ gap: 14, paddingBottom: 130 }} showsVerticalScrollIndicator={false} refreshControl={<RefreshControl refreshing={cargando} onRefresh={recargar} tintColor={C.brand} />}>
        {datos.eventos.length === 0 ? (
          <Tarjeta>
            <Text style={{ fontWeight: '700', color: C.fg, textAlign: 'center' }}>{cargando ? 'Cargando…' : 'No hay eventos por ahora'}</Text>
            <Text style={{ color: C.muted, textAlign: 'center' }}>{error || 'Cuando publiquen uno en tu zona, aparecerá aquí.'}</Text>
          </Tarjeta>
        ) : null}
        {datos.eventos.map((e) => {
          const f = partesFecha(e.inicio);
          return (
            <Tarjeta key={e.id}>
              <View style={{ flexDirection: 'row', gap: 12 }}>
                <View style={{ width: 52, backgroundColor: C.ink, borderRadius: 12, alignItems: 'center', paddingVertical: 6, alignSelf: 'flex-start' }}>
                  <Text style={{ color: C.brand, fontSize: 20, fontWeight: '800' }}>{f.dia}</Text>
                  <Text style={{ color: C.white, fontSize: 10.5, textTransform: 'uppercase' }}>{f.mes}</Text>
                </View>
                <View style={{ flex: 1, gap: 4 }}>
                  <Text style={{ fontWeight: '800', color: C.fg }}>{e.titulo}</Text>
                  <Text style={{ color: C.muted }}>{e.lugar} · {f.hora}</Text>
                  {e.descripcion ? <Text style={{ color: C.fg, fontSize: 13.5 }}>{e.descripcion}</Text> : null}
                  <Pastilla texto={e.colonia ?? e.municipio ?? 'Todos'} />
                </View>
              </View>
              <Boton texto={e.asisto ? '✓ Asistiré' : 'Asistiré'} variante={e.asisto ? 'primario' : 'secundario'} cargando={ocupado === e.id} onPress={() => alternar(e)} />
            </Tarjeta>
          );
        })}
      </ScrollView>
    </Pantalla>
  );
}
