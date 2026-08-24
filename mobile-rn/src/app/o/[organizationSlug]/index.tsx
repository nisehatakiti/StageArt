import { useQuery } from '@tanstack/react-query';
import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet } from 'react-native';

import { ApiError } from '@/api/errors';
import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { fetchPublicOrganizationBySlug } from '@/features/organization/api';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Web First Phase 2: `stageart.top/{organization-slug}`'s
 * public, unauthenticated Organization page - GET
 * /organizations/by-slug/{slug} (see src/types/api.ts's
 * PublicOrganization docblock). A nonexistent slug and an existing-but-
 * unpublished Organization both 404 identically Backend-side, so this
 * screen shows the same "見つかりませんでした" message for both - it
 * never learns which case it actually was, by design (never leaking
 * unpublished existence).
 */
export default function PublicOrganizationScreen() {
  const { organizationSlug } = useLocalSearchParams<{ organizationSlug: string }>();

  const query = useQuery({
    queryKey: ['public-organization', organizationSlug],
    queryFn: () => fetchPublicOrganizationBySlug(organizationSlug),
    enabled: !!organizationSlug,
    retry: false,
  });

  return (
    <AppShell scroll>
      <ScrollView contentContainerStyle={styles.container}>
        {query.isLoading && <ActivityIndicator testID="public-organization-loading" />}

        {query.isError && (
          <ThemedText testID="public-organization-not-found">
            {query.error instanceof ApiError && query.error.statusCode === 404
              ? 'この団体のページは見つかりませんでした。'
              : getErrorMessage(query.error)}
          </ThemedText>
        )}

        {query.data && (
          <ThemedView testID="public-organization-content" style={styles.content}>
            <ThemedText type="title" testID="public-organization-name">
              {query.data.name}
            </ThemedText>
            {query.data.description && (
              <ThemedText testID="public-organization-description" style={styles.description}>
                {query.data.description}
              </ThemedText>
            )}
          </ThemedView>
        )}
      </ScrollView>
    </AppShell>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four },
  content: { gap: Spacing.two },
  description: { marginTop: Spacing.two },
});
