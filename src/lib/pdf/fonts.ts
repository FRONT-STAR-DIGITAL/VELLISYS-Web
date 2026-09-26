import { Font } from '@react-pdf/renderer';

let registered = false;

/** Optional hook — register custom brand TTFs here when available under /assets/fonts. */
export function ensurePdfFonts(): void {
  if (registered) return;
  registered = true;
  // Built-in Helvetica is used by default. Uncomment when brand fonts ship:
  // Font.register({ family: 'Vellisys', fonts: [
  //   { src: '/assets/fonts/Brand-Regular.ttf' },
  //   { src: '/assets/fonts/Brand-Bold.ttf', fontWeight: 700 },
  // ]});
  void Font;
}
