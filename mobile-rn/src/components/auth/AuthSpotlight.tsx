import { StyleSheet, View } from 'react-native';
import Svg, { Defs, RadialGradient, Rect, Stop } from 'react-native-svg';

import { STAGE } from './authTheme';

/**
 * 認証画面デザイン統一 §5: not a literal spotlight beam, and deliberately
 * NOT the old hard-edged solid circle behind the logo (a flat-color View
 * with borderRadius + low opacity still has a visible, if faint, circular
 * boundary - exactly the "丸い円” the redesign instruction calls out).
 * A true radial-gradient fade (opaque center -> fully transparent edge,
 * via react-native-svg's RadialGradient, already a project dependency -
 * see StageArtIcon.tsx) has no boundary to see at all.
 *
 * Rendered as one FIXED-size square (not stretched to the screen's own
 * width/height) so the gradient itself never becomes an ellipse on a wide
 * viewport - centered horizontally within an absolutely-positioned,
 * screen-covering wrapper instead, which is what actually centers it on
 * the auth content regardless of aspect ratio. Sits behind AuthLayout's
 * content (rendered before it), covering the whole auth screen rather
 * than only the logo, so it reads as "light around the person operating
 * the form" rather than "light source parked behind the wordmark".
 *
 * StageArt 認証画面 ロゴ強化 (2026-09-07): the wrapper's own vertical
 * anchor moved from `justifyContent: 'center'` to a fixed `paddingTop`,
 * matching AuthLayout's content moving from screen-center to a
 * flex-start + paddingTop position (a bigger logo pushed toward the top
 * of the screen). SIZE grew accordingly so the glow still comfortably
 * reaches from the logo down through the form and links, instead of
 * only covering the vertical screen-center the old centered content used
 * to occupy.
 */
const SIZE = 820;

export function AuthSpotlight() {
  return (
    <View style={styles.wrapper} pointerEvents="none">
      <Svg width={SIZE} height={SIZE} viewBox={`0 0 ${SIZE} ${SIZE}`}>
        <Defs>
          <RadialGradient id="authSpotlight" cx="50%" cy="50%" r="50%">
            <Stop offset="0%" stopColor={STAGE.spotlight} stopOpacity={0.16} />
            <Stop offset="55%" stopColor={STAGE.spotlight} stopOpacity={0.06} />
            <Stop offset="100%" stopColor={STAGE.spotlight} stopOpacity={0} />
          </RadialGradient>
        </Defs>
        <Rect x={0} y={0} width={SIZE} height={SIZE} fill="url(#authSpotlight)" />
      </Svg>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    alignItems: 'center',
    justifyContent: 'flex-start',
    paddingTop: 40,
  },
});
