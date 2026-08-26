import { useQuery } from '@tanstack/react-query';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import Head from 'expo-router/head';
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
import { useMyFavorites, useToggleFavorite } from '@/features/favorite/useFavorite';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Public Page Architecture phase
 * (docs/03-PublicPageURLAndPublicationSchedule.md, Status: Confirmed):
 * the Production Public Page now resolves at
 * `/{organization-slug}/{production-slug}` - matching
 * `https://stageart.top/{organization-slug}/{production-slug}`'s
 * intended path shape once actually deployed there (see
 * `docs/CurrentStateAudit.md` for why this session cannot configure the
 * real domain/DNS/hosting). Previously served under `/o/{slug}/{slug}`,
 * which now 302-redirects here for backward compatibility.
 *
 * GET /productions/by-slug/{slug} is the sole visibility gate (never
 * cross-checked against its Organization's own publication state - see
 * PublicProductionResult's docblock), so this page is reachable even if
 * `organizationSlug` in the URL doesn't match the resolved production -
 * the fetch is by production slug alone; the Organization identity
 * shown/linked below is the Backend's own resolved parent, not
 * re-derived from the URL segment.
 *
 * Blueprint content this phase's data model does not yet support
 * (flyer/フライヤー, 会場/venue, チケット情報, 出演者, 公演回 - see
 * docs/CurrentStateAudit.md's "Public Page architecture gap") is not
 * fabricated here - the page shows only what the Production Domain
 * Model actually has (name/titleHeading/slug/parent Organization).
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

  const myFavoritesQuery = useMyFavorites();
  const { add: addFavorite, remove: removeFavorite } = useToggleFavorite();
  const isFavorited = !!query.data && !!myFavoritesQuery.data?.some((f) => f.target_type === 'PRODUCTION' && f.target_id === query.data!.id);

  return (
    <AppShell scroll>
      {query.data && (
        <Head>
          <title>{query.data.name} | {query.data.organization.name} | StageArt</title>
          <meta name="description" content={`${query.data.organization.name}「${query.data.name}」のStageArt公式ページ`} />
        </Head>
      )}
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
              onPress={() => router.push(`/${query.data.organization.slug}` as Href)}
            >
              <ThemedText type="link" testID="public-production-organization-name">
                {query.data.organization.name}
              </ThemedText>
            </TouchableOpacity>

            {status === 'authenticated' && (
              <TouchableOpacity
                testID={isFavorited ? 'public-production-unfavorite' : 'public-production-favorite'}
                disabled={addFavorite.isPending || removeFavorite.isPending}
                onPress={() =>
                  isFavorited
                    ? removeFavorite.mutate({ targetType: 'PRODUCTION', targetId: query.data!.id })
                    : addFavorite.mutate({ targetType: 'PRODUCTION', targetId: query.data!.id })
                }
                style={styles.favoriteLink}
              >
                <ThemedText type="link">{isFavorited ? 'お気に入り解除' : 'お気に入りに追加'}</ThemedText>
              </TouchableOpacity>
            )}

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
  favoriteLink: { marginTop: Spacing.two },
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
