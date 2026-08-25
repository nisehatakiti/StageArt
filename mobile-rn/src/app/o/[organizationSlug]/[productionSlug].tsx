import { useQuery } from '@tanstack/react-query';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { ApiError } from '@/api/errors';
import { useAuth } from '@/auth/AuthContext';
import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { fetchPublicProductionBySlug } from '@/features/production/api';
import { useRequestProductionParticipation } from '@/features/participation/useParticipation';
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
 *
 * StageArt Web β版: adds a "参加を申請する" action, mirroring the
 * Organization public page's own Membership request UI. Unlike
 * Organization Follow/Membership state, there is no cheap "is this
 * Person already a Participant here?" list to check client-side (no
 * GET /me/participations endpoint exists this Phase - Open Item, see
 * completion report), so this screen only tracks the outcome of a
 * request made in the current session, not prior sessions.
 */
export default function PublicProductionScreen() {
  const { productionSlug } = useLocalSearchParams<{ organizationSlug: string; productionSlug: string }>();
  const router = useRouter();
  const { status } = useAuth();
  const [participantType, setParticipantType] = useState<'CAST' | 'STAFF'>('CAST');

  const query = useQuery({
    queryKey: ['public-production', productionSlug],
    queryFn: () => fetchPublicProductionBySlug(productionSlug),
    enabled: !!productionSlug,
    retry: false,
  });

  const requestParticipation = useRequestProductionParticipation();

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

            {status === 'authenticated' && (
              <ThemedView style={styles.participationSection}>
                {!requestParticipation.isSuccess ? (
                  <>
                    <ThemedView style={styles.typeRow}>
                      <TouchableOpacity
                        testID="public-production-type-cast"
                        onPress={() => setParticipantType('CAST')}
                        style={[styles.typeOption, participantType === 'CAST' && styles.typeOptionSelected]}
                      >
                        <ThemedText>出演者として</ThemedText>
                      </TouchableOpacity>
                      <TouchableOpacity
                        testID="public-production-type-staff"
                        onPress={() => setParticipantType('STAFF')}
                        style={[styles.typeOption, participantType === 'STAFF' && styles.typeOptionSelected]}
                      >
                        <ThemedText>スタッフとして</ThemedText>
                      </TouchableOpacity>
                    </ThemedView>
                    <TouchableOpacity
                      testID="public-production-request-participation"
                      onPress={() => requestParticipation.mutate({ productionId: query.data!.id, participantType })}
                      disabled={requestParticipation.isPending}
                      style={styles.secondaryButton}
                    >
                      <ThemedText style={styles.secondaryButtonText}>この公演・活動に参加を申請する</ThemedText>
                    </TouchableOpacity>
                  </>
                ) : (
                  <ThemedText testID="public-production-request-success" type="smallBold" themeColor="textSecondary">
                    参加を申請しました。管理者の承認をお待ちください。
                  </ThemedText>
                )}
                {requestParticipation.isError && (
                  <ThemedText style={styles.error}>{getErrorMessage(requestParticipation.error)}</ThemedText>
                )}
              </ThemedView>
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
  participationSection: { marginTop: Spacing.three, gap: Spacing.two },
  typeRow: { flexDirection: 'row', gap: Spacing.two },
  typeOption: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    alignItems: 'center',
  },
  typeOptionSelected: { borderColor: BrandColors.warmAmber, backgroundColor: '#FBEFDD' },
  secondaryButton: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
    alignSelf: 'flex-start',
  },
  secondaryButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  error: { color: '#a6483a' },
});
