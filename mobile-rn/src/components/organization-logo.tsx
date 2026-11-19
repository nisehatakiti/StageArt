import { useState } from 'react';
import { Image, Platform, StyleSheet, useWindowDimensions, View, type StyleProp, type ViewStyle } from 'react-native';

import { Radius } from '@/constants/theme';

/**
 * 団体ロゴ: the whole image at its own aspect ratio (`contain`, never
 * cropped), sized to fit a box instead of a fixed 96x96 square - a fixed
 * square made wide logos tiny (a 4:1 logo showed as about 88x22).
 *
 * Box: at most `maxWidth` wide (and never wider than its container) and
 * `maxHeight` high; narrow screens (< 600px) use the smaller height. With
 * the defaults a square logo is 160x160 (120x120 on a phone), a 4:1 logo
 * 480x120 (as wide as the column allows on a phone) and a 1:2 logo 80x160 -
 * large enough to read next to the Hero and the organization name. Until
 * the image size is known the box is square.
 */
export const LOGO_MAX_WIDTH = 480;
export const LOGO_MAX_HEIGHT = 160;
export const LOGO_MAX_HEIGHT_NARROW = 120;
const NARROW_SCREEN_WIDTH = 600;

export function OrganizationLogo({
  uri,
  testID,
  frameTestID,
  maxWidth = LOGO_MAX_WIDTH,
  maxHeight = LOGO_MAX_HEIGHT,
  maxHeightNarrow = LOGO_MAX_HEIGHT_NARROW,
  style,
}: {
  uri: string;
  testID?: string;
  frameTestID?: string;
  maxWidth?: number;
  maxHeight?: number;
  maxHeightNarrow?: number;
  style?: StyleProp<ViewStyle>;
}) {
  const { width: windowWidth } = useWindowDimensions();
  const [aspectRatio, setAspectRatio] = useState<number | null>(null);

  // A new image starts from the square fallback until its own size is known.
  const [sizedUri, setSizedUri] = useState(uri);
  if (sizedUri !== uri) {
    setSizedUri(uri);
    setAspectRatio(null);
  }

  function handleLoad(event: unknown) {
    const size = imageSizeFromLoadEvent(event);

    if (size) {
      setAspectRatio(size.width / size.height);
      return;
    }

    // react-native-web's load event no longer carries the <img> once it
    // reaches onLoad (it is dispatched after decode()), so ask for the
    // size instead - react-native-web answers from the browser's own
    // image (Image.getSize is only used on the web: the native module's
    // jest mock does not match its signature).
    if (Platform.OS === 'web') {
      Image.getSize(
        uri,
        (width, height) => {
          if (width > 0 && height > 0) {
            setAspectRatio(width / height);
          }
        },
        () => undefined
      );
    }
  }

  const boxHeight = windowWidth > 0 && windowWidth < NARROW_SCREEN_WIDTH ? maxHeightNarrow : maxHeight;
  const ratio = aspectRatio ?? 1;
  const width = Math.min(maxWidth, boxHeight * ratio);

  return (
    <View testID={frameTestID} style={[styles.frame, { width, aspectRatio: ratio }, style]}>
      <Image testID={testID} source={{ uri }} style={styles.image} resizeMode="contain" onLoad={handleLoad} />
    </View>
  );
}

/**
 * The loaded image's own size when the load event carries it: native
 * platforms report it as nativeEvent.source (a DOM load event's target,
 * the <img>, is also read when present).
 */
export function imageSizeFromLoadEvent(event: unknown): { width: number; height: number } | null {
  const nativeEvent = (event as { nativeEvent?: { source?: { width?: number; height?: number }; target?: { naturalWidth?: number; naturalHeight?: number } } } | null)?.nativeEvent;
  const width = nativeEvent?.source?.width ?? nativeEvent?.target?.naturalWidth ?? 0;
  const height = nativeEvent?.source?.height ?? nativeEvent?.target?.naturalHeight ?? 0;

  return width > 0 && height > 0 ? { width, height } : null;
}

const styles = StyleSheet.create({
  // flexShrink 0: in a row (e.g. next to the logo buttons) the box keeps
  // its size and wraps instead of being squeezed; maxWidth still caps it.
  frame: { maxWidth: '100%', flexShrink: 0, borderRadius: Radius.small, overflow: 'hidden' },
  image: { width: '100%', height: '100%' },
});
