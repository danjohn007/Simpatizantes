import { Tabs } from 'expo-router/js-tabs';

import { BarraFlotante, type IconosBarra } from '@/components/barra-flotante';
import { useCola } from '@/lib/hooks';
import { SyncProvider } from '@/lib/sync-context';
import { C } from '@/lib/theme';

const ICONOS: IconosBarra = {
  index: ['create-outline', 'create'],
  pendientes: ['cloud-upload-outline', 'cloud-upload'],
  metas: ['flag-outline', 'flag'],
  perfil: ['person-outline', 'person'],
};

function Pestanas() {
  const { pendientes, conError } = useCola();
  const sinEnviar = pendientes + conError;
  return (
    <Tabs
      tabBar={(props) => <BarraFlotante {...props} iconos={ICONOS} />}
      screenOptions={{ headerShown: false, sceneStyle: { backgroundColor: C.bg } }}>
      <Tabs.Screen name="index" options={{ title: 'Captura' }} />
      <Tabs.Screen name="pendientes" options={{ title: 'Pendientes', tabBarBadge: sinEnviar > 0 ? sinEnviar : undefined }} />
      <Tabs.Screen name="metas" options={{ title: 'Metas' }} />
      <Tabs.Screen name="perfil" options={{ title: 'Perfil' }} />
    </Tabs>
  );
}

export default function EquipoLayout() {
  return (
    <SyncProvider>
      <Pestanas />
    </SyncProvider>
  );
}
