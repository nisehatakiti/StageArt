import { useQuery } from '@tanstack/react-query';
import { useLocalSearchParams, useRouter } from 'expo-router';
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
 *
 * StageArt Follow: the page itself stays reachable without a session
 * (docs/03-FollowAndHomeExperience.md's "一般観客も主要利用者"), but
 * Follow itself requires one - an unauthenticated visitor sees a
 * "ログインしてフォロー" prompt instead of the Follow toggle.
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
