import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Radius, Spacing, BrandColors } from '@/constants/theme';
import { useProduction } from '@/features/production/useProductions';
import { useCancelProductionMemberSettlement, useProductionSettlementSummary, useSettleProductionMember } from '@/features/settlement/useSettlement';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * ProductionSettlementScreen.md (Chapter 29): 精算 - one row per
 * Production Member with their confirmed Ticket Back unpaid amount
 * (based on actual Check-in attendance, not the Home estimate), settled
 * individually via "精算済みにする". Quota shortfall/買取 is shown as
 * read-only context - Blueprint defines no per-member settlement action
 * for it (Quota buyback is Production-wide only, per Phase 3's confirmed
 * decision).
 */
export default function ProductionSettlementScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const summaryQuery = useProductionSettlementSummary(id);
  const settleMember = useSettleProductionMember(id);
  const cancelSettlement = useCancelProductionMemberSettlement(id);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const isPrimaryManager = !!production?.is_primary_manager;

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

  if (productionQuery.isLoading) {
    return <ActivityIndicator testID="production-settlement-loading" />;
  }

  if (!production) {
    return <ThemedText testID="production-settlement-not-found">この公演が見つかりません。</ThemedText>;
  }

  if (!isPrimaryManager) {
    return <ThemedText testID="production-settlement-forbidden">精算はPrimaryManagerのみ利用できます。</ThemedText>;
  }

  const summary = summaryQuery.data;

  return (
    <>
      <ThemedText type="title" style={styles.pageTitle}>
        精算
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
    </>
  );
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.two },
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
