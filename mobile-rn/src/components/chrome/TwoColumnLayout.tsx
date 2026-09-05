import type { ReactNode } from 'react';
import { StyleSheet, View, useWindowDimensions } from 'react-native';

import { Spacing } from '@/constants/theme';

/**
 * StageArt Blueprint再構成 Phase 1c §6: a plain width-based responsive
 * switch (no Platform.OS branching - a wide Web window and a wide
 * tablet-ish Native window behave the same way) between dashboard.tsx's
 * former two-column layout and a single stacked column. Used by Home;
 * not a general-purpose page shell.
 */
const TWO_COLUMN_MIN_WIDTH = 860;

export function TwoColumnLayout({ main, side }: { main: ReactNode; side: ReactNode }) {
  const { width } = useWindowDimensions();

  if (width < TWO_COLUMN_MIN_WIDTH) {
    return (
      <View style={styles.stack} testID="two-column-layout-stacked">
        {main}
        {side}
      </View>
    );
  }

  return (
    <View style={styles.columns} testID="two-column-layout-columns">
      <View style={styles.mainColumn}>{main}</View>
      <View style={styles.sideColumn}>{side}</View>
    </View>
  );
}

const styles = StyleSheet.create({
  stack: { gap: Spacing.four },
  columns: { flexDirection: 'row', gap: Spacing.four, alignItems: 'flex-start' },
  mainColumn: { flex: 2, minWidth: 320, gap: Spacing.four },
  sideColumn: { flex: 1, minWidth: 260, gap: Spacing.four },
});
