import { useRouter } from 'expo-router';
import { StyleSheet, TouchableOpacity } from 'react-native';

import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Radius, Spacing } from '@/constants/theme';

/**
 * StageArt Web First Phase 1 (docs/04-CommonNavigationDesign.md §2.2):
 * "探す" is one of the 4 fixed Bottom Navigation destinations, and leads
 * to the two existing discovery entry points (団体を探す /
 * 公演・活動を探す) - both already real, navigable routes with an honest
 * "準備中" state (no public discovery Backend API exists yet; see those
 * screens' own docblocks). This hub does not fabricate search results,
 * it only provides the confirmed navigation structure.
 */
export default function DiscoverScreen() {
  const router = useRouter();

  return (
    <AppShell>
      <ThemedView style={styles.container}>
        <ThemedText type="title" style={styles.title}>
          探す
        </ThemedText>

        <TouchableOpacity
          testID="discover-organizations-link"
          style={styles.tile}
          onPress={() => router.push('/discover-organizations')}
          accessibilityRole="button"
          accessibilityLabel="団体を探す"
        >
          <ThemedText type="smallBold">団体を探す</ThemedText>
        </TouchableOpacity>

        <TouchableOpacity
          testID="discover-productions-link"
          style={styles.tile}
          onPress={() => router.push('/discover-productions')}
          accessibilityRole="button"
          accessibilityLabel="公演・活動を探す"
        >
          <ThemedText type="smallBold">公演・活動を探す</ThemedText>
        </TouchableOpacity>
      </ThemedView>
    </AppShell>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: Spacing.four, gap: Spacing.three },
  title: { fontSize: 22, lineHeight: 28 },
  tile: {
    padding: Spacing.three,
    borderRadius: Radius.medium,
    backgroundColor: '#F7E4DE',
  },
});
