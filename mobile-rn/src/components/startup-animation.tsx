import { useEffect, useMemo } from 'react';
import { AccessibilityInfo, Animated, Easing, StyleSheet } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';

const ANIMATION_DURATION_MS = 1800;

/**
 * BusinessFlowUXClarifications.md §12 "アプリ起動時アニメーション"
 * (1.5-3.0秒, 幕が開く/脚立/Sの筆致モチーフ) - a curtain-opening motif:
 * two panels (in the app's accent red) start covering the full screen
 * and slide apart to reveal the StageArt wordmark underneath, which
 * simultaneously fades/scales in. The official brand animation asset
 * (脚立/Sの筆致) does not exist in this repo any more than the static
 * logo does (see app-shell.tsx's docblock) - this is a simple,
 * disclosed placeholder built from the same tokens as the rest of this
 * Phase's Visual Design work, not an invented brand asset.
 *
 * Respects the OS-level "reduce motion" accessibility setting - skips
 * straight to onFinish() without animating rather than forcing motion
 * on a user who has asked their device to minimize it.
 */
export function StartupAnimation({ onFinish }: { onFinish: () => void }) {
  // Animated.Value instances just need a stable identity across renders
  // (Animated mutates them internally via .setValue()/.timing(), never
  // via React re-render) - useMemo, not useRef, since react-hooks/refs
  // (React Compiler's stricter ESLint rule set) flags reading a ref's
  // .current during render, including indirectly via .interpolate()
  // below.
  const curtainLeft = useMemo(() => new Animated.Value(0), []);
  const curtainRight = useMemo(() => new Animated.Value(0), []);
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
        Animated.delay(250),
        Animated.parallel([
          Animated.timing(curtainLeft, { toValue: -1, duration: 700, easing: Easing.out(Easing.cubic), useNativeDriver: true }),
          Animated.timing(curtainRight, { toValue: 1, duration: 700, easing: Easing.out(Easing.cubic), useNativeDriver: true }),
          Animated.timing(wordmarkOpacity, { toValue: 1, duration: 600, delay: 200, useNativeDriver: true }),
        ]),
        Animated.delay(400),
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
    <ThemedView style={styles.container} testID="startup-animation">
      <Animated.View style={[styles.wordmarkWrap, { opacity: wordmarkOpacity }]}>
        <ThemedText type="title" themeColor="accent" style={styles.wordmark}>
          StageArt
        </ThemedText>
      </Animated.View>

      <Animated.View
        style={[
          styles.curtain,
          styles.curtainLeft,
          { transform: [{ translateX: curtainLeft.interpolate({ inputRange: [-1, 0], outputRange: ['-100%', '0%'] }) }] },
        ]}
      />
      <Animated.View
        style={[
          styles.curtain,
          styles.curtainRight,
          { transform: [{ translateX: curtainRight.interpolate({ inputRange: [0, 1], outputRange: ['0%', '100%'] }) }] },
        ]}
      />
    </ThemedView>
  );
}

export { ANIMATION_DURATION_MS };

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  wordmarkWrap: { alignItems: 'center' },
  wordmark: { fontSize: 40, lineHeight: 46, fontWeight: '800' },
  curtain: {
    position: 'absolute',
    top: 0,
    bottom: 0,
    width: '50%',
    backgroundColor: '#C4432F',
  },
  curtainLeft: { left: 0 },
  curtainRight: { right: 0 },
});
