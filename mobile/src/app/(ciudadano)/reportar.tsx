import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, Text, View } from 'react-native';

import { AvisosCiudadano } from '@/components/avisos-ciudadano';
import { Boton, Campo, FilaSwitch, Pantalla, Tarjeta } from '@/components/ui';
import { CATEGORIAS, guardarAnonima, invalidar, mensajeError, useCiudadanoApi } from '@/lib/ciudadano';
import { C, R } from '@/lib/theme';
import { useToast } from '@/lib/toast';

export default function Reportar() {
  const toast = useToast();
  const api = useCiudadanoApi();
  const [cat, setCat] = useState<string | null>(null);
  const [desc, setDesc] = useState('');
  const [foto, setFoto] = useState<string | null>(null);
  const [anonimo, setAnonimo] = useState(false);
  const [pos, setPos] = useState<{ lat: number; lng: number } | null>(null);
  const [posTexto, setPosTexto] = useState('Aún no se ha tomado tu ubicación');
  const [enviando, setEnviando] = useState(false);
  const [error, setError] = useState('');
  const denuncia = cat === 'denuncia';
  const listo = !!cat && desc.trim().length > 5;

  async function ubicar() {
    try {
      const p = await Location.requestForegroundPermissionsAsync();
      if (p.status !== 'granted') return setPosTexto('Permiso de ubicación negado. Puedes describir el lugar en el texto.');
      setPosTexto('Obteniendo ubicación…');
      const x = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
      setPos({ lat: x.coords.latitude, lng: x.coords.longitude });
      setPosTexto(`Ubicación tomada (±${Math.round(x.coords.accuracy ?? 0)} m)`);
    } catch {
      setPosTexto('No se pudo obtener la ubicación');
    }
  }

  async function tomarFoto(camara: boolean) {
    try {
      const permiso = camara ? await ImagePicker.requestCameraPermissionsAsync() : await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (permiso.status !== 'granted') return toast('Permiso negado. Actívalo en Ajustes para adjuntar fotos.');
      // Calidad media: la foto se envía por datos móviles; el servidor además le quita la ubicación y los datos del teléfono
      const opciones: ImagePicker.ImagePickerOptions = { mediaTypes: ['images'], quality: 0.6, exif: false };
      const r = camara ? await ImagePicker.launchCameraAsync(opciones) : await ImagePicker.launchImageLibraryAsync(opciones);
      if (!r.canceled && r.assets[0]) setFoto(r.assets[0].uri);
    } catch {
      toast('No se pudo abrir la cámara o las fotos.');
    }
  }

  async function enviar() {
    if (!listo || !cat || enviando) return;
    setEnviando(true);
    setError('');
    const esAnonimo = denuncia && anonimo;
    try {
      const r = await api.crear({
        categoria: cat,
        descripcion: desc.trim(),
        anonimo: esAnonimo,
        // Una denuncia anónima no manda ubicación ni foto, para no identificar a nadie
        ...(pos && !esAnonimo ? { latitud: pos.lat, longitud: pos.lng } : {}),
      });
      let avisoFoto = '';
      if (foto && !esAnonimo) {
        try {
          await api.subirFoto(r.folio, foto);
        } catch (e) {
          avisoFoto = ` La foto no se pudo subir (${mensajeError(e)}).`;
        }
      }
      if (esAnonimo && r.codigo) guardarAnonima({ folio: r.folio, codigo: r.codigo, fecha: new Date().toISOString() });
      invalidar('c_reportes');
      setCat(null);
      setDesc('');
      setFoto(null);
      setAnonimo(false);
      setPos(null);
      setPosTexto('Aún no se ha tomado tu ubicación');
      toast(`Reporte enviado. Tu folio: ${r.folio}.${avisoFoto}`, avisoFoto ? 6000 : 4500);
      router.push({ pathname: '/reporte/[folio]', params: esAnonimo && r.codigo ? { folio: r.folio, codigo: r.codigo, nuevo: '1' } : { folio: r.folio, nuevo: '1' } });
    } catch (e) {
      // El borrador se conserva: no se pierde lo escrito si no hubo conexión
      setError(mensajeError(e));
    } finally {
      setEnviando(false);
    }
  }

  return (
    <Pantalla titulo="Reportar" subtitulo="Tu colonia" banner={<AvisosCiudadano />}>
      <Tarjeta titulo="¿Qué quieres reportar?" icono="megaphone-outline">
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
          {CATEGORIAS.map((c) => (
            <Pressable
              key={c.id}
              onPress={() => setCat(c.id)}
              accessibilityRole="button"
              accessibilityState={{ selected: cat === c.id }}
              style={{ width: '31%', flexGrow: 1, alignItems: 'center', gap: 4, paddingVertical: 12, borderRadius: R.button, borderWidth: 1.5, borderColor: cat === c.id ? C.brand : C.line, backgroundColor: cat === c.id ? C.brandSoft : C.white }}>
              <Ionicons name={c.icono} size={24} color={C.brandInk} />
              <Text style={{ fontSize: 12, fontWeight: '700', color: C.fg, textAlign: 'center' }}>{c.nombre}</Text>
            </Pressable>
          ))}
          <Pressable
            onPress={() => setCat('denuncia')}
            accessibilityRole="button"
            accessibilityState={{ selected: denuncia }}
            style={{ width: '100%', flexDirection: 'row', alignItems: 'center', gap: 10, padding: 12, borderRadius: R.button, backgroundColor: C.ink, borderWidth: 2, borderColor: denuncia ? C.brand : C.ink }}>
            <Ionicons name="shield-checkmark-outline" size={24} color={C.brand} />
            <View style={{ flex: 1 }}>
              <Text style={{ color: C.white, fontWeight: '800' }}>Hacer una denuncia</Text>
              <Text style={{ color: '#d9cdbf', fontSize: 12.5 }}>Puede ser anónima</Text>
            </View>
          </Pressable>
        </View>
      </Tarjeta>

      {denuncia ? (
        <View style={{ backgroundColor: C.warnSoft, borderRadius: R.card, padding: 12, borderWidth: 1, borderColor: '#f0c78f' }}>
          <Text style={{ fontWeight: '800', color: C.warn }}>Importante</Text>
          <Text style={{ color: C.warn, fontSize: 13.5 }}>Esta vía no sustituye al 911 ni a una denuncia formal ante la autoridad. Si hay peligro inmediato, llama al 911.</Text>
        </View>
      ) : null}

      <Tarjeta>
        <Campo etiqueta="Cuéntanos qué pasa" value={desc} onChangeText={setDesc} multiline placeholder="Ej. Bache grande frente a la primaria, ya causó accidentes." maxLength={2000} />
        {denuncia && anonimo ? (
          <Text style={{ color: C.muted, fontSize: 12.5 }}>En una denuncia anónima no se envían fotos ni ubicación, para que nadie pueda identificarte.</Text>
        ) : foto ? (
          <View style={{ gap: 6 }}>
            <Image source={{ uri: foto }} style={{ width: '100%', height: 160, borderRadius: R.button }} contentFit="cover" accessibilityLabel="Foto adjunta" />
            <Boton texto="Quitar foto" variante="peligro" pequeno onPress={() => setFoto(null)} />
          </View>
        ) : (
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <Boton texto="Tomar foto" icono="camera-outline" variante="secundario" pequeno onPress={() => tomarFoto(true)} />
            <Boton texto="Galería" icono="images-outline" variante="secundario" pequeno onPress={() => tomarFoto(false)} />
          </View>
        )}
      </Tarjeta>

      {!(denuncia && anonimo) ? (
        <Tarjeta titulo="Dónde está" icono="location-outline">
          <Text style={{ color: C.fg }}>{posTexto}</Text>
          <Boton texto={pos ? 'Actualizar ubicación' : 'Usar mi ubicación'} icono="navigate-outline" variante="secundario" pequeno onPress={ubicar} />
        </Tarjeta>
      ) : null}

      {denuncia ? (
        <Tarjeta>
          <FilaSwitch icono="eye-off" texto="Enviar de forma anónima" detalle="No se guarda tu nombre, tu ubicación ni tu foto. Recibirás un código para consultar el avance." valor={anonimo} onCambio={setAnonimo} />
        </Tarjeta>
      ) : null}

      {error ? (
        <View style={{ flexDirection: 'row', gap: 8, alignItems: 'center', backgroundColor: C.badSoft, borderRadius: 12, padding: 12 }} accessibilityLiveRegion="polite">
          <Ionicons name="alert-circle" size={18} color={C.bad} />
          <Text style={{ color: C.bad, flex: 1, fontWeight: '600' }}>{error} Tu reporte sigue aquí, sin perder lo escrito.</Text>
        </View>
      ) : null}
      <Boton texto="Enviar reporte" icono="send" onPress={enviar} deshabilitado={!listo} cargando={enviando} brillo />
    </Pantalla>
  );
}
