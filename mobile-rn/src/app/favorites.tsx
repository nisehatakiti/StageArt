import { StyleSheet } from 'react-native';

import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';

/**
 * StageArt Web First Phase 1 (docs/04-CommonNavigationDesign.md §2.3,
 * docs/04-DomainModel/Follow.md): "お気に入り" is one of the 4 fixed
 * Bottom Navigation destinations. The underlying Favorite Domain does
 * not exist anywhere in the Backend yet (confirmed: no Favorite/Follow
 * Domain, Application, Infrastructure, or REST layer exists) - this
 * route exists now as a real, navigable entry point with an honest
 * "準備中" state, matching the same disclosed-placeholder pattern already
 * used by discover-organizations.tsx, rather than fabricating favorite
 * data or hiding the destination.
 */
export default function FavoritesScreen() {
  return (
    <AppShell>
      <ThemedView style={styles.container}>
        <ThemedText type="title" style={styles.title}>
          お気に入り
        </ThemedText>
        <ThemedText themeColor="textSecondary" testID="favorites-placeholder" style={styles.body}>
          お気に入り機能は準備中です。公開後は、お気に入りの団体・お気に入りの公演/活動をここから確認できるようになります。
        </ThemedText>
      </ThemedView>
    </AppShell>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  title: { fontSize: 22, lineHeight: 28 },
  body: { textAlign: 'center' },
});
