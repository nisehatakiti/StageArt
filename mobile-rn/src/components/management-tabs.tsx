import { StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { BrandColors, Spacing } from '@/constants/theme';

/**
 * Tabs inside one management screen (e.g. 基本情報 / 公開ページ設定 on the
 * organization edit screen) - the same split StageArtCore's
 * OrganizationAdmin makes with its tab bar. Plain buttons with an
 * underline for the active one; the screen decides what each tab shows.
 */
export function ManagementTabs<T extends string>({
  tabs,
  active,
  onChange,
  testID,
}: {
  tabs: { key: T; label: string }[];
  active: T;
  onChange: (key: T) => void;
  testID: string;
}) {
  return (
    <View style={styles.bar} testID={testID} accessibilityRole="tablist">
      {tabs.map((tab) => {
        const selected = tab.key === active;

        return (
          <TouchableOpacity
            key={tab.key}
            testID={`${testID}-${tab.key}`}
            accessibilityRole="tab"
            accessibilityState={{ selected }}
            onPress={() => onChange(tab.key)}
            style={[styles.tab, selected && styles.tabActive]}
          >
            <ThemedText type="smallBold" style={selected ? styles.labelActive : styles.label}>
              {tab.label}
            </ThemedText>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    borderBottomWidth: 1,
    borderBottomColor: '#e1dee6',
    marginBottom: Spacing.three,
    maxWidth: 720,
  },
  tab: {
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
    borderBottomWidth: 3,
    borderBottomColor: 'transparent',
    marginBottom: -1,
  },
  tabActive: { borderBottomColor: BrandColors.warmAmber },
  label: { color: '#60646C' },
  labelActive: { color: BrandColors.warmAmber },
});
