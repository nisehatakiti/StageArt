import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Radius, Spacing, BrandColors } from '@/constants/theme';
import { useCompleteProduction, useProduction } from '@/features/production/useProductions';
import { useProductionOrganization } from '@/features/production/useProductionOrganization';
import { useCancelProductionMemberSettlement, useProductionSettlementSummary, useSettleProductionMember } from '@/features/settlement/useSettlement';
import { confirmAlert } from '@/utils/confirmAlert';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * ProductionSettlementScreen.md (Chapter 29): 精算 - one row per
 * Production Member with their confirmed Ticket Back unpaid amount
 * (based on actual Check-in attendance, not the Home estimate), settled
 * individually via "精算済みにする". Quota shortfall/買取 is shown as
 * read-only context - Blueprint defines no per-member settlement action
 * for it (Quota buyback is Production-wide only, per Phase 3's confirmed
 * decision).
 *
 * StageArt 公演終了／精算処理接続 instruction: this is also the
 * confirmed "公演終了／精算処理" sidebar destination (useNavMenu.ts), so
 * it now surfaces the two other steps of that instruction's flow using
 * only already-existing, already-implemented actions - no new Domain
 * behavior is added here:
 * - "公演を終了する（決算完了）" reuses the exact same
 *   useCompleteProduction() mutation/confirmation copy already on the
 *   Production Management top screen (productions/[id]/index.tsx) -
 *   CompleteProductionUseCase already blocks this when any member has an
 *   outstanding confirmed Ticket Back amount, so this screen's own list
 *   above is the actual guidance for what must be settled first.
 * - "必要な会計処理" links to the existing Production Accounting summary
 *   screen (Budget/Actual/差異, `/production/{id}/accounting`), shown
 *   only when the Organization has Accounting enabled - same gating
 *   condition useNavMenu.ts already uses for Organization Context's
 *   own 会計管理 entry.
 * Settlement itself intentionally still does not generate any Accounting
 * Journal Entry (Chapter 29 §8 leaves "Accounting journal details"
 * unconfirmed, and no confirmed spec for a 未収金/未払金 ledger entry on
 * Ticket Back settlement was found this round either - see this Phase's
 * report).
 */
export default function ProductionSettlementScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const { organization } = useProductionOrganization(production);
  const summaryQuery = useProductionSettlementSummary(id);
  const settleMember = useSettleProductionMember(id);
  const cancelSettlement = useCancelProductionMemberSettlement(id);
  const completeProduction = useCompleteProduction(id);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [lifecycleError, setLifecycleError] = useState<string | null>(null);

  const isPrimaryManager = !!production?.is_primary_manager;
  const canManageSettlement = isPrimaryManager || !!production?.delegate_roles?.includes('ACCOUNTING_MANAGER');

  async function handleSettle(personId: string) {
    setErrorMessage(null);
    try {
      await settleMember.mutateAsync(personId);
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  /** チェックを外してUpdate = settlement cancellation（Phase 5 §7）。
   * 直近1回分のsettleのみを取り消す。 */
  async function handleCancelSettlement(personId: string) {
    setErrorMessage(null);
    try {
      await cancelSettlement.mutateAsync(personId);
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handleCompleteProduction() {
    setLifecycleError(null);
    try {
      await completeProduction.mutateAsync();
    } catch (error) {
      setLifecycleError(getErrorMessage(error));
    }
  }

  function confirmCompleteProduction() {
    confirmAlert(
      '公演終了・精算完了',
      'この公演を終了し、決算完了（COMPLETED）にします。未精算のメンバーが残っている場合はエラーになります。よろしいですか？',
      [
        { text: 'キャンセル', style: 'cancel' },
        { text: 'OK', style: 'default', onPress: handleCompleteProduction },
      ]
    );
  }

  if (productionQuery.isLoading) {
    return <ActivityIndicator testID="production-settlement-loading" />;
  }

  if (!production) {
    return <ThemedText testID="production-settlement-not-found">この公演が見つかりません。</ThemedText>;
  }

  if (!canManageSettlement) {
    return (
      <ThemedText testID="production-settlement-forbidden">
        精算はPrimaryManagerまたは会計担当の権限を持つ担当者のみ利用できます。
      </ThemedText>
    );
  }

  const summary = summaryQuery.data;

  return (
    <>
      <ThemedText type="title" style={styles.pageTitle}>
        公演終了／精算処理
      </ThemedText>

      <View style={styles.lifecycleSection} testID="production-settlement-lifecycle">
        <ThemedText type="small" themeColor="textSecondary">
          公演の状態：{production.status === 'COMPLETED' ? '終了（決算完了）' : production.status}
        </ThemedText>
        {lifecycleError && (
          <ThemedText testID="production-settlement-lifecycle-error" style={styles.error}>
            {lifecycleError}
          </ThemedText>
        )}
        {/* 公演終了（決算完了）はProduction Lifecycle Actionそのもので、
            会計担当を含むどのDelegate Roleにも委譲されない
            PrimaryManager専用操作（canManageProduction()）のまま - Settlement
            を会計担当へ開放しても、この操作の権限範囲は変更しない。 */}
        {isPrimaryManager && production.status === 'ACTIVE' && (
          <TouchableOpacity
            testID="production-settlement-complete"
            onPress={confirmCompleteProduction}
            disabled={completeProduction.isPending}
            style={[styles.secondaryButton, completeProduction.isPending && styles.buttonDisabled]}
          >
            <ThemedText style={styles.secondaryButtonText}>公演を終了する（決算完了）</ThemedText>
          </TouchableOpacity>
        )}
      </View>

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        メンバー精算
      </ThemedText>

      {errorMessage && (
        <ThemedText testID="production-settlement-error" style={styles.error}>
          {errorMessage}
        </ThemedText>
      )}

      {summaryQuery.isLoading && <ActivityIndicator testID="production-settlement-summary-loading" />}

      {summary && summary.members.length === 0 && (
        <ThemedText testID="production-settlement-empty" themeColor="textSecondary">
          精算対象のメンバーはいません。
        </ThemedText>
      )}

      {summary && summary.members.length > 0 && (
        <View style={styles.list} testID="production-settlement-list">
          {summary.members.map((member) => {
            // 精算不要（一度もTicket Back対象になったことがない）: ✓表示のみ、
            // 操作対象外。それ以外は「精算済み」チェックボックス:
            // チェックしてUpdate=settle、外してUpdate=settlement cancellation。
            const neverRelevant = member.confirmed_ticket_back_amount === 0 && member.already_settled_amount === 0;
            const isChecked = member.last_settled_amount > 0;
            const busy = settleMember.isPending || cancelSettlement.isPending;

            return (
              <View key={member.person_id} style={styles.row} testID={`settlement-row-${member.person_id}`}>
                <View style={styles.colInfo}>
                  <ThemedText>{member.display_name ?? '（未設定）'}</ThemedText>
                  <ThemedText type="small" themeColor="textSecondary">
                    未払い金: {member.outstanding_amount}円（確定額 {member.confirmed_ticket_back_amount}円 / 精算済み {member.already_settled_amount}円）
                  </ThemedText>
                </View>
                {neverRelevant ? (
                  <ThemedText testID={`settlement-not-needed-${member.person_id}`} themeColor="textSecondary">
                    ✓ 精算不要
                  </ThemedText>
                ) : (
                  <TouchableOpacity
                    testID={`settlement-checkbox-${member.person_id}`}
                    onPress={() => (isChecked ? handleCancelSettlement(member.person_id) : handleSettle(member.person_id))}
                    disabled={busy || (!isChecked && member.outstanding_amount <= 0)}
                    style={[
                      styles.button,
                      (busy || (!isChecked && member.outstanding_amount <= 0)) && styles.buttonDisabled,
                    ]}
                  >
                    <ThemedText style={styles.buttonText}>
                      {isChecked ? '☑ 精算済み' : '☐ 精算済みにする'}
                    </ThemedText>
                  </TouchableOpacity>
                )}
              </View>
            );
          })}
        </View>
      )}

      {summary && (
        <ThemedText testID="production-settlement-quota-info" type="small" themeColor="textSecondary" style={styles.quotaInfo}>
          ノルマ未達: {summary.quota_shortfall_count}枚 / 買取額（参考）: {summary.quota_shortfall_payable}円
        </ThemedText>
      )}

      {organization?.accounting_enabled && (
        <View style={styles.accountingSection}>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            必要な会計処理
          </ThemedText>
          <TouchableOpacity
            testID="production-settlement-accounting-link"
            onPress={() => router.push(`/production/${id}/accounting` as Href)}
            style={styles.secondaryButton}
          >
            <ThemedText style={styles.secondaryButtonText}>会計（予算・実績・差異）を確認する</ThemedText>
          </TouchableOpacity>
        </View>
      )}
    </>
  );
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.two },
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.one },
  lifecycleSection: { gap: Spacing.two, marginBottom: Spacing.two },
  accountingSection: { marginTop: Spacing.three },
  secondaryButton: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
    alignSelf: 'flex-start',
  },
  secondaryButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  list: { gap: Spacing.one },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
    gap: Spacing.two,
  },
  colInfo: { flex: 1 },
  quotaInfo: { marginTop: Spacing.three },
  error: { color: '#a6483a', marginTop: Spacing.two },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.one,
    paddingHorizontal: Spacing.three,
    alignItems: 'center',
  },
  buttonDisabled: { opacity: 0.5 },
  buttonText: { color: '#fff', fontWeight: '600' },
});
