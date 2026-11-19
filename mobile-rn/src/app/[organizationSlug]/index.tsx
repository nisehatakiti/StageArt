import { useQuery } from '@tanstack/react-query';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import Head from 'expo-router/head';
import { ActivityIndicator, Linking, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { ApiError } from '@/api/errors';
import { useAuth } from '@/auth/AuthContext';
import { AppShell } from '@/components/app-shell';
import { OrganizationLogo } from '@/components/organization-logo';
import { PublicContentLayout } from '@/components/public-content-layout';
import { PublicPageHero } from '@/components/public-page-hero';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { fetchPublicOrganizationBySlug } from '@/features/organization/api';
import { PublicPageThemeProvider } from '@/features/publicPage/PublicPageTheme';
import type { PublicPageThemeValues } from '@/features/publicPage/types';
import { useFollowOrganization, useMyFollows } from '@/features/organization/useFollow';
import { useMyMemberships, useRequestOrganizationMembership } from '@/features/membership/useMembership';
import { useMyFavorites, useToggleFavorite } from '@/features/favorite/useFavorite';
import { useIsPublicSite } from '@/hooks/use-site-host';
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
const SNS_SERVICE_LABEL: Record<string, string> = {
  X: 'X',
  INSTAGRAM: 'Instagram',
  FACEBOOK: 'Facebook',
  OTHER: 'SNS',
};

export default function PublicOrganizationScreen() {
  const { organizationSlug } = useLocalSearchParams<{ organizationSlug: string }>();
  const { status } = useAuth();
  const router = useRouter();
  const isPublicSite = useIsPublicSite();

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

  // 公開ページ設定: this Organization's own theme and hero (absent from
  // older API responses - the page then looks as before).
  const pageTheme = query.data?.public_page?.theme ?? null;
  const hero = query.data?.public_page?.hero ?? null;
  const themed = pageTheme ? themedStyles(pageTheme) : null;

  // The fixed page's 公演 lists; with a saved コンテンツ配置 they are shown
  // only where its 公演一覧 is placed.
  const productionLists = query.data ? (
    <>
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
    </>
  ) : null;

  return (
    <AppShell scroll>
      {query.data && (
        <Head>
          <title>{query.data.name} | StageArt</title>
          <meta name="description" content={query.data.description ?? `${query.data.name}のStageArt公式ページ`} />
        </Head>
      )}
      <PublicPageThemeProvider theme={pageTheme}>
      <ScrollView style={themed?.page} contentContainerStyle={styles.container} testID="public-organization-page">
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
            {hero && (
              <PublicPageHero
                testID="public-organization-hero"
                hero={hero}
                fallbackTitle={query.data.name}
                fallbackDescription={query.data.description}
              />
            )}

            {query.data.logo_url && (
              // The whole logo at its own aspect ratio (contain, never
              // cover), sized to fit a box - see OrganizationLogo.
              <OrganizationLogo testID="public-organization-logo" frameTestID="public-organization-logo-frame" uri={query.data.logo_url} style={styles.logo} />
            )}

            <ThemedText type="title" testID="public-organization-name">
              {query.data.name}
            </ThemedText>
            {query.data.description && (
              <ThemedText testID="public-organization-description" style={styles.description}>
                {query.data.description}
              </ThemedText>
            )}

            {query.data.sns.length > 0 && (
              <ThemedView style={styles.snsSection} testID="public-organization-sns">
                <ThemedText type="smallBold">公式SNS</ThemedText>
                {query.data.sns.map((sns) => (
                  <TouchableOpacity
                    key={sns.id}
                    testID={`public-organization-sns-${sns.id}`}
                    onPress={() => {
                      if (sns.profile_url) {
                        Linking.openURL(sns.profile_url);
                      }
                    }}
                    disabled={!sns.profile_url}
                    style={styles.snsRow}
                  >
                    <ThemedText type="small" style={styles.snsService}>
                      {SNS_SERVICE_LABEL[sns.service] ?? sns.service}
                    </ThemedText>
                    <ThemedText type="link">{sns.display_name ?? sns.account_identifier}</ThemedText>
                  </TouchableOpacity>
                ))}
              </ThemedView>
            )}

            {query.data.external_links.length > 0 && (
              <ThemedView style={styles.linksSection} testID="public-organization-external-links">
                <ThemedText type="smallBold">リンク</ThemedText>
                {query.data.external_links.map((link) => (
                  <TouchableOpacity
                    key={link.id}
                    testID={`public-organization-external-link-${link.id}`}
                    onPress={() => Linking.openURL(link.url)}
                    style={styles.linkRow}
                  >
                    <ThemedText type="link">{link.label}</ThemedText>
                  </TouchableOpacity>
                ))}
              </ThemedView>
            )}

            {status === 'authenticated' ? (
              <TouchableOpacity
                testID={isFollowing ? 'public-organization-unfollow' : 'public-organization-follow'}
                disabled={followPending}
                onPress={() => (isFollowing ? unfollow.mutate(query.data!.id) : follow.mutate(query.data!.id))}
                style={[
                  styles.followButton,
                  isFollowing ? styles.followingButton : styles.followButtonActive,
                  themed && (isFollowing ? themed.outlineButton : themed.filledButton),
                ]}
              >
                <ThemedText
                  style={[
                    isFollowing ? styles.followingButtonText : styles.followButtonText,
                    themed && (isFollowing ? themed.outlineButtonText : themed.filledButtonText),
                  ]}
                >
                  {isFollowing ? 'フォロー中' : 'フォローする'}
                </ThemedText>
              </TouchableOpacity>
            ) : (
              // The public-site hosts never ask for a login (only the
              // application hosts own the login screen).
              !isPublicSite && (
                <TouchableOpacity testID="public-organization-login-to-follow" onPress={() => router.push('/login')}>
                  <ThemedText type="link">ログインしてフォロー</ThemedText>
                </TouchableOpacity>
              )
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
                    style={[styles.secondaryButton, themed?.outlineButton]}
                  >
                    <ThemedText style={[styles.secondaryButtonText, themed?.outlineButtonText]}>この団体に所属を申請する</ThemedText>
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

            {query.data.content_layout ? (
              // コンテンツ配置 saved in the コンテンツ tab: only what is placed,
              // in that order (StageArtCore's homepage sections).
              <PublicContentLayout
                blocks={query.data.content_layout}
                system={{
                  productions: productionLists,
                  about: query.data.description ? (
                    <ThemedText testID="public-organization-about" style={styles.about}>
                      {query.data.description}
                    </ThemedText>
                  ) : null,
                }}
                onOpenProduction={(productionSlug) => router.push(`/${query.data!.slug}/${productionSlug}` as Href)}
                colors={pageTheme ? { primary: pageTheme.primary, surface: pageTheme.surface, border: pageTheme.border } : null}
              />
            ) : (
              productionLists
            )}
          </ThemedView>
        )}
      </ScrollView>
      </PublicPageThemeProvider>
    </AppShell>
  );
}

/** StageArtCore ThemeRenderer: page background, buttons in the main color (`.stageart-button`). */
function themedStyles(theme: PublicPageThemeValues) {
  return {
    page: { backgroundColor: theme.bg },
    filledButton: { backgroundColor: theme.primary },
    filledButtonText: { color: theme.surface },
    outlineButton: { borderColor: theme.primary },
    outlineButtonText: { color: theme.primary },
  };
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four },
  content: { gap: Spacing.two },
  logo: { marginBottom: Spacing.two },
  description: { marginTop: Spacing.two },
  about: { lineHeight: 26 },
  snsSection: { marginTop: Spacing.three, gap: Spacing.one },
  snsRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two, paddingVertical: Spacing.half },
  snsService: { fontWeight: '700', minWidth: 72 },
  linksSection: { marginTop: Spacing.three, gap: Spacing.one },
  linkRow: { paddingVertical: Spacing.half },
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
