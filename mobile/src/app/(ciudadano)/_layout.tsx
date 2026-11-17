import { Tabs } from 'expo-router/js-tabs';

import { BarraFlotante, type IconosBarra } from '@/components/barra-flotante';
import { C } from '@/lib/theme';

const ICONOS: IconosBarra = {
  index: ['home-outline', 'home'],
  reportar: ['megaphone-outline', 'megaphone'],
  'mis-reportes': ['list-outline', 'list'],
  eventos: ['calendar-outline', 'calendar'],
  cuenta: ['person-outline', 'person'],
};

export default function CiudadanoLayout() {
  return (
    <Tabs
      tabBar={(props) => <BarraFlotante {...props} iconos={ICONOS} />}
      screenOptions={{ headerShown: false, sceneStyle: { backgroundColor: C.bg } }}>
      <Tabs.Screen name="index" options={{ title: 'Inicio' }} />
      <Tabs.Screen name="reportar" options={{ title: 'Reportar' }} />
      <Tabs.Screen name="mis-reportes" options={{ title: 'Reportes' }} />
      <Tabs.Screen name="eventos" options={{ title: 'Eventos' }} />
      <Tabs.Screen name="cuenta" options={{ title: 'Cuenta' }} />
      <Tabs.Screen name="reporte/[folio]" options={{ href: null }} />
    </Tabs>
  );
}
