import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';
import { useQueryClient } from '@tanstack/react-query';

import { ApiError } from '@/api/errors';
import { useAuth } from '@/auth/AuthContext';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { updateProduction } from '@/features/production/api';
import { useProduction } from '@/features/production/useProductions';
import { useProductionOrganization } from '@/features/production/useProductionOrganization';
import { getErrorMessage } from '@/utils/errorMessage';
import { isValidSlug } from '@/utils/slug';

/**
 * StageArt Web版 公演管理 Phase: 公演情報編集. Reuses the real
 * `PUT /productions/{id}` (updateProduction()) - name/title_heading/slug
 * are all applied by UpdateProductionUseCase (confirmed by reading it);
 * `status` is deliberately never sent (Phase 6.1 moved Status changes to
 * dedicated Lifecycle Action endpoints - a `status` key here is now
 * rejected with 422), and `published` is left untouched by this screen
 * entirely (owned by /productions/[id]/publish instead, per this
 * Phase's own screen split) by simply never including it in the request.
 *
 * Ownership is enforced server-side (UpdateProductionUseCase via
 * ProductionAuthorizationService.canManageProduction(): PrimaryManager
 * only, no ProductionDelegate exception even for PARTICIPANT_MANAGER -
 * see that service's own docblock) - `is_primary_manager` (already on
 * every Production fetch) is read here only to skip straight to that
 * same message locally, not as this screen's own access decision.
 */
export default function ProductionEditScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { apiClient } = useAuth();
  const router = useRouter();
  const queryClient = useQueryClient();
  const productionQuery = useProduction(id);

  const production = productionQuery.data;
  const { organization } = useProductionOrganization(production);
  const isPrimaryManager = !!production?.is_primary_manager;

  const [name, setName] = useState('');
  const [titleHeading, setTitleHeading] = useState('');
  const [slug, setSlug] = useState('');
  const [description, setDescription] = useState('');
  const [venueName, setVenueName] = useState('');
  const [scheduleStartDate, setScheduleStartDate] = useState('');
  const [scheduleEndDate, setScheduleEndDate] = useState('');
  const [scriptCredit, setScriptCredit] = useState('');
  const [directionCredit, setDirectionCredit] = useState('');
  const [initialized, setInitialized] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    if (production && !initialized) {
      setName(production.name);
      setTitleHeading(production.title_heading ?? '');
      setSlug(production.slug ?? '');
      setDescription(production.description ?? '');
      setVenueName(production.venue_name ?? '');
      setScheduleStartDate(production.schedule_start_date ?? '');
      setScheduleEndDate(production.schedule_end_date ?? '');
      setScriptCredit(production.script_credit ?? '');
      setDirectionCredit(production.direction_credit ?? '');
      setInitialized(true);
    }
  }, [production, initialized]);

  const slugChanged = slug !== (production?.slug ?? '');
  const slugValid = isValidSlug(slug);
  const canSubmit = !!name.trim() && (!slugChanged || slugValid) && !submitting;

  async function handleSubmit() {
    if (!production || !canSubmit) {
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);

    try {
      const trimmedDescription = description.trim() || null;
      const trimmedVenue = venueName.trim() || null;
      const trimmedScript = scriptCredit.trim() || null;
      const trimmedDirection = directionCredit.trim() || null;
      const trimmedStart = scheduleStartDate.trim() || null;
      const trimmedEnd = scheduleEndDate.trim() || null;
      const now = new Date().toISOString();

      await updateProduction(apiClient, production.id, {
        name: name.trim(),
        titleHeading: titleHeading.trim() ? titleHeading.trim() : null,
        slug: slugChanged ? slug : undefined,
        description: trimmedDescription,
        // §20.5: each section keeps its own publication date/time once
        // set (a scheduled/future date is not overwritten just because
        // the section was saved again); the first time a section gets
        // non-empty content, it publishes immediately. A dedicated
        // per-section date/time picker (scheduling publication for a
        // future moment) is not built this Phase - see this Phase's
        // report.
        descriptionPublishedAt: trimmedDescription ? production.description_published_at ?? now : null,
        venueName: trimmedVenue,
        venuePublishedAt: trimmedVenue ? production.venue_published_at ?? now : null,
        scheduleStartDate: trimmedStart,
        scheduleEndDate: trimmedEnd,
        schedulePublishedAt: trimmedStart || trimmedEnd ? production.schedule_published_at ?? now : null,
        scriptCredit: trimmedScript,
        directionCredit: trimmedDirection,
        scriptDirectionPublishedAt: trimmedScript || trimmedDirection ? production.script_direction_published_at ?? now : null,
      });
      await queryClient.invalidateQueries({ queryKey: ['production', production.id] });
      await queryClient.invalidateQueries({ queryKey: ['productions'] });
      router.replace({ pathname: `/productions/${production.id}`, params: { saved: '1' } } as Href);
    } catch (error) {
      if (error instanceof ApiError && error.code === 'stageart_production_slug_taken') {
        setErrorMessage('このSlugは既に使用されています。別のSlugを入力してください。');
      } else if (error instanceof ApiError && error.statusCode === 403) {
        setErrorMessage('この公演を編集する権限がありません（PrimaryManagerのみ編集できます）。');
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
    { label: '公演情報編集' },
  ];

  if (productionQuery.isLoading) {
    return (
      <>
        <ActivityIndicator testID="production-edit-loading" />
      </>
    );
  }

  if (!production) {
    return (
      <>
        <ThemedText testID="production-edit-not-found">この公演が見つかりません。</ThemedText>
      </>
    );
  }

  if (!isPrimaryManager) {
    return (
      <>
        <ThemedText testID="production-edit-forbidden">この公演を編集する権限がありません（PrimaryManagerのみ編集できます）。</ThemedText>
      </>
    );
  }

  return (
    <>
      <ThemedText type="title" style={styles.pageTitle}>
        公演情報編集
      </ThemedText>

      <View style={styles.form}>
        <ThemedText type="small" themeColor="textSecondary">
          公演肩書（任意）
        </ThemedText>
        <ThemedTextInput testID="production-edit-title-heading" value={titleHeading} onChangeText={setTitleHeading} style={styles.input} />

        <ThemedText type="small" themeColor="textSecondary">
          公演名
        </ThemedText>
        <ThemedTextInput testID="production-edit-name" value={name} onChangeText={setName} style={styles.input} />

        <ThemedText type="small" themeColor="textSecondary">
          Slug（公開URLに使用）
        </ThemedText>
        <ThemedTextInput
          testID="production-edit-slug"
          value={slug}
          onChangeText={setSlug}
          autoCapitalize="none"
          autoCorrect={false}
          style={styles.input}
        />
        {!slugValid && slug.length > 0 && (
          <ThemedText type="small" style={styles.error}>
            Slugは半角英数字とハイフンのみ、3〜64文字で入力してください。
          </ThemedText>
        )}

        <ThemedText type="small" themeColor="textSecondary">
          公演説明（任意）
        </ThemedText>
        <ThemedTextInput
          testID="production-edit-description"
          value={description}
          onChangeText={setDescription}
          multiline
          style={[styles.input, styles.multilineInput]}
        />

        <ThemedText type="small" themeColor="textSecondary">
          会場名（任意）
        </ThemedText>
        <ThemedTextInput testID="production-edit-venue" value={venueName} onChangeText={setVenueName} style={styles.input} />

        <ThemedText type="small" themeColor="textSecondary">
          日程（開始日 〜 終了日、任意・YYYY-MM-DD）
        </ThemedText>
        <View style={styles.row}>
          <ThemedTextInput
            testID="production-edit-schedule-start"
            value={scheduleStartDate}
            onChangeText={setScheduleStartDate}
            placeholder="YYYY-MM-DD"
            style={[styles.input, styles.rowInput]}
          />
          <ThemedText>〜</ThemedText>
          <ThemedTextInput
            testID="production-edit-schedule-end"
            value={scheduleEndDate}
            onChangeText={setScheduleEndDate}
            placeholder="YYYY-MM-DD"
            style={[styles.input, styles.rowInput]}
          />
        </View>

        <ThemedText type="small" themeColor="textSecondary">
          脚本 / 演出（任意）
        </ThemedText>
        <View style={styles.row}>
          <ThemedTextInput
            testID="production-edit-script-credit"
            value={scriptCredit}
            onChangeText={setScriptCredit}
            placeholder="脚本"
            style={[styles.input, styles.rowInput]}
          />
          <ThemedTextInput
            testID="production-edit-direction-credit"
            value={directionCredit}
            onChangeText={setDirectionCredit}
            placeholder="演出"
            style={[styles.input, styles.rowInput]}
          />
        </View>

        {errorMessage && (
          <ThemedText testID="production-edit-error" style={styles.error}>
            {errorMessage}
          </ThemedText>
        )}

        <View style={styles.actions}>
          <TouchableOpacity
            testID="production-edit-submit"
            onPress={handleSubmit}
            disabled={!canSubmit}
            style={[styles.button, !canSubmit && styles.buttonDisabled]}
          >
            {submitting ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>保存する</ThemedText>}
          </TouchableOpacity>
          <TouchableOpacity testID="production-edit-cancel" onPress={() => router.push(`/productions/${production.id}` as Href)}>
            <ThemedText type="link">キャンセル</ThemedText>
          </TouchableOpacity>
        </View>
      </View>
    </>
  );
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.four },
  form: { gap: Spacing.one, maxWidth: 480 },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
    marginBottom: Spacing.two,
  },
  error: { color: '#a6483a', marginBottom: Spacing.two },
  multilineInput: { minHeight: 96, textAlignVertical: 'top' },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two },
  rowInput: { flex: 1 },
  actions: { flexDirection: 'row', alignItems: 'center', gap: Spacing.four, marginTop: Spacing.two },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
});
