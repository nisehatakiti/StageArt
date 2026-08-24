import { useEffect, useMemo } from 'react';
import { AccessibilityInfo, Animated, Easing, StyleSheet, View } from 'react-native';

import { StageArtIcon } from '@/components/brand/StageArtIcon';
import { StageArtLogo } from '@/components/brand/StageArtLogo';
import { BrandColors } from '@/constants/theme';

const ANIMATION_DURATION_MS = 1100;

/**
 * StageArt Web First Phase 1 (docs/03-StartupExperience.md): the
 * confirmed sequence - blackout -> warm overhead stage light enters ->
 * beige circular S+A icon emerges -> StageArt wordmark fades in ->
 * transition to destination. Total ~0.8-1.2s. Supersedes the earlier
 * curtain-opening placeholder (see git history) - that was itself a
 * disclosed placeholder, not an official asset, and this Blueprint
 * update explicitly retires it.
 *
 * Deliberately restrained per docs/03-BrandIdentity.md §4 ("avoid
 * excessive gradients, glass effects, neon, decorative floating
 * shapes"): the "light" is a single soft warm glow fading in behind the
 * icon, not a literal beam or sci-fi effect.
 *
 * Respects the OS-level "reduce motion" accessibility setting - skips
 * straight to onFinish() without animating.
 */
export function StartupAnimation({ onFinish }: { onFinish: () => void }) {
  // Animated.Value instances need a stable identity across renders
  // (Animated mutates them internally via .setValue()/.timing(), never
  // via React re-render) - useMemo, not useRef, since react-hooks/refs
  // (React Compiler's stricter ESLint rule set) flags reading a ref's
  // .current during render, including indirectly via .interpolate().
  const glowOpacity = useMemo(() => new Animated.Value(0), []);
  const iconOpacity = useMemo(() => new Animated.Value(0), []);
  const iconScale = useMemo(() => new Animated.Value(0.85), []);
  const wordmarkOpacity = useMemo(() => new Animated.Value(0), []);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      const reduceMotion = await AccessibilityInfo.isReduceMotionEnabled().catch(() => false);

      if (cancelled) return;

      if (reduceMotion) {
        onFinish();
        return;
      }

      Animated.sequence([
        Animated.delay(150),
        Animated.parallel([
          Animated.timing(glowOpacity, { toValue: 1, duration: 300, easing: Easing.out(Easing.cubic), useNativeDriver: true }),
          Animated.timing(iconOpacity, { toValue: 1, duration: 300, easing: Easing.out(Easing.cubic), useNativeDriver: true }),
          Animated.timing(iconScale, { toValue: 1, duration: 300, easing: Easing.out(Easing.cubic), useNativeDriver: true }),
        ]),
        Animated.timing(wordmarkOpacity, { toValue: 1, duration: 330, easing: Easing.out(Easing.cubic), useNativeDriver: true }),
        Animated.delay(300),
      ]).start(() => {
        if (!cancelled) onFinish();
      });
    })();

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <View style={styles.container} testID="startup-animation">
      <Animated.View style={[styles.glow, { opacity: glowOpacity }]} />
      <Animated.View style={[styles.iconWrap, { opacity: iconOpacity, transform: [{ scale: iconScale }] }]}>
        <StageArtIcon size={112} />
      </Animated.View>
      <Animated.View style={[styles.logoWrap, { opacity: wordmarkOpacity }]}>
        <StageArtLogo width={240} height={72} />
      </Animated.View>
    </View>
  );
}

export { ANIMATION_DURATION_MS };

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: BrandColors.blackoutBlack,
    gap: 20,
  },
  glow: {
    position: 'absolute',
    width: 260,
    height: 260,
    borderRadius: 130,
    backgroundColor: BrandColors.warmGold,
    opacity: 0.25,
  },
  iconWrap: { alignItems: 'center' },
  logoWrap: { alignItems: 'center' },
});
