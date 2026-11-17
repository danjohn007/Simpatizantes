/** Colores y medidas de la app: negro, naranja y blanco, con buen contraste. */
export const C = {
  ink: '#111111',
  brand: '#ff8000',
  brandInk: '#a34700',
  brandSoft: '#fff0e0',
  bg: '#f5f2ee',
  card: '#ffffff',
  line: '#e9e4de',
  field: '#d9d2cb',
  fg: '#17130f',
  muted: '#6b6259',
  ok: '#2f6b4a',
  okSoft: '#e4f1ea',
  warn: '#8a4a00',
  warnSoft: '#fff1de',
  bad: '#8a2f25',
  badSoft: '#fbe9e7',
  mute: '#ece6df',
  white: '#ffffff',
  paper: '#faf8f5',
  inkSoft: '#26211c',
  brandLight: '#ff9a33',
} as const;

export const R = { card: 14, field: 10, button: 12, pill: 999 } as const;

/** Sombra suave de tarjetas y botones (iOS usa shadow*, Android usa elevation). */
export const sombra = (alto = 4, opacidad = 0.08, radio = 12, color = '#3a2a14') =>
  ({ shadowColor: color, shadowOffset: { width: 0, height: alto }, shadowOpacity: opacidad, shadowRadius: radio, elevation: Math.round(alto * 0.9) }) as const;
