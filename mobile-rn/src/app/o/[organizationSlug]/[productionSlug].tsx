import { useQuery } from '@tanstack/react-query';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { ApiError } from '@/api/errors';
import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { fetchPublicProductionBySlug } from '@/features/production/api';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Web First Phase 2: `stageart.top/{organization-slug}/
 * {production-slug}`'s public, unauthenticated Production page - GET
 * /productions/by-slug/{slug} (see src/types/api.ts's PublicProduction
 * docblock). The Production's own publishedAt is the sole visibility
 * gate (never cross-checked against its Organization's own publication
 * state - see PublicProductionResult's docblock), so this page is
 * reachable even if `organizationSlug` in the URL doesn't match the
 * resolved production - the fetch is by production slug alone; the
 * Organization identity shown/linked below is the Backend's own
 * resolved parent, not re-derived from the URL segment.
 */
export default function PublicProductionScreen() {
  const { productionSlug } = useLocalSearchParams<{ organizationSlug: string; productionSlug: string }>();
  const router = useRouter();

  const query = useQuery({
    queryKey: ['public-production', productionSlug],
    queryFn: () => fetchPublicProductionBySlug(productionSlug),
    enabled: !!productionSlug,
    retry: false,
  });

  return (
    <AppShell scroll>
      <ScrollView contentContainerStyle={styles.container}>
        {query.isLoading && <ActivityIndicator testID="public-production-loading" />}

        {query.isError && (
          <ThemedText testID="public-production-not-found">
            {query.error instanceof ApiError && query.error.statusCode === 404
              ? 'この公演・活動のページは見つかりませんでした。'
              : getErrorMessage(query.error)}
          </ThemedText>
        )}

        {query.data && (
          <ThemedView testID="public-production-content" style={styles.content}>
            {query.data.title_heading && (
              <ThemedText testID="public-production-title-heading" themeColor="textSecondary">
                {query.data.title_heading}
              </ThemedText>
            )}
            <ThemedText type="title" testID="public-production-name">
              {query.data.name}
            </ThemedText>

            <TouchableOpacity
              testID="public-production-organization-link"
              onPress={() => router.push(`/o/${query.data.organization.slug}` as Href)}
            >
              <ThemedText type="link" testID="public-production-organization-name">
                {query.data.organization.name}
              </ThemedText>
            </TouchableOpacity>
          </ThemedView>
        )}
      </ScrollView>
    </AppShell>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four },
  content: { gap: Spacing.two },
});
