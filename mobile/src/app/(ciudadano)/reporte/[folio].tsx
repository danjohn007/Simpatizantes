import { Image } from 'expo-image';
import { router, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { ScrollView, Text, View } from 'react-native';

import { AvisosCiudadano } from '@/components/avisos-ciudadano';
import { Boton, Pantalla, Pastilla, Tarjeta } from '@/components/ui';
import { type DetalleReporte, ESTADOS, fechaLarga, fotoFuente, mensajeError, tonoEstado, useCiudadanoApi } from '@/lib/ciudadano';
import { C } from '@/lib/theme';

type Vista = Omit<DetalleReporte, 'id' | 'tipo' | 'categoria' | 'descripcion' | 'anonimo' | 'fotos' | 'categoria_nombre'> & Partial<Pick<DetalleReporte, 'id' | 'descripcion' | 'categoria_nombre' | 'anonimo' | 'fotos'>>;

export default function DetalleReporteScreen() {
  const { folio, codigo, nuevo } = useLocalSearchParams<{ folio: string; codigo?: string; nuevo?: string }>();
  const api = useCiudadanoApi();
  const [r, setR] = useState<Vista | null>(null);
  const [error, setError] = useState('');
  const [cargando, setCargando] = useState(true);

  const cargar = useCallback(async () => {
    setCargando(true);
    try {
      // Con código es una denuncia anónima (sin sesión); sin código, un reporte de mi cuenta
      const v = codigo ? await api.seguimiento(folio, codigo) : await api.detalle(folio);
      setR(v as Vista);
      setError('');
    } catch (e) {
      setError(mensajeError(e));
    } finally {
      setCargando(false);
    }
  }, [api, folio, codigo]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void cargar();
  }, [cargar]);

  if (!r) {
    return (
      <Pantalla titulo="Reporte" banner={<AvisosCiudadano />}>
        <Tarjeta>
          <Text style={{ color: C.fg }}>{cargando ? 'Cargando…' : error || 'No encontramos este reporte.'}</Text>
          {!cargando ? <Boton texto="Reintentar" variante="secundario" onPress={cargar} /> : null}
          <Boton texto="Ver mis reportes" variante="enlace" onPress={() => router.replace('/mis-reportes')} />
        </Tarjeta>
      </Pantalla>
    );
  }

  const fotos = r.fotos ?? [];
  return (
    <Pantalla titulo={`Reporte ${folio}`} banner={<AvisosCiudadano />}>
      {nuevo && codigo ? (
        <View style={{ backgroundColor: C.warnSoft, borderRadius: 14, padding: 14, gap: 6, borderWidth: 1, borderColor: '#f0c78f' }}>
          <Text style={{ fontWeight: '800', color: C.warn }}>Guarda tu código</Text>
          <Text selectable style={{ fontSize: 26, fontWeight: '800', letterSpacing: 3, color: C.ink }}>{codigo}</Text>
          <Text style={{ color: C.warn, fontSize: 13 }}>Es la única forma de consultar esta denuncia anónima. El servidor no lo guarda y no se puede recuperar. Ya quedó guardado en este teléfono.</Text>
        </View>
      ) : null}

      <Tarjeta>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
          <Text style={{ fontWeight: '800', color: C.fg, flex: 1 }}>{r.categoria_nombre ?? 'Denuncia'}{codigo ? ' · anónima' : ''}</Text>
          <Pastilla texto={ESTADOS[r.estado]} tono={tonoEstado(r.estado)} />
        </View>
        {r.descripcion ? <Text style={{ color: C.fg }}>{r.descripcion}</Text> : null}
        {fotos.length > 0 ? (
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
            {fotos.map((id) => (
              <Image key={id} source={fotoFuente(id, api.token, codigo)} style={{ width: 200, height: 150, borderRadius: 12 }} contentFit="cover" accessibilityLabel="Foto del reporte" />
            ))}
          </ScrollView>
        ) : null}
        {r.latitud != null && r.longitud != null ? <Text style={{ color: C.muted, fontSize: 12.5 }}>Ubicación: {r.latitud.toFixed(5)}, {r.longitud.toFixed(5)}</Text> : null}
      </Tarjeta>

      <Tarjeta titulo="Seguimiento" icono="time-outline">
        {r.historial.map((m, i) => (
          <View key={`${m.created_at}-${i}`} style={{ flexDirection: 'row', gap: 12 }}>
            <View style={{ alignItems: 'center' }}>
              <View style={{ width: 14, height: 14, borderRadius: 7, borderWidth: 2, borderColor: C.brand, backgroundColor: C.brand, marginTop: 3 }} />
              {i < r.historial.length - 1 ? <View style={{ width: 2, flex: 1, backgroundColor: C.line, marginTop: 2 }} /> : null}
            </View>
            <View style={{ flex: 1, paddingBottom: 14 }}>
              <Text style={{ fontWeight: '800', color: C.fg }}>{ESTADOS[m.estado]}</Text>
              <Text style={{ color: C.muted, fontSize: 12.5 }}>{fechaLarga(m.created_at)}</Text>
              {m.mensaje ? <Text style={{ color: C.fg }}>{m.mensaje}</Text> : null}
            </View>
          </View>
        ))}
        {r.historial.length === 0 ? <Text style={{ color: C.muted }}>Recibido. El equipo lo revisará pronto.</Text> : null}
      </Tarjeta>

      <Boton texto="Actualizar" icono="refresh" variante="secundario" cargando={cargando} onPress={cargar} />
      <Boton texto="← Ver mis reportes" variante="enlace" onPress={() => router.replace('/mis-reportes')} />
    </Pantalla>
  );
}
