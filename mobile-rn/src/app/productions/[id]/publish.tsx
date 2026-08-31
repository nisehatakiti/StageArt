import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';
import { useQueryClient } from '@tanstack/react-query';

import { ApiError } from '@/api/errors';
import { useAuth } from '@/auth/AuthContext';
import { WebLayout } from '@/components/web/WebLayout';
import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { updateProduction } from '@/features/production/api';
import { useProduction } from '@/features/production/useProductions';
import { useProductionOrganization } from '@/features/production/useProductionOrganization';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Web版 公演管理 Phase: 公開設定 - the screen this Phase's own
 * instruction calls its most important part, because of the previously
 * reported "公演を作成したあと、下書きから公開に進めない" acceptance-test
 * problem.
 *
 * Re-investigated from scratch for this Phase (Backend API / Frontend
 * state / TanStack Query cache / URL params / reload / public page):
 *
 * - Backend: `PUT /productions/{id}` with `published: true` works
 *   correctly and always has - ProductionRestController::update() ->
 *   UpdateProductionUseCase::execute() -> Production::publish() is a
 *   plain, unconditional state change with no defect (confirmed by
 *   reading all three this Phase).
 * - Frontend: the actual bug was entirely local to
 *   organizations/[id]/productions/create.tsx's own post-create screen -
 *   its "created" confirmation view lived only in a `useState` never
 *   tied to the URL, so a real browser reload remounted the screen from
 *   scratch and silently reset to the blank creation form, discarding
 *   all evidence the Production (and, if already clicked, its publish)
 *   had succeeded server-side. That screen's own docblock already
 *   documents its fix (`createdId` written into the URL, `useProduction()`
 *   as the reload-survival fallback) - already present in this
 *   repository's uncommitted changes and left untouched by this Phase.
 * - This screen is unaffected by that same class of bug by construction:
 *   `published_at` is never local state here at all - every render reads
 *   it straight from `useProduction(id)` (a real network fetch keyed
 *   only by the Production's own ID from the URL, not from any
 *   navigation-local state), so a reload simply re-fetches the same
 *   already-persisted Backend truth. No separate reload-survival logic
 *   was needed to write for this screen; see this Phase's report for the
 *   Playwright reload verification.
 */
export default function ProductionPublishScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { apiClient } = useAuth();
  const router = useRouter();
  const queryClient = useQueryClient();
  const productionQuery = useProduction(id);

  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const production = productionQuery.data;
  const { organization, isLoading: organizationLoading } = useProductionOrganization(production);
  const isPrimaryManager = !!production?.is_primary_manager;
  const published = !!production?.published_at;
  // Canonical root-level public URL - see
  // [organizationSlug]/[productionSlug].tsx's own docblock (`/o/{slug}/{slug}`
  // is now only a legacy redirect to this).
  const publicPath = published && organization?.slug && production?.slug ? `/${organization.slug}/${production.slug}` : null;

  async function setPublished(next: boolean) {
    if (!production) {
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);

    try {
      await updateProduction(apiClient, production.id, {
        name: production.name,
        titleHeading: production.title_heading,
        published: next,
      });
      await queryClient.invalidateQueries({ queryKey: ['production', production.id] });
      await queryClient.invalidateQueries({ queryKey: ['productions'] });
    } catch (error) {
      if (error instanceof ApiError && error.statusCode === 403) {
        setErrorMessage('この公演を公開する権限がありません（PrimaryManagerのみ操作できます）。');
      } else {
        setErrorMessage(getErrorMessage(error));
      }
    } finally {
      setSubmitting(false);
    }
  }

  const breadcrumbs = [
    { label: 'StageArt', href: '/dashboard' as Href },
    { label: '団体', href: '/organizations' as Href },
    ...(organization ? [{ label: organization.name, href: `/organizations/${organization.id}` as Href }] : []),
    ...(organization ? [{ label: '公演', href: `/organizations/${organization.id}/productions` as Href }] : []),
    { label: production?.name ?? '...', href: `/productions/${id}` as Href },
    { label: '公開設定' },
  ];

  if (productionQuery.isLoading) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" productionId={id}>
        <ActivityIndicator testID="production-publish-loading" />
      </WebLayout>
    );
  }

  if (!production) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" productionId={id}>
        <ThemedText testID="production-publish-not-found">この公演が見つかりません。</ThemedText>
      </WebLayout>
    );
  }

  if (!isPrimaryManager) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" productionId={id}>
        <ThemedText testID="production-publish-forbidden">公開設定はPrimaryManagerのみ操作できます。</ThemedText>
      </WebLayout>
    );
  }

  return (
    <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" productionId={id} productionName={production.name}>
      <ThemedText type="title" style={styles.pageTitle}>
        公開設定
      </ThemedText>

      <View style={styles.card}>
        <View style={styles.statusRow}>
          <ThemedText type="smallBold">現在の状態</ThemedText>
          <View style={[styles.pill, published ? styles.pillPublished : styles.pillDraft]} testID="production-publish-status-pill">
            <ThemedText type="small" style={published ? styles.pillTextPublished : styles.pillTextDraft}>
              {published ? '公開中' : '下書き（未公開）'}
            </ThemedText>
          </View>
        </View>

        {/* Only meaningful once published (an unpublished Production has
            no public page to link regardless), and only once the
            Organization fetch has actually settled - otherwise this
            would flash "Slug未設定" for every Production while that
            still-independent fetch is in flight. */}
        {published && !organizationLoading && !organization?.slug && (
          <ThemedText type="small" themeColor="textSecondary" testID="production-publish-no-org-slug">
            団体にSlugが設定されていないため、公開ページURLを表示できません。団体情報編集からSlugを設定してください。
          </ThemedText>
        )}
        {published && organization?.slug && !production.slug && (
          <ThemedText type="small" themeColor="textSecondary" testID="production-publish-no-production-slug">
            公演にSlugが設定されていないため、公開ページURLを表示できません。公演情報編集からSlugを設定してください。
          </ThemedText>
        )}

        {errorMessage && (
          <ThemedText testID="production-publish-error" style={styles.error}>
            {errorMessage}
          </ThemedText>
        )}

        {!published ? (
          <TouchableOpacity
            testID="production-publish-button"
            onPress={() => setPublished(true)}
            disabled={submitting}
            style={[styles.button, submitting && styles.buttonDisabled]}
          >
            {submitting ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>公開する</ThemedText>}
          </TouchableOpacity>
        ) : (
          <>
            {publicPath && (
              <TouchableOpacity testID="production-publish-view-public" onPress={() => router.push(publicPath as Href)} style={styles.buttonSecondary}>
                <ThemedText style={styles.buttonSecondaryText}>公開ページを見る（{publicPath}）</ThemedText>
              </TouchableOpacity>
            )}
            <TouchableOpacity
              testID="production-unpublish-button"
              onPress={() => setPublished(false)}
              disabled={submitting}
              style={[styles.buttonDanger, submitting && styles.buttonDisabled]}
            >
              {submitting ? <ActivityIndicator /> : <ThemedText style={styles.buttonDangerText}>公開を取り下げる</ThemedText>}
            </TouchableOpacity>
          </>
        )}
      </View>
    </WebLayout>
  );
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.four },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.four,
    gap: Spacing.two,
    maxWidth: 480,
    alignItems: 'flex-start',
  },
  statusRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two },
  pill: { paddingHorizontal: Spacing.two, paddingVertical: Spacing.half, borderRadius: Radius.medium },
  pillPublished: { backgroundColor: '#e3f3e8' },
  pillDraft: { backgroundColor: '#f7e4de' },
  pillTextPublished: { color: '#2f7a4a', fontWeight: '600' },
  pillTextDraft: { color: '#a6483a', fontWeight: '600' },
  error: { color: '#a6483a' },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.three,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
    alignSelf: 'stretch',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  buttonSecondary: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.three,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
    alignSelf: 'stretch',
  },
  buttonSecondaryText: { color: BrandColors.warmAmber, fontWeight: '600' },
  buttonDanger: {
    borderWidth: 1,
    borderColor: '#a6483a',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
    alignSelf: 'stretch',
  },
  buttonDangerText: { color: '#a6483a', fontWeight: '600' },
});
