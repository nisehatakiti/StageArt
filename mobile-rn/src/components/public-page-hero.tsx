import { useState } from 'react';
import { Image, StyleSheet, View, type LayoutChangeEvent } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Radius } from '@/constants/theme';
import type { PublicPageAppearance } from '@/features/publicPage/types';

/**
 * Hero of a public page (公開ページ設定), laid out as StageArtCore's theme
 * lays out its `.stageart-hero`: the background image `cover`, centered
 * (manifest: fit "cover", position "center center"), a minimum height
 * rather than a fixed one (min-height 590px / 500px on a phone), the copy
 * centered vertically with generous padding (90px), and the name as the
 * main element - large heading type (clamp(3rem, 8vw, 7.5rem), line-height
 * ~1.05) with the description clearly smaller and at most 680px wide
 * (`white-space: pre-line`).
 *
 * The sizes follow the Hero's own width (it sits inside the page column,
 * not across the whole window), and the box grows with a long title or
 * description instead of cutting it off. The image is darkened a little
 * more than Core's rgba(0,0,0,.22), with a soft text shadow, so white text
 * stays readable on light presets and own images.
 *
 * An empty title/description falls back to the page's own name/
 * description, as StageArtCore's admin hint says (空欄の場合は…名・紹介を
 * 使用します). Only for hero backgrounds - never for the organization
 * logo, which is always shown whole (OrganizationLogo).
 */
export const HERO_NARROW_WIDTH = 600;

/** Font sizes, padding and minimum height for a Hero of this width. */
export function heroLayout(width: number) {
  const narrow = width < HERO_NARROW_WIDTH;
  const clamp = (value: number, min: number, max: number) => Math.round(Math.min(max, Math.max(min, value)));
  const titleSize = narrow ? clamp(width * 0.1, 30, 44) : clamp(width * 0.08, 44, 84);
  const descriptionSize = narrow ? 15 : 18;

  return {
    narrow,
    titleSize,
    titleLineHeight: Math.round(titleSize * 1.15),
    descriptionSize,
    descriptionLineHeight: Math.round(descriptionSize * 1.75),
    paddingHorizontal: narrow ? 24 : 56,
    paddingVertical: narrow ? 40 : 64,
    minHeight: narrow ? 420 : Math.max(460, Math.round((width * 9) / 16)),
  };
}

export function PublicPageHero({
  hero,
  fallbackTitle,
  fallbackDescription,
  testID,
}: {
  hero: NonNullable<PublicPageAppearance['hero']>;
  fallbackTitle: string;
  fallbackDescription: string | null;
  testID: string;
}) {
  const title = hero.title.trim() || fallbackTitle;
  const description = hero.description.trim() || fallbackDescription;
  // Until measured, the desktop column width (MaxContentWidth) is assumed.
  const [width, setWidth] = useState(752);
  const layout = heroLayout(width);

  function handleLayout(event: LayoutChangeEvent) {
    const measured = Math.round(event.nativeEvent.layout.width);
    if (measured > 0 && measured !== width) {
      setWidth(measured);
    }
  }

  return (
    <View testID={testID} onLayout={handleLayout} style={[styles.hero, { minHeight: layout.minHeight }]}>
      <Image testID={`${testID}-image`} source={{ uri: hero.image_url }} resizeMode="cover" style={StyleSheet.absoluteFill} />
      <View
        testID={`${testID}-copy`}
        style={[styles.overlay, { minHeight: layout.minHeight, paddingHorizontal: layout.paddingHorizontal, paddingVertical: layout.paddingVertical }]}
      >
        <ThemedText
          type="title"
          style={[styles.title, { fontSize: layout.titleSize, lineHeight: layout.titleLineHeight }]}
          testID={`${testID}-title`}
        >
          {title}
        </ThemedText>
        {description ? (
          <ThemedText
            style={[styles.description, { fontSize: layout.descriptionSize, lineHeight: layout.descriptionLineHeight, marginTop: layout.narrow ? 16 : 28 }]}
            testID={`${testID}-description`}
          >
            {description}
          </ThemedText>
        ) : null}
      </View>
    </View>
  );
}

const shadow = { textShadowColor: 'rgba(0,0,0,0.45)', textShadowOffset: { width: 0, height: 1 }, textShadowRadius: 8 };

const styles = StyleSheet.create({
  hero: {
    width: '100%',
    borderRadius: Radius.medium,
    overflow: 'hidden',
    marginBottom: 32,
    backgroundColor: '#111',
  },
  overlay: {
    justifyContent: 'center',
    backgroundColor: 'rgba(0,0,0,0.36)',
  },
  // Always light on the darkened image, whatever the page theme.
  title: { color: '#fff', fontWeight: '700', letterSpacing: 0.5, ...shadow },
  description: { color: '#f1ece6', maxWidth: 680, ...shadow },
});
