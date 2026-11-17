import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { type ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';
import Animated, { type AnimatedStyle } from 'react-native-reanimated';
import { type StyleProp, type ViewStyle } from 'react-native';

const CARTEL = require('../../assets/images/login.jpg');

/**
 * El cartel de la campaña a todo el ancho, alineado arriba para que se lea el título completo,
 * con un degradado oscuro abajo para que el texto encima se lea bien.
 * `estiloImagen` permite animar la imagen (por ejemplo, el "asentarse" de 1.08 a 1).
 */
export function Cartel({ alto, children, estiloImagen, estilo }: { alto: number; children?: ReactNode; estiloImagen?: StyleProp<AnimatedStyle<StyleProp<ViewStyle>>>; estilo?: StyleProp<ViewStyle> }) {
  return (
    <View style={[{ height: alto, overflow: 'hidden', backgroundColor: '#ff9100' }, estilo]}>
      <Animated.View style={[StyleSheet.absoluteFill, estiloImagen]}>
        <Image source={CARTEL} style={StyleSheet.absoluteFill} contentFit="cover" contentPosition="top center" accessibilityLabel="Cartel de la campaña" transition={150} />
      </Animated.View>
      <LinearGradient colors={['rgba(17,17,17,0)', 'rgba(17,17,17,0.15)', 'rgba(17,17,17,0.88)']} locations={[0.45, 0.7, 1]} style={StyleSheet.absoluteFill} pointerEvents="none" />
      {children}
    </View>
  );
}
