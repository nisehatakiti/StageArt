import type { ReactNode } from 'react';
import { Linking, StyleSheet, TouchableOpacity, useWindowDimensions, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import type { PublicContentBlock, PublicContentSlot } from '@/features/publicPage/contentTypes';

/** Below this width a horizontal section stacks its columns (Core's theme does the same at its phone breakpoint). */
const NARROW_SCREEN_WIDTH = 600;
const INDENT_STEP = Spacing.four;

/**
 * The saved コンテンツ配置 of a public Organization page, rendered as
 * StageArtCore's front page renders its homepage sections: each section's
 * heading, then its slots side by side in `columns` columns (horizontal)
 * or stacked (vertical), each slot indented 0-3 steps. Only what the API
 * resolved is here - a hidden or deleted item is already an empty slot.
 *
 * The page supplies how its own system entries look (`system`), so the
 * 公演一覧 keeps exactly the lists the fixed page shows.
 */
export function PublicContentLayout({
  blocks,
  system,
  onOpenProduction,
  colors,
}: {
  blocks: PublicContentBlock[];
  system: Record<string, ReactNode>;
  onOpenProduction: (slug: string) => void;
  colors?: { primary: string; surface: string; border: string } | null;
}) {
  const { width } = useWindowDimensions();
  const narrow = width > 0 && width < NARROW_SCREEN_WIDTH;

  return (
    <View testID="public-content-layout" style={styles.layout}>
      {blocks.map((block) => (
        <View key={block.block_id} testID={`public-content-block-${block.block_id}`} style={styles.block}>
          {block.sections.map((section) => {
            const horizontal = section.layout === 'horizontal' && !narrow;

            return (
              <View key={section.section_id} testID={`public-content-section-${section.section_id}`} style={styles.section}>
                {section.heading !== '' && (
                  <View style={styles.sectionHeader}>
                    <ThemedText type="subtitle" testID={`public-content-section-${section.section_id}-heading`}>
                      {section.heading}
                    </ThemedText>
                    <View style={[styles.rule, colors && { backgroundColor: colors.primary }]} />
                  </View>
                )}
                <View style={horizontal ? styles.slotsHorizontal : styles.slotsVertical}>
                  {section.slots.map((slot, i) => (
                    <View
                      key={i}
                      testID={`public-content-section-${section.section_id}-slot-${i}`}
                      style={[
                        horizontal ? { width: `${100 / section.columns}%` as `${number}%`, paddingHorizontal: Spacing.two } : null,
                        { paddingLeft: (horizontal ? Spacing.two : 0) + slot.indent * INDENT_STEP },
                      ]}
                    >
                      <Slot slot={slot} system={system} onOpenProduction={onOpenProduction} colors={colors} testID={`public-content-section-${section.section_id}-slot-${i}`} />
                    </View>
                  ))}
                </View>
              </View>
            );
          })}
        </View>
      ))}
    </View>
  );
}

function Slot({
  slot,
  system,
  onOpenProduction,
  colors,
  testID,
}: {
  slot: PublicContentSlot;
  system: Record<string, ReactNode>;
  onOpenProduction: (slug: string) => void;
  colors?: { primary: string; surface: string; border: string } | null;
  testID: string;
}) {
  switch (slot.type) {
    case 'system':
      return <>{system[slot.ref] ?? null}</>;
    case 'production':
      return (
        <TouchableOpacity
          testID={`${testID}-production-${slot.production.slug}`}
          onPress={() => onOpenProduction(slot.production.slug)}
          style={[styles.card, colors && { borderColor: colors.border, backgroundColor: colors.surface }]}
        >
          <ThemedText type="smallBold" themeColor="textSecondary">
            公演
          </ThemedText>
          <ThemedText type="link">{slot.production.name}</ThemedText>
        </TouchableOpacity>
      );
    case 'free_content':
      return (
        <View testID={`${testID}-content`} style={[styles.card, colors && { borderColor: colors.border, backgroundColor: colors.surface }]}>
          <ThemedText type="smallBold" style={styles.contentTitle}>
            {slot.content.title}
          </ThemedText>
          {slot.content.body !== '' && <ThemedText style={styles.contentBody}>{slot.content.body}</ThemedText>}
        </View>
      );
    case 'member':
      // A member of メンバー管理, by the name it has there now.
      return (
        <View testID={`${testID}-member`} style={[styles.card, colors && { borderColor: colors.border, backgroundColor: colors.surface }]}>
          <ThemedText type="smallBold" themeColor="textSecondary">
            メンバー
          </ThemedText>
          <ThemedText type="smallBold" style={styles.contentTitle}>
            {slot.member.name}
          </ThemedText>
        </View>
      );
    case 'url':
      // Core's homepage link slot: a "詳しく見る" button to the URL.
      return (
        <TouchableOpacity
          testID={`${testID}-link`}
          accessibilityRole="link"
          onPress={() => Linking.openURL(slot.ref)}
          style={[styles.linkButton, colors && { backgroundColor: colors.primary }]}
        >
          <ThemedText style={[styles.linkButtonText, colors && { color: colors.surface }]}>詳しく見る</ThemedText>
        </TouchableOpacity>
      );
    case 'heading':
      return (
        <ThemedText type="smallBold" style={styles.slotHeading} testID={`${testID}-heading`}>
          {slot.ref}
        </ThemedText>
      );
    default:
      return null;
  }
}

const styles = StyleSheet.create({
  layout: { gap: Spacing.five, marginTop: Spacing.four },
  block: { gap: Spacing.five },
  section: { gap: Spacing.three },
  sectionHeader: { gap: Spacing.two },
  rule: { width: 48, height: 2, backgroundColor: BrandColors.warmAmber },
  slotsHorizontal: { flexDirection: 'row', flexWrap: 'wrap', marginHorizontal: -Spacing.two, rowGap: Spacing.three },
  slotsVertical: { flexDirection: 'column', gap: Spacing.three },
  card: { borderWidth: 1, borderColor: '#e1dee6', borderRadius: Radius.medium, padding: Spacing.three, gap: Spacing.one, height: '100%' },
  contentTitle: { fontSize: 18, lineHeight: 26 },
  contentBody: { lineHeight: 26 },
  slotHeading: { fontSize: 20, lineHeight: 28 },
  linkButton: { alignSelf: 'flex-start', backgroundColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.four },
  linkButtonText: { color: '#fff', fontWeight: '600' },
});
