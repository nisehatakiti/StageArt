import { useRouter, type Href } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Radius, Spacing } from '@/constants/theme';
import { useMyFavorites, useToggleFavorite } from '@/features/favorite/useFavorite';
import { getErrorMessage } from '@/utils/errorMessage';
import type { MyFavorite } from '@/types/api';

/**
 * docs/04-DomainModel/Follow.md's "Favorite": now real (see this Phase's
 * Backend work) - replaces the earlier "準備中" placeholder from before
 * the Favorite Domain existed.
 */
export default function FavoritesScreen() {
  const router = useRouter();
  const query = useMyFavorites();
  const { remove } = useToggleFavorite();

  function targetHref(favorite: MyFavorite): Href | null {
    if (favorite.target_type === 'ORGANIZATION' && favorite.target_slug) {
      return `/o/${favorite.target_slug}` as Href;
    }
    if (favorite.target_type === 'PRODUCTION' && favorite.organization_slug && favorite.target_slug) {
      return `/o/${favorite.organization_slug}/${favorite.target_slug}` as Href;
    }
    return null;
  }

  return (
    <AppShell scroll>
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText type="title" style={styles.title}>
          お気に入り
        </ThemedText>

        {query.isLoading && <ActivityIndicator testID="favorites-loading" />}
        {query.isError && <ThemedText testID="favorites-error">{getErrorMessage(query.error)}</ThemedText>}

        {!query.isLoading && !query.isError && (query.data?.length ?? 0) === 0 && (
          <ThemedText testID="favorites-empty" themeColor="textSecondary" style={styles.body}>
            お気に入りはまだありません。団体・公演のページから「お気に入りに追加」で登録できます。
          </ThemedText>
        )}

        {(query.data?.length ?? 0) > 0 && (
          <ThemedView testID="favorites-list" style={styles.list}>
            {query.data?.map((favorite) => {
              const href = targetHref(favorite);
              return (
                <ThemedView key={favorite.id} style={styles.card} testID={`favorite-row-${favorite.id}`}>
                  <TouchableOpacity disabled={!href} onPress={() => href && router.push(href)} style={styles.cardMain}>
                    <ThemedText type="small" themeColor="textSecondary">
                      {favorite.target_type === 'ORGANIZATION' ? '団体' : '公演・活動'}
                    </ThemedText>
                    <ThemedText type="smallBold">{favorite.target_name}</ThemedText>
                  </TouchableOpacity>
                  <TouchableOpacity
                    testID={`favorite-remove-${favorite.id}`}
                    onPress={() =>
                      remove.mutate({
                        targetType: favorite.target_type as 'ORGANIZATION' | 'PRODUCTION',
                        targetId: favorite.target_id,
                      })
                    }
                    disabled={remove.isPending}
                  >
                    <ThemedText type="link">解除</ThemedText>
                  </TouchableOpacity>
                </ThemedView>
              );
            })}
          </ThemedView>
        )}
      </ScrollView>
    </AppShell>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.three },
  title: { fontSize: 22, lineHeight: 28 },
  body: { textAlign: 'center' },
  list: { gap: Spacing.two },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: Spacing.two,
  },
  cardMain: { flex: 1, gap: Spacing.half },
});
