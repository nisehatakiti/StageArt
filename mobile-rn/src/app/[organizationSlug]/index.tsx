import { useQuery } from '@tanstack/react-query';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import Head from 'expo-router/head';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { ApiError } from '@/api/errors';
import { useAuth } from '@/auth/AuthContext';
import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { fetchPublicOrganizationBySlug } from '@/features/organization/api';
import { useFollowOrganization, useMyFollows } from '@/features/organization/useFollow';
import { useMyMemberships, useRequestOrganizationMembership } from '@/features/membership/useMembership';
import { useMyFavorites, useToggleFavorite } from '@/features/favorite/useFavorite';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Public Page Architecture phase
 * (docs/03-PublicPageURLAndPublicationSchedule.md, Status: Confirmed):
 * the Organization Public Page now resolves at the Web app's URL root -
 * `/{organization-slug}` - matching `https://stageart.top/{organization-slug}`'s
 * intended path shape once actually deployed there (this session cannot
 * configure the real `stageart.top` domain/DNS/hosting - see
 * `docs/CurrentStateAudit.md`). Previously served under an `/o/{slug}`
 * prefix, which now 302-redirects here for backward compatibility (see
 * `src/app/o/[organizationSlug]/index.tsx`).
 *
 * A nonexistent slug and an existing-but-unpublished Organization both
 * 404 identically Backend-side (GET /organizations/by-slug/{slug}) - this
 * screen shows the same "見つかりませんでした" message for both, by
 * design (never leaking unpublished existence).
 *
 * Reachable without a session (`docs/03-PublicPageURLAndPublicationSchedule.md`'s
 * "所属なしユーザー" - 観劇客 can browse without an account); Follow/
 * Favorite/Membership-request actions require one.
 */
export default function PublicOrganizationScreen() {
  const { organizationSlug } = useLocalSearchParams<{ organizationSlug: string }>();
  const { status } = useAuth();
  const router = useRouter();

  const query = useQuery({
    queryKey: ['public-organization', organizationSlug],
    queryFn: () => fetchPublicOrganizationBySlug(organizationSlug),
    enabled: !!organizationSlug,
    retry: false,
  });

  const myFollowsQuery = useMyFollows();
  const { follow, unfollow } = useFollowOrganization(organizationSlug);

  const isFollowing = !!query.data && !!myFollowsQuery.data?.some((f) => f.organization_id === query.data!.id);
  const followPending = follow.isPending || unfollow.isPending;

  const myMembershipsQuery = useMyMemberships();
  const requestMembership = useRequestOrganizationMembership();
  const myMembership = query.data ? myMembershipsQuery.data?.find((m) => m.organization_id === query.data!.id) : undefined;

  const myFavoritesQuery = useMyFavorites();
  const { add: addFavorite, remove: removeFavorite } = useToggleFavorite();
  const isFavorited = !!query.data && !!myFavoritesQuery.data?.some((f) => f.target_type === 'ORGANIZATION' && f.target_id === query.data!.id);

  const upcomingOrCurrent = query.data?.productions.filter((p) => p.status !== 'ARCHIVED' && p.status !== 'COMPLETED') ?? [];
  const past = query.data?.productions.filter((p) => p.status === 'ARCHIVED' || p.status === 'COMPLETED') ?? [];

  return (
    <AppShell scroll>
      {query.data && (
        <Head>
          <title>{query.data.name} | StageArt</title>
          <meta name="description" content={query.data.description ?? `${query.data.name}のStageArt公式ページ`} />
        </Head>
      )}
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

            {status === 'authenticated' ? (
              <TouchableOpacity
                testID={isFollowing ? 'public-organization-unfollow' : 'public-organization-follow'}
                disabled={followPending}
                onPress={() => (isFollowing ? unfollow.mutate(query.data!.id) : follow.mutate(query.data!.id))}
                style={[styles.followButton, isFollowing ? styles.followingButton : styles.followButtonActive]}
              >
                <ThemedText style={isFollowing ? styles.followingButtonText : styles.followButtonText}>
                  {isFollowing ? 'フォロー中' : 'フォローする'}
                </ThemedText>
              </TouchableOpacity>
            ) : (
              <TouchableOpacity testID="public-organization-login-to-follow" onPress={() => router.push('/login')}>
                <ThemedText type="link">ログインしてフォロー</ThemedText>
              </TouchableOpacity>
            )}

            {status === 'authenticated' && (
              <TouchableOpacity
                testID={isFavorited ? 'public-organization-unfavorite' : 'public-organization-favorite'}
                disabled={addFavorite.isPending || removeFavorite.isPending}
                onPress={() =>
                  isFavorited
                    ? removeFavorite.mutate({ targetType: 'ORGANIZATION', targetId: query.data!.id })
                    : addFavorite.mutate({ targetType: 'ORGANIZATION', targetId: query.data!.id })
                }
                style={styles.favoriteLink}
              >
                <ThemedText type="link">{isFavorited ? 'お気に入り解除' : 'お気に入りに追加'}</ThemedText>
              </TouchableOpacity>
            )}

            {status === 'authenticated' && (
              <ThemedView style={styles.membershipSection}>
                {myMembership?.status === 'ACTIVE' && (
                  <ThemedText testID="public-organization-member" type="smallBold" themeColor="textSecondary">
                    この団体に所属しています
                  </ThemedText>
                )}
                {myMembership?.status === 'REQUESTED' && (
                  <ThemedText testID="public-organization-request-pending" type="smallBold" themeColor="textSecondary">
                    所属を申請中です
                  </ThemedText>
                )}
                {(!myMembership || myMembership.status === 'REJECTED') && !requestMembership.isSuccess && (
                  <TouchableOpacity
                    testID="public-organization-request-membership"
                    onPress={() => requestMembership.mutate({ organizationId: query.data!.id })}
                    disabled={requestMembership.isPending}
                    style={styles.secondaryButton}
                  >
                    <ThemedText style={styles.secondaryButtonText}>この団体に所属を申請する</ThemedText>
                  </TouchableOpacity>
                )}
                {requestMembership.isSuccess && (
                  <ThemedText testID="public-organization-request-success" type="smallBold" themeColor="textSecondary">
                    所属を申請しました。管理者の承認をお待ちください。
                  </ThemedText>
                )}
                {requestMembership.isError && (
                  <ThemedText style={styles.error}>{getErrorMessage(requestMembership.error)}</ThemedText>
                )}
              </ThemedView>
            )}

            {/* docs/04-DomainModel/PublicPageUrlPolicy.md's "Next Production"
                section, simplified to a list (no dedicated Hero layout this
                round): upcoming/current productions first, then past ones -
                using Production.status as an honest proxy for "past" since
                Production has no start/end date fields yet (see
                docs/CurrentStateAudit.md). */}
            {upcomingOrCurrent.length > 0 && (
              <ThemedView style={styles.productionsSection}>
                <ThemedText type="smallBold">開催予定・公開中の公演</ThemedText>
                {upcomingOrCurrent.map((p) => (
                  <TouchableOpacity
                    key={p.id}
                    testID={`public-organization-production-${p.slug}`}
                    onPress={() => router.push(`/${query.data!.slug}/${p.slug}` as Href)}
                    style={styles.productionRow}
                  >
                    <ThemedText type="link">{p.name}</ThemedText>
                  </TouchableOpacity>
                ))}
              </ThemedView>
            )}

            {past.length > 0 && (
              <ThemedView style={styles.productionsSection}>
                <ThemedText type="smallBold">過去の公演</ThemedText>
                {past.map((p) => (
                  <TouchableOpacity
                    key={p.id}
                    testID={`public-organization-production-${p.slug}`}
                    onPress={() => router.push(`/${query.data!.slug}/${p.slug}` as Href)}
                    style={styles.productionRow}
                  >
                    <ThemedText type="link">{p.name}</ThemedText>
                  </TouchableOpacity>
                ))}
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
  description: { marginTop: Spacing.two },
  followButton: {
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
    alignSelf: 'flex-start',
    marginTop: Spacing.two,
  },
  followButtonActive: { backgroundColor: BrandColors.warmAmber },
  followButtonText: { color: '#fff', fontWeight: '600' },
  followingButton: { borderWidth: 1, borderColor: BrandColors.warmAmber },
  followingButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  membershipSection: { marginTop: Spacing.three, gap: Spacing.two },
  favoriteLink: { marginTop: Spacing.two },
  productionsSection: { marginTop: Spacing.three, gap: Spacing.two },
  productionRow: { paddingVertical: Spacing.one },
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
