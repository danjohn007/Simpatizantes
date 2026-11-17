import { randomUUID } from 'expo-crypto';
import * as Location from 'expo-location';
import { router, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import { AvisosEquipo } from '@/components/avisos';
import { Boton, Campo, Chip, FilaSwitch, Pantalla, Pastilla, Tarjeta } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { borrarItem, type Captura, encolar, obtenerItem } from '@/lib/db';
import { avisarCambios } from '@/lib/sync';
import { useSync } from '@/lib/sync-context';
import { C, sombra } from '@/lib/theme';
import { useToast } from '@/lib/toast';

/** Respaldo por si el catálogo del servidor aún no se descargó (primer uso sin conexión). */
const TEMAS_RESPALDO = [
  ['agua', 'Agua'], ['drenaje', 'Drenaje'], ['seguridad', 'Seguridad'], ['alumbrado', 'Alumbrado'], ['baches', 'Baches y calles'],
  ['basura', 'Basura'], ['transporte', 'Transporte'], ['salud', 'Salud'], ['educacion', 'Educación'], ['empleo', 'Empleo'],
  ['parques', 'Parques y deporte'], ['otro', 'Otro'],
] as const;

type Ubicacion = { lat: number; lng: number; prec: number; en: number };

const VACIO = { nombre: '', dom: '', seccion: '', whatsapp: '', colonia: '', comentario: '' };

export default function PantallaCaptura() {
  const { me, usuario } = useAuth();
  const toast = useToast();
  const { sincronizarAhora } = useSync();
  const { corregir } = useLocalSearchParams<{ corregir?: string }>();

  const [f, setF] = useState(VACIO);
  const [temas, setTemas] = useState<string[]>([]);
  const [consent, setConsent] = useState(false);
  const [ubic, setUbic] = useState<Ubicacion | null>(null);
  const [ubicTexto, setUbicTexto] = useState('Ubicación sin obtener');
  const [campanaId, setCampanaId] = useState<number | null>(null);
  const [guardando, setGuardando] = useState(false);

  const campo = (k: keyof typeof VACIO) => (v: string) => setF((p) => ({ ...p, [k]: v }));
  const catalogo = me?.temas?.length ? me.temas.map((t) => [t.clave, t.etiqueta] as const) : TEMAS_RESPALDO;
  const elegirCampana = !!me && me.campanas.length > 0;

  const pedirUbicacion = useCallback(async (silencioso: boolean) => {
    try {
      const permiso = silencioso ? await Location.getForegroundPermissionsAsync() : await Location.requestForegroundPermissionsAsync();
      if (permiso.status !== 'granted') {
        if (!silencioso) setUbicTexto('Permiso de ubicación negado. Actívelo en Ajustes.');
        return;
      }
      setUbicTexto('Obteniendo ubicación…');
      const p = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
      const prec = Math.round(p.coords.accuracy ?? 0);
      setUbic({ lat: p.coords.latitude, lng: p.coords.longitude, prec, en: Date.now() });
      setUbicTexto(`Ubicación obtenida (±${prec} m)${prec > 100 ? ' · poca precisión, salga a cielo abierto' : ''}`);
    } catch {
      setUbicTexto('No se pudo obtener la ubicación');
    }
  }, []);

  // Si el permiso ya estaba concedido, la ubicación se obtiene sola
  useEffect(() => {
    Location.getForegroundPermissionsAsync()
      .then((p) => {
        if (p.status === 'granted') pedirUbicacion(true);
      })
      .catch(() => undefined);
  }, [pedirUbicacion]);

  // Corregir un registro que el servidor rechazó: se vuelve a cargar en el formulario
  useEffect(() => {
    if (!corregir) return;
    (async () => {
      const item = await obtenerItem(corregir);
      if (item) {
        const p = item.payload;
        setF({ nombre: p.nombre_completo, dom: p.domicilio_completo, seccion: p.seccion_electoral, whatsapp: p.whatsapp, colonia: p.colonia, comentario: p.comentario_colonia });
        setTemas(p.temas);
        setConsent(p.consentimiento === '1');
        if (p.campana_id) setCampanaId(p.campana_id);
        await borrarItem(corregir);
        avisarCambios();
        toast('Corrija los datos y guarde de nuevo.');
      }
      router.setParams({ corregir: undefined });
    })();
  }, [corregir, toast]);

  function alternarTema(clave: string) {
    setTemas((t) => (t.includes(clave) ? t.filter((x) => x !== clave) : [...t, clave]));
  }

  async function guardar() {
    if (!usuario) return;
    const faltan: string[] = [];
    const nombre = f.nombre.trim().replace(/\s+/g, ' ');
    const wa = f.whatsapp.replace(/\D/g, '');
    if (nombre.length < 5 || !nombre.includes(' ')) faltan.push('nombre y apellido');
    if (!f.dom.trim()) faltan.push('domicilio');
    if (!/^\d{1,4}$/.test(f.seccion.trim())) faltan.push('sección (4 dígitos)');
    if (wa && wa.length !== 10) faltan.push('WhatsApp de 10 dígitos');
    if (elegirCampana && !campanaId) faltan.push('campaña');
    if (faltan.length) return toast(`Falta: ${faltan.join(', ')}`, 4500);

    setGuardando(true);
    const seccion = ('0000' + f.seccion.trim()).slice(-4);
    const registro: Captura = {
      uuid: randomUUID(),
      _usuario: usuario.id,
      capturado_en: new Date().toISOString(),
      nombre_completo: nombre,
      domicilio_completo: f.dom.trim(),
      seccion_electoral: seccion,
      whatsapp: wa,
      colonia: f.colonia.trim(),
      comentario_colonia: f.comentario.trim(),
      temas,
      consentimiento: consent ? '1' : '',
    };
    if (campanaId) registro.campana_id = campanaId;
    // La ubicación solo vale si es reciente (10 minutos)
    if (ubic && Date.now() - ubic.en < 600000) {
      registro.latitud = ubic.lat.toFixed(7);
      registro.longitud = ubic.lng.toFixed(7);
    }
    try {
      await encolar(registro);
      avisarCambios();
      const fuera = !!me?.zonas.length && !me.zonas.includes(seccion);
      setF(VACIO);
      setTemas([]);
      setConsent(false);
      toast(fuera ? `Guardado. La sección ${seccion} está fuera de sus zonas; avise a su coordinador.` : 'Guardado en el teléfono. Se envía solo.', fuera ? 6000 : 3200);
      sincronizarAhora();
    } catch {
      toast('No se pudo guardar en el teléfono. Revise el espacio disponible.', 5000);
    } finally {
      setGuardando(false);
    }
  }

  return (
    <Pantalla titulo="Captura en campo" subtitulo={usuario?.campana ?? 'Nuevo registro'} banner={<AvisosEquipo />}>
      <View style={[estilos.ubicacion, ubic ? { backgroundColor: C.okSoft } : null]}>
        <View style={[estilos.ubicacionIcono, ubic ? { backgroundColor: C.ok } : null]}>
          <Ionicons name={ubic ? 'location' : 'location-outline'} size={20} color={ubic ? C.white : C.muted} />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={{ fontWeight: '700', color: ubic ? C.ok : C.fg, fontSize: 14 }}>{ubicTexto}</Text>
          <Text style={{ color: C.muted, fontSize: 12 }}>Solo para ubicar el registro en el mapa. Puede guardar sin ubicación.</Text>
        </View>
        <Boton texto={ubic ? 'Actualizar' : 'Usar'} variante="secundario" pequeno onPress={() => pedirUbicacion(false)} />
      </View>

      {elegirCampana ? (
        <Tarjeta titulo="Campaña" icono="megaphone-outline">
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
            {me!.campanas.map((c) => (
              <Chip key={c.id} texto={c.nombre} activo={campanaId === c.id} onPress={() => setCampanaId(c.id)} />
            ))}
          </View>
        </Tarjeta>
      ) : null}

      <Tarjeta titulo="Datos de la persona" icono="person-outline">
        <Campo etiqueta="Nombre completo *" icono="person" value={f.nombre} onChangeText={campo('nombre')} placeholder="Nombre y apellidos" autoCapitalize="words" autoComplete="off" />
        <Campo etiqueta="Domicilio *" icono="home" value={f.dom} onChangeText={campo('dom')} placeholder="Calle, número y colonia" multiline />
        <View style={{ flexDirection: 'row', gap: 10 }}>
          <View style={{ flex: 1 }}>
            <Campo etiqueta="Sección *" icono="map" value={f.seccion} onChangeText={campo('seccion')} placeholder="0123" keyboardType="number-pad" maxLength={4} />
          </View>
          <View style={{ flex: 1.5 }}>
            <Campo etiqueta="WhatsApp" icono="logo-whatsapp" value={f.whatsapp} onChangeText={campo('whatsapp')} placeholder="10 dígitos" keyboardType="phone-pad" maxLength={16} />
          </View>
        </View>
      </Tarjeta>

      <Tarjeta titulo="¿Qué le gustaría mejorar en su colonia?" icono="chatbubble-ellipses-outline">
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
          {catalogo.map(([clave, etiqueta]) => (
            <Chip key={clave} texto={etiqueta} activo={temas.includes(clave)} onPress={() => alternarTema(clave)} />
          ))}
        </View>
        <Campo etiqueta="Colonia" icono="business" value={f.colonia} onChangeText={campo('colonia')} placeholder="Ej. Villas del Sol" />
        <Campo etiqueta="Comentario (opcional)" value={f.comentario} onChangeText={campo('comentario')} multiline maxLength={500} placeholder="Con sus propias palabras" ayuda="Lo que diga la persona ayuda a priorizar las necesidades de su zona." />
      </Tarjeta>

      <Tarjeta>
        <FilaSwitch icono="shield-checkmark" texto="Aceptó recibir mensajes de la campaña" detalle="Márquelo solo si la persona lo dijo expresamente." valor={consent} onCambio={setConsent} />
      </Tarjeta>

      <Boton texto="Guardar registro" icono="checkmark-circle-outline" onPress={guardar} cargando={guardando} brillo />
      <View style={{ alignItems: 'center' }}>
        <Pastilla texto="Funciona sin conexión" tono="ok" />
      </View>
    </Pantalla>
  );
}

const estilos = StyleSheet.create({
  ubicacion: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: C.white, borderRadius: 18, padding: 12, ...sombra(4, 0.07, 14) },
  ubicacionIcono: { width: 40, height: 40, borderRadius: 20, backgroundColor: C.mute, alignItems: 'center', justifyContent: 'center' },
});
