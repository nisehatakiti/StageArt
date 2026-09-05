import { useEffect, useRef, useState } from 'react';
import { AccessibilityInfo, StyleSheet, View } from 'react-native';

import { BrandColors } from '@/constants/theme';

const STARTUP_VIDEO = require('../../assets/videos/StageArt_Startup_3sec.mp4');

/**
 * A safety net only, never the completion trigger - onFinish() is driven
 * by the player's own 'playToEnd' event (the file's real ~3s duration),
 * not this timer. This exists solely so a stalled/unsupported asset, or
 * a load failure (see the dynamic-import catch and 'statusChange'/error
 * listener below), can never leave the app stuck before boot -
 * comfortably longer than the video itself.
 */
const FALLBACK_TIMEOUT_MS = 8000;

type ExpoVideoModule = typeof import('expo-video');
type ExpoModule = typeof import('expo');

/**
 * StageArt起動アニメーション方針変更 (2026-09-04): the previous SVG-drawn
 * icon+glow+wordmark sequence (StageArtIcon/StageArtLogo, hand-built
 * Animated.timing curves) is retired from startup. The completed After
 * Effects deliverable (assets/videos/StageArt_Startup_3sec.mp4) is
 * played back as-is instead - none of its visuals, timing, or effects
 * are reproduced in code here; this component's only job is to play the
 * file and call onFinish() when it ends.
 *
 * StageArtIcon.tsx itself is intentionally left in place (not deleted) -
 * it's a small reusable brand component (docs/03-BrandIdentity.md also
 * names it as a future avatar-placeholder source), just no longer
 * referenced by this screen.
 *
 * expo-video is loaded dynamically, the same way login.tsx's own
 * useGoogleSigninButtonComponent() already has to (see that hook's
 * docblock): a static top-level `import` pulls in expo-video's native
 * binding the instant any file that imports this module is evaluated -
 * including every Jest test that renders the router's "/" route, even
 * though StartupAnimation is never actually mounted under test
 * (NODE_ENV=test skips straight past it - see index.tsx). That native
 * binding throws immediately in the Jest environment (confirmed by
 * actually running the suite, not assumed), breaking every such test.
 * Deferring the import into an effect means it only ever runs where a
 * real expo-video native module exists.
 */
export function StartupAnimation({ onFinish }: { onFinish: () => void }) {
  const finishedRef = useRef(false);
  const [modules, setModules] = useState<{ expoVideo: ExpoVideoModule; expo: ExpoModule } | null>(null);

  function finish() {
    if (finishedRef.current) return;
    finishedRef.current = true;
    onFinish();
  }

  useEffect(() => {
    let cancelled = false;

    Promise.all([import('expo-video'), import('expo')])
      .then(([expoVideo, expo]) => {
        if (!cancelled) setModules({ expoVideo, expo });
      })
      .catch(() => {
        // expo-video unavailable (old binary / unsupported environment)
        // - fall back to the existing startup flow rather than getting
        // stuck with nothing on screen.
        finish();
      });

    AccessibilityInfo.isReduceMotionEnabled()
      .then((reduceMotion) => {
        if (!cancelled && reduceMotion) finish();
      })
      .catch(() => {});

    const timeout = setTimeout(finish, FALLBACK_TIMEOUT_MS);

    return () => {
      cancelled = true;
      clearTimeout(timeout);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  if (!modules) {
    return <View style={styles.container} testID="startup-animation" />;
  }

  return <StartupVideo expoVideo={modules.expoVideo} expo={modules.expo} onFinishOnce={finish} />;
}

function StartupVideo({
  expoVideo,
  expo,
  onFinishOnce,
}: {
  expoVideo: ExpoVideoModule;
  expo: ExpoModule;
  onFinishOnce: () => void;
}) {
  const { useVideoPlayer, VideoView } = expoVideo;
  const { useEventListener } = expo;

  const player = useVideoPlayer(STARTUP_VIDEO, (p) => {
    p.muted = true;
    p.play();
  });

  useEventListener(player, 'playToEnd', () => onFinishOnce());
  useEventListener(player, 'statusChange', (payload) => {
    if (payload.status === 'error') {
      onFinishOnce();
      return;
    }
    // A real-environment finding (Web, confirmed by testing an actual
    // export in a real browser, not assumed): useVideoPlayer's setup
    // callback runs before VideoView's underlying <video> element has
    // mounted, so the play() call above lands while the player has no
    // mounted video yet and is silently dropped there - nothing plays.
    // 'readyToPlay' only ever fires from the mounted <video> element's
    // own 'canplay' event, so play() here is guaranteed to reach a real,
    // mounted video.
    if (payload.status === 'readyToPlay') {
      player.play();
    }
  });

  return (
    <View style={styles.container} testID="startup-animation">
      <VideoView player={player} style={styles.video} contentFit="contain" nativeControls={false} playsInline />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: BrandColors.blackoutBlack },
  video: { flex: 1 },
});
