import { useQuery } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { useAuth } from '@/auth/AuthContext';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Radius, Spacing } from '@/constants/theme';
import { useMyParticipatingProductions, useWithdrawParticipation } from '@/features/participant/useParticipant';
import { fetchProductions } from '@/features/production/api';
import type { ParticipatingProduction } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * BusinessFlowUXClarifications.md §02 ("参加している公演・活動") / §07
 * ("本人確認・承認を経て参加を確定する"): shows every Production the caller
 * is a confirmed (ACTIVE) PERSON Participant of, or a natural empty
 * state (never an error) right after registration when there are none.
 *
 * Sourced from GET /me/participating-productions
 * (ListMyParticipatingProductionsUseCase.php), which reads
 * ProductionParticipant directly - NOT GET /me/dashboard's
 * upcoming_rehearsals, which this screen used to reuse as a proxy. That
 * proxy was Attendance-based, so a Production with no upcoming
 * Rehearsal (or a Participant with no Attendance rows at all) never
 * appeared here even when the Person was genuinely an ACTIVE
 * Participant - this screen now reflects Participant status, not
 * Rehearsal scheduling.
 *
 * 参加者向け「公演概要ダッシュボード」instruction: tapping a Production
 * navigates to `/production/{id}/overview` (参加者向け公演概要), not
 * `/production/{id}/schedule` - that screen also serves as the Web
 * Production管理Context sidebar's「稽古管理」destination
 * (useNavMenu.ts) and shows Production管理操作 (タイムテーブル作成/印刷,
 * 出欠入口) that are not appropriate for a Participant arriving from
 * this list. See overview.tsx's own docblock.
 *
 * PR #7 R14-B (design doc §7.2 / §10, D9): each Production offers
 * 「参加をやめる」 (POST /participants/{id}/withdraw) behind a confirmation
 * that says the delegate roles end too when the person holds any. It is
 * not offered on a Production the person is the PrimaryManager of (they
 * hand the role over first). Whether they are the PrimaryManager / a
 * delegate comes from the existing GET /productions (confirmed by the
 * user before stage 9); that list leaves out ARCHIVED / CANCELLED
 * Productions, where the button is shown and a PrimaryManager gets the
 * Backend's 409 as the hand-over notice.
 */
export default function ParticipatingProductionsScreen() {
  const router = useRouter();
  const { apiClient, status } = useAuth();
  const participatingProductionsQuery = useMyParticipatingProductions();
  const managedProductionsQuery = useQuery({
    queryKey: ['productions'],
    queryFn: () => fetchProductions(apiClient),
    enabled: status === 'authenticated',
  });
  const withdraw = useWithdrawParticipation();
  const [confirmingId, setConfirmingId] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [withdrawnMessage, setWithdrawnMessage] = useState<string | null>(null);

  const productions = participatingProductionsQuery.data ?? [];
  const managedById = new Map((managedProductionsQuery.data ?? []).map((production) => [production.id, production]));

  async function handleWithdraw(production: ParticipatingProduction) {
    setErrors((current) => ({ ...current, [production.participant_id]: '' }));
    setWithdrawnMessage(null);
    try {
      await withdraw.mutateAsync(production.participant_id);
      setConfirmingId(null);
      setWithdrawnMessage(`「${production.production_name}」の参加をやめました。`);
    } catch (error) {
      setErrors((current) => ({ ...current, [production.participant_id]: getErrorMessage(error) }));
    }
  }

  return (
    <>
      <ThemedView style={styles.container}>
        <ThemedText type="title" style={styles.title}>
          参加している公演・活動
        </ThemedText>

        {withdrawnMessage && (
          <ThemedText testID="participating-productions-withdrawn" type="small">
            {withdrawnMessage}
          </ThemedText>
        )}

        {participatingProductionsQuery.isLoading && <ActivityIndicator testID="participating-productions-loading" />}

        {participatingProductionsQuery.isError && (
          <ThemedText testID="participating-productions-error" style={styles.body}>
            {getErrorMessage(participatingProductionsQuery.error)}
          </ThemedText>
        )}

        {!participatingProductionsQuery.isLoading && !participatingProductionsQuery.isError && productions.length === 0 && (
          <ThemedText themeColor="textSecondary" testID="participating-productions-empty" style={styles.body}>
            参加している公演・活動はありません。
          </ThemedText>
        )}

        {productions.length > 0 && (
          <ThemedView testID="participating-productions-list" style={styles.list}>
            {productions.map((production) => {
              const managed = managedById.get(production.production_id);
              const isPrimaryManager = !!managed?.is_primary_manager;
              const isDelegate = (managed?.delegate_roles?.length ?? 0) > 0;
              const confirming = confirmingId === production.participant_id;
              const error = errors[production.participant_id];
              return (
                <View key={production.participant_id} style={styles.card}>
                  <TouchableOpacity
                    testID={`participating-production-row-${production.production_id}`}
                    onPress={() => router.push(`/production/${production.production_id}/overview`)}
                  >
                    <ThemedText type="smallBold">{production.production_name}</ThemedText>
                  </TouchableOpacity>

                  {isPrimaryManager ? (
                    <ThemedText type="small" themeColor="textSecondary" testID={`participating-production-primary-manager-${production.production_id}`}>
                      公演の管理者（PrimaryManager）のため、参加をやめるには先に管理者を引き継いでください。
                    </ThemedText>
                  ) : confirming ? (
                    <View style={styles.confirm} testID={`participating-production-confirm-${production.production_id}`}>
                      <ThemedText type="small">「{production.production_name}」の参加をやめますか？</ThemedText>
                      {isDelegate && (
                        <ThemedText type="small" testID={`participating-production-delegate-note-${production.production_id}`}>
                          担当者の役割も解除されます。
                        </ThemedText>
                      )}
                      <View style={styles.confirmButtons}>
                        <TouchableOpacity
                          testID={`participating-production-withdraw-confirm-${production.production_id}`}
                          onPress={() => handleWithdraw(production)}
                          disabled={withdraw.isPending}
                          style={styles.destructiveButton}
                        >
                          {withdraw.isPending ? <ActivityIndicator size="small" color="#fff" /> : <ThemedText style={styles.destructiveButtonText}>参加をやめる</ThemedText>}
                        </TouchableOpacity>
                        <TouchableOpacity
                          testID={`participating-production-withdraw-cancel-${production.production_id}`}
                          onPress={() => setConfirmingId(null)}
                        >
                          <ThemedText type="link">キャンセル</ThemedText>
                        </TouchableOpacity>
                      </View>
                    </View>
                  ) : (
                    <TouchableOpacity
                      testID={`participating-production-withdraw-${production.production_id}`}
                      onPress={() => setConfirmingId(production.participant_id)}
                      style={styles.withdrawLink}
                    >
                      <ThemedText type="small" style={styles.destructiveText}>参加をやめる</ThemedText>
                    </TouchableOpacity>
                  )}

                  {error ? (
                    <ThemedText type="small" style={styles.destructiveText} testID={`participating-production-error-${production.production_id}`}>
                      {error}
                    </ThemedText>
                  ) : null}
                </View>
              );
            })}
          </ThemedView>
        )}
      </ThemedView>
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: Spacing.four, gap: Spacing.three },
  title: { fontSize: 22, lineHeight: 28 },
  body: { textAlign: 'center', paddingTop: Spacing.four },
  list: { gap: Spacing.two },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.one,
  },
  withdrawLink: { alignSelf: 'flex-start', marginTop: Spacing.one },
  destructiveText: { color: '#a6483a' },
  confirm: { gap: Spacing.one, marginTop: Spacing.one },
  confirmButtons: { flexDirection: 'row', alignItems: 'center', gap: Spacing.three },
  destructiveButton: {
    backgroundColor: '#a6483a',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.one,
    paddingHorizontal: Spacing.three,
  },
  destructiveButtonText: { color: '#fff', fontWeight: '600' },
});
