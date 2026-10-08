import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';
import { useQueryClient } from '@tanstack/react-query';

import { ApiError } from '@/api/errors';
import { useAuth } from '@/auth/AuthContext';
import { ThemedText } from '@/components/themed-text';
import { FormInput } from '@/components/form-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { updateProduction } from '@/features/production/api';
import { useCreatePerformance, usePerformances } from '@/features/performance/usePerformances';
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
  const performancesQuery = usePerformances(id);
  const createPerformance = useCreatePerformance(id);
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
  const [capacity, setCapacity] = useState('');
  const [performanceCommonRemarks, setPerformanceCommonRemarks] = useState('');
  const [newPerformanceDate, setNewPerformanceDate] = useState('');
  const [newPerformanceStartTime, setNewPerformanceStartTime] = useState('');
  const [newPerformanceEndTime, setNewPerformanceEndTime] = useState('');
  const [newPerformanceCapacity, setNewPerformanceCapacity] = useState('');
  const [newPerformanceSymbol, setNewPerformanceSymbol] = useState('');
  const [newPerformanceRemarks, setNewPerformanceRemarks] = useState('');
  const [performanceErrorMessage, setPerformanceErrorMessage] = useState<string | null>(null);
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
      setCapacity(production.capacity !== null && production.capacity !== undefined ? String(production.capacity) : '');
      setPerformanceCommonRemarks(production.performance_common_remarks ?? '');
      setInitialized(true);
    }
  }, [production, initialized]);

  const slugChanged = slug !== (production?.slug ?? '');
  const slugValid = isValidSlug(slug);
  const capacityTrimmed = capacity.trim();
  const capacityValid = capacityTrimmed === '' || (/^\d+$/.test(capacityTrimmed) && Number(capacityTrimmed) > 0);
  const canSubmit = !!name.trim() && (!slugChanged || slugValid) && capacityValid && !submitting;

  async function handleAddPerformance() {
    setPerformanceErrorMessage(null);
    if (!newPerformanceDate.trim() || !newPerformanceStartTime.trim()) {
      setPerformanceErrorMessage('公演日と開演時刻を入力してください。');
      return;
    }
    const startMinute = Number(newPerformanceStartTime.slice(3, 5));
    const endMinute = newPerformanceEndTime.trim() ? Number(newPerformanceEndTime.slice(3, 5)) : null;
    if (!/^\\d{2}:\\d{2}$/.test(newPerformanceStartTime) || startMinute % 5 !== 0) {
      setPerformanceErrorMessage('開演時刻の分は5分刻みで入力してください。');
      return;
    }
    if (newPerformanceEndTime.trim() && (!/^\\d{2}:\\d{2}$/.test(newPerformanceEndTime) || endMinute === null || endMinute % 5 !== 0)) {
      setPerformanceErrorMessage('終演予定時刻の分は5分刻みで入力してください。');
      return;
    }
    try {
      await createPerformance.mutateAsync({
        performanceDate: newPerformanceDate.trim(),
        startTime: newPerformanceStartTime.trim(),
        endTime: newPerformanceEndTime.trim() || undefined,
        capacity: newPerformanceCapacity.trim() ? Number(newPerformanceCapacity.trim()) : undefined,
        symbol: newPerformanceSymbol.trim() || undefined,
        remarks: newPerformanceRemarks.trim() || undefined,
      });
      setNewPerformanceDate('');
      setNewPerformanceStartTime('');
      setNewPerformanceEndTime('');
      setNewPerformanceCapacity('');
      setNewPerformanceSymbol('');
      setNewPerformanceRemarks('');
    } catch (error) {
      if (error instanceof ApiError && error.code === 'stageart_performance_duplicate_datetime') {
        setPerformanceErrorMessage('同じ公演日・開演時刻の公演スケジュールは登録できません。');
      } else {
        setPerformanceErrorMessage(getErrorMessage(error));
      }
    }
  }

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
        capacity: capacityTrimmed === '' ? null : Number(capacityTrimmed),
        performanceCommonRemarks: performanceCommonRemarks.trim() || null,
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
      <ScrollView contentContainerStyle={styles.pageContainer}>
      <ThemedText type="title" style={styles.pageTitle}>
        公演情報編集
      </ThemedText>

      <View style={styles.form}>
        <ThemedText type="small" themeColor="textSecondary">
          公演冠（任意）
        </ThemedText>
        <FormInput testID="production-edit-title-heading" value={titleHeading} onChangeText={setTitleHeading} style={[styles.input, styles.inputTitle]} />

        <ThemedText type="small" themeColor="textSecondary">
          公演名
        </ThemedText>
        <FormInput testID="production-edit-name" value={name} onChangeText={setName} style={[styles.input, styles.inputName]} />

        <ThemedText type="small" themeColor="textSecondary">
          Slug（公開URLに使用）
        </ThemedText>
        <FormInput
          testID="production-edit-slug"
          value={slug}
          onChangeText={setSlug}
          autoCapitalize="none"
          autoCorrect={false}
          style={[styles.input, styles.inputSlug]}
        />
        {!slugValid && slug.length > 0 && (
          <ThemedText type="small" style={styles.error}>
            Slugは半角英数字とハイフンのみ、3〜64文字で入力してください。
          </ThemedText>
        )}

        <ThemedText type="small" themeColor="textSecondary">
          公演説明（任意）
        </ThemedText>
        <FormInput
          kind="textarea"
          testID="production-edit-description"
          value={description}
          onChangeText={setDescription}
          multiline
          style={[styles.input, styles.multilineInput, styles.inputLong]}
        />

        <ThemedText type="small" themeColor="textSecondary">
          会場名（任意）
        </ThemedText>
        <FormInput testID="production-edit-venue" value={venueName} onChangeText={setVenueName} style={[styles.input, styles.inputVenue]} />

        <ThemedText type="small" themeColor="textSecondary">
          日程（開始日 〜 終了日、任意・YYYY-MM-DD）
        </ThemedText>
        <View style={styles.row}>
          <FormInput
            kind="date"
            testID="production-edit-schedule-start"
            value={scheduleStartDate}
            onChangeText={setScheduleStartDate}
            placeholder="YYYY-MM-DD"
            style={[styles.input, styles.rowInput, styles.inputDate]}
          />
          <ThemedText>〜</ThemedText>
          <FormInput
            kind="date"
            testID="production-edit-schedule-end"
            value={scheduleEndDate}
            onChangeText={setScheduleEndDate}
            placeholder="YYYY-MM-DD"
            style={[styles.input, styles.rowInput, styles.inputDate]}
          />
        </View>

        <ThemedText type="small" themeColor="textSecondary">
          脚本 / 演出（任意）
        </ThemedText>
        <View style={styles.row}>
          <FormInput
            testID="production-edit-script-credit"
            value={scriptCredit}
            onChangeText={setScriptCredit}
            placeholder="脚本"
            style={[styles.input, styles.rowInput, styles.inputPerson]}
          />
          <FormInput
            testID="production-edit-direction-credit"
            value={directionCredit}
            onChangeText={setDirectionCredit}
            placeholder="演出"
            style={[styles.input, styles.rowInput, styles.inputPerson]}
          />
        </View>

        <ThemedText type="small" themeColor="textSecondary">
          収容人数（任意・非公開）
        </ThemedText>
        <FormInput
          testID="production-edit-capacity"
          value={capacity}
          onChangeText={setCapacity}
          keyboardType="number-pad"
          style={[styles.input, styles.inputNumber]}
        />
        <ThemedText type="small" themeColor="textSecondary" style={styles.caption}>
          変更すると、この公演のすべての公演スケジュールの収容人数が一括で上書きされます（個別に変更済みの公演スケジュールも含む）。
        </ThemedText>
        {!capacityValid && (
          <ThemedText type="small" style={styles.error}>
            収容人数は1以上の整数で入力してください。
          </ThemedText>
        )}

        <ThemedText type="subtitle" style={styles.sectionTitle}>
          公演スケジュール
        </ThemedText>
        <ThemedText type="small" themeColor="textSecondary" style={styles.caption}>
          公演スケジュールはここから追加できます。時刻の分は5分刻みです。
        </ThemedText>

        {performancesQuery.data && performancesQuery.data.length > 0 && (
          <View style={styles.performanceList} testID="production-edit-performance-list">
            {performancesQuery.data.map((performance) => (
              <View key={performance.id} style={styles.performanceRow}>
                <ThemedText style={styles.performanceDate}>{formatDisplayDate(performance.performance_date)}</ThemedText>
                <ThemedText style={styles.performanceTime}>
                  {performance.start_time.slice(0, 5)}
                  {performance.end_time ? `〜${performance.end_time.slice(0, 5)}` : ''}
                </ThemedText>
                {performance.symbol && <ThemedText type="small">{performance.symbol}</ThemedText>}
                <ThemedText type="small" themeColor="textSecondary">
                  {performance.status === 'CANCELLED' ? '中止' : performance.status === 'SOLD_OUT' ? '満席' : performance.status === 'FINISHED' ? '終了' : '公開中'}
                </ThemedText>
              </View>
            ))}
          </View>
        )}

        <ThemedText type="small" themeColor="textSecondary">
          公演日
        </ThemedText>
        <FormInput
          kind="date"
          testID="production-edit-performance-date"
          value={newPerformanceDate}
          onChangeText={setNewPerformanceDate}
          style={[styles.input, styles.inputDate]}
        />
        <ThemedText type="small" themeColor="textSecondary">
          開演時刻
        </ThemedText>
        <FormInput
          kind="time"
          step={300}
          testID="production-edit-performance-start-time"
          value={newPerformanceStartTime}
          onChangeText={setNewPerformanceStartTime}
          style={[styles.input, styles.inputTime]}
        />
        <ThemedText type="small" themeColor="textSecondary">
          終演予定時刻（任意）
        </ThemedText>
        <FormInput
          kind="time"
          step={300}
          testID="production-edit-performance-end-time"
          value={newPerformanceEndTime}
          onChangeText={setNewPerformanceEndTime}
          style={[styles.input, styles.inputTime]}
        />
        <ThemedText type="small" themeColor="textSecondary">
          定員（任意）
        </ThemedText>
        <FormInput
          testID="production-edit-performance-capacity"
          value={newPerformanceCapacity}
          onChangeText={setNewPerformanceCapacity}
          keyboardType="number-pad"
          placeholder={production.capacity != null ? String(production.capacity) : ''}
          style={[styles.input, styles.inputNumber]}
        />
        <ThemedText type="small" themeColor="textSecondary">
          記号（任意）
        </ThemedText>
        <FormInput
          testID="production-edit-performance-symbol"
          value={newPerformanceSymbol}
          onChangeText={setNewPerformanceSymbol}
          style={[styles.input, styles.inputSymbol]}
        />
        <ThemedText type="small" themeColor="textSecondary">
          備考（任意）
        </ThemedText>
        <FormInput
          kind="textarea"
          testID="production-edit-performance-remarks"
          value={newPerformanceRemarks}
          onChangeText={setNewPerformanceRemarks}
          multiline
          style={[styles.input, styles.multilineInput, styles.inputLong]}
        />
        {performanceErrorMessage && (
          <ThemedText testID="production-edit-performance-error" style={styles.error}>
            {performanceErrorMessage}
          </ThemedText>
        )}
        <TouchableOpacity
          testID="production-edit-performance-add"
          onPress={handleAddPerformance}
          disabled={createPerformance.isPending}
          style={[styles.button, createPerformance.isPending && styles.buttonDisabled]}
        >
          {createPerformance.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>＋ 公演スケジュールを追加</ThemedText>}
        </TouchableOpacity>

        <ThemedText type="small" themeColor="textSecondary">
          公演スケジュール共通備考（任意）
        </ThemedText>
        <FormInput
          kind="textarea"
          testID="production-edit-performance-common-remarks"
          value={performanceCommonRemarks}
          onChangeText={setPerformanceCommonRemarks}
          multiline
          style={[styles.input, styles.multilineInput, styles.inputLong]}
        />

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
      </ScrollView>
    </>
  );
}

function formatDisplayDate(value: string): string {
  const match = value.match(/^(\\d{4})-(\\d{2})-(\\d{2})$/);
  return match ? `${match[1]}/${match[2]}/${match[3]}` : value;
}

const styles = StyleSheet.create({
  pageContainer: { padding: Spacing.five, paddingBottom: Spacing.six },
  pageTitle: { marginBottom: Spacing.four, fontSize: 32, lineHeight: 40 },
  form: { gap: Spacing.one, width: '100%', maxWidth: 960 },
  input: {
    borderWidth: 1,
    borderColor: '#d7d2ca',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
    marginBottom: Spacing.two,
    maxWidth: '100%',
  },
  inputTitle: { width: 360 },
  inputName: { width: 520 },
  inputSlug: { width: 360 },
  inputVenue: { width: 360 },
  inputDate: { width: 180 },
  inputPerson: { width: 320 },
  inputNumber: { width: 160 },
  inputTime: { width: 140 },
  inputSymbol: { width: 220 },
  performanceList: { width: '100%', maxWidth: 760, gap: Spacing.one, marginBottom: Spacing.three },
  performanceRow: { flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: Spacing.two, paddingVertical: Spacing.two, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: '#eee' },
  performanceDate: { width: 120, fontWeight: '600' },
  performanceTime: { width: 130 },
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.one },
  inputLong: { width: '100%', maxWidth: 760 },
  error: { color: '#a6483a', marginBottom: Spacing.two },
  caption: { marginBottom: Spacing.two },
  multilineInput: { minHeight: 120, textAlignVertical: 'top' },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two, flexWrap: 'wrap' },
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
  button: { backgroundColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.four, alignItems: 'center', alignSelf: 'flex-start', marginBottom: Spacing.three },
  buttonText: { color: '#fff', fontWeight: '600' },
});
