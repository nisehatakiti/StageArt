import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import {
  useCheckInByNumber,
  useCheckInReservation,
  useCreateWalkUpReservation,
  useMarkNoShow,
  useReverseCheckIn,
  useSearchReservationsForCheckIn,
} from '@/features/checkin/useCheckIn';
import { useParticipants } from '@/features/participant/useParticipant';
import { usePerformances } from '@/features/performance/usePerformances';
import { useProduction } from '@/features/production/useProductions';
import { useTickets } from '@/features/ticket/useTickets';
import type { Reservation } from '@/types/api';
import { confirmAlert } from '@/utils/confirmAlert';
import { getErrorMessage } from '@/utils/errorMessage';

const STATUS_LABEL: Record<Reservation['status'], string> = {
  RESERVED: '未受付',
  CHECKED_IN: '受付済み',
  CANCELLED: 'キャンセル済み',
  NO_SHOW: '不参加（連絡済み）',
};

/** Phase 0-4統合監査 P1-3: one fresh identifier per confirmed walk-up
 * action, so a retried/double-submitted confirmation reuses the same
 * server-side result instead of registering twice. Same fallback shape
 * already used by GoogleSignInButtonWeb.tsx's generateNonce() for
 * environments without crypto.randomUUID. */
function generateIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return crypto.randomUUID();
  }
  return `walkup-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

/**
 * CheckIn.md/CheckInConsistencyPolicy.md (Phase 4 Check-in/精算/会計連携):
 * the reception-desk screen - select today's Performance, search a
 * Reservation by Number/氏名 or type a scanned/typed Reservation Number
 * (CheckIn.md's own "QR Check InとManual Selectionは同じCheck In Factとして
 * 扱う" - this UI does not distinguish a "camera scan" from typing the
 * same code by hand, since QR Scanning itself is explicitly a UI/
 * Infrastructure concern this Phase does not build a camera view for),
 * Check-in/no-show/Check-in-cancel per Reservation, and sell+immediately
 * check in a 当日券 (walk-up ticket).
 */
export default function ProductionCheckInScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const performancesQuery = usePerformances(id);
  const ticketsQuery = useTickets(id);
  const participantsQuery = useParticipants(id);

  const isPrimaryManager = !!production?.is_primary_manager;
  const canManage = isPrimaryManager || production?.delegate_role === 'CHECKIN_MANAGER';

  const performances = performancesQuery.data ?? [];
  const [selectedPerformanceId, setSelectedPerformanceId] = useState<string | null>(null);
  const activePerformanceId = selectedPerformanceId ?? performances[0]?.id ?? null;

  const [keyword, setKeyword] = useState('');
  const [numberEntry, setNumberEntry] = useState('');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const searchQuery = useSearchReservationsForCheckIn(activePerformanceId ?? undefined, keyword);
  const checkIn = useCheckInReservation(activePerformanceId ?? undefined);
  const checkInByNumber = useCheckInByNumber(activePerformanceId ?? undefined);
  const markNoShow = useMarkNoShow(activePerformanceId ?? undefined);
  const reverseCheckIn = useReverseCheckIn(activePerformanceId ?? undefined);

  const [walkUpTicketId, setWalkUpTicketId] = useState('');
  const [walkUpName, setWalkUpName] = useState('');
  const [walkUpEmail, setWalkUpEmail] = useState('');
  const [walkUpGuestCount, setWalkUpGuestCount] = useState('1');
  const [walkUpAttributedPersonId, setWalkUpAttributedPersonId] = useState<string | null>(null);
  const createWalkUp = useCreateWalkUpReservation(activePerformanceId ?? undefined);

  const memberParticipants = (participantsQuery.data ?? []).filter(
    (p) => p.subject_type === 'PERSON' && p.status === 'ACTIVE'
  );

  async function handleCheckIn(reservationId: string) {
    setErrorMessage(null);
    setMessage(null);
    try {
      const result = await checkIn.mutateAsync(reservationId);
      setMessage(result.already_processed ? '受付済みです。' : '受付を完了しました。');
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handleCheckInByNumber() {
    setErrorMessage(null);
    setMessage(null);
    try {
      const result = await checkInByNumber.mutateAsync(numberEntry.trim());
      setMessage(result.already_processed ? '受付済みです。' : '受付を完了しました。');
      setNumberEntry('');
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handleNoShow(reservationId: string) {
    setErrorMessage(null);
    setMessage(null);
    try {
      await markNoShow.mutateAsync(reservationId);
      setMessage('不参加として記録しました。');
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handleReverse(reservationId: string) {
    setErrorMessage(null);
    setMessage(null);
    try {
      await reverseCheckIn.mutateAsync(reservationId);
      setMessage('受付を取り消しました。');
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  /**
   * Phase 0-4統合監査 P1-3: 当日券登録は「入力→OK→確認ポップアップ→OK→登録」
   * の2段階操作にする。この関数は第1段階の「OK」で呼ばれ、まだ何も登録しな
   * い - 内容を確認ポップアップ（confirmAlert、既存のコメント削除確認等と
   * 同じ仕組み）に表示し、そこでの最終「OK」でのみ実際にperformWalkUp()を
   * 呼ぶ。ポップアップの「キャンセル」は登録処理を一切実行しない。
   */
  function handleWalkUpFirstStage() {
    const selectedTicket = activeTickets.find((t) => t.id === walkUpTicketId);
    const guestCount = Number(walkUpGuestCount.trim()) || 1;
    const attributionLabel = walkUpAttributedPersonId
      ? memberParticipants.find((p) => p.subject_id === walkUpAttributedPersonId)?.display_name ?? '（選択したメンバー）'
      : '未設定';
    const idempotencyKey = generateIdempotencyKey();

    confirmAlert(
      '当日券の登録確認',
      `当日券${guestCount}名を登録します。\n\nチケット種別：${selectedTicket?.name ?? ''}\n誰扱い：${attributionLabel}`,
      [
        { text: 'キャンセル', style: 'cancel' },
        { text: 'OK', style: 'default', onPress: () => performWalkUp(guestCount, idempotencyKey) },
      ]
    );
  }

  /** 第2段階の最終「OK」でのみ実行される、実際の登録処理。 */
  async function performWalkUp(guestCount: number, idempotencyKey: string) {
    setErrorMessage(null);
    setMessage(null);
    try {
      await createWalkUp.mutateAsync({
        ticketId: walkUpTicketId,
        bookerName: walkUpName.trim(),
        bookerEmail: walkUpEmail.trim(),
        guestCount,
        attributedPersonId: walkUpAttributedPersonId,
        idempotencyKey,
      });
      setMessage('当日券を発行し、受付を完了しました。');
      setWalkUpName('');
      setWalkUpEmail('');
      setWalkUpGuestCount('1');
      setWalkUpAttributedPersonId(null);
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  if (productionQuery.isLoading) {
    return <ActivityIndicator testID="production-checkin-loading" />;
  }

  if (!production) {
    return <ThemedText testID="production-checkin-not-found">この公演が見つかりません。</ThemedText>;
  }

  if (!canManage) {
    return (
      <ThemedText testID="production-checkin-forbidden">
        受付（Check-in）はPrimaryManagerまたは受付担当の権限を持つ担当者のみ利用できます。
      </ThemedText>
    );
  }

  const activeTickets = (ticketsQuery.data ?? []).filter((t) => t.status === 'ACTIVE');
  const reservations = searchQuery.data ?? [];

  return (
    <>
      <ThemedText type="title" style={styles.pageTitle}>
        受付（Check-in）
      </ThemedText>

      {errorMessage && (
        <ThemedText testID="production-checkin-error" style={styles.error}>
          {errorMessage}
        </ThemedText>
      )}
      {message && (
        <ThemedText testID="production-checkin-message" style={styles.message}>
          {message}
        </ThemedText>
      )}

      <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
        対象公演回
      </ThemedText>
      <View style={styles.performanceRow} testID="production-checkin-performance-list">
        {performances.map((performance) => (
          <TouchableOpacity
            key={performance.id}
            testID={`production-checkin-performance-${performance.id}`}
            onPress={() => setSelectedPerformanceId(performance.id)}
            style={[styles.performanceChip, activePerformanceId === performance.id && styles.performanceChipActive]}
          >
            <ThemedText>
              {performance.performance_date} {performance.start_time}
              {performance.symbol ? ` (${performance.symbol})` : ''}
            </ThemedText>
          </TouchableOpacity>
        ))}
      </View>

      {!activePerformanceId && (
        <ThemedText testID="production-checkin-no-performance" themeColor="textSecondary">
          先に公演回管理で公演回を登録してください。
        </ThemedText>
      )}

      {activePerformanceId && (
        <>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            予約番号で受付
          </ThemedText>
          <ThemedTextInput
            testID="production-checkin-number-entry"
            value={numberEntry}
            onChangeText={setNumberEntry}
            placeholder="予約番号（QRコードの内容）"
            style={styles.input}
          />
          <TouchableOpacity
            testID="production-checkin-number-submit"
            onPress={handleCheckInByNumber}
            disabled={!numberEntry.trim() || checkInByNumber.isPending}
            style={[styles.button, (!numberEntry.trim() || checkInByNumber.isPending) && styles.buttonDisabled]}
          >
            <ThemedText style={styles.buttonText}>受付する</ThemedText>
          </TouchableOpacity>

          <ThemedText type="subtitle" style={styles.sectionTitle}>
            予約検索
          </ThemedText>
          <ThemedTextInput
            testID="production-checkin-search-keyword"
            value={keyword}
            onChangeText={setKeyword}
            placeholder="予約番号または氏名"
            style={styles.input}
          />

          {searchQuery.isLoading && <ActivityIndicator testID="production-checkin-search-loading" />}

          {reservations.length === 0 && !searchQuery.isLoading && (
            <ThemedText testID="production-checkin-search-empty" themeColor="textSecondary">
              該当する予約がありません。
            </ThemedText>
          )}

          <View style={styles.list} testID="production-checkin-search-results">
            {reservations.map((reservation) => (
              <View key={reservation.id} style={styles.row} testID={`checkin-reservation-row-${reservation.id}`}>
                <View style={styles.colInfo}>
                  <ThemedText>
                    {reservation.reservation_number} - {reservation.booker_name}（{reservation.guest_count}名）
                  </ThemedText>
                  <ThemedText type="small" themeColor="textSecondary">
                    {STATUS_LABEL[reservation.status]}
                  </ThemedText>
                </View>
                <View style={styles.actionButtons}>
                  {reservation.status === 'RESERVED' && (
                    <>
                      <TouchableOpacity testID={`checkin-action-checkin-${reservation.id}`} onPress={() => handleCheckIn(reservation.id)}>
                        <ThemedText type="link">受付</ThemedText>
                      </TouchableOpacity>
                      <TouchableOpacity testID={`checkin-action-noshow-${reservation.id}`} onPress={() => handleNoShow(reservation.id)}>
                        <ThemedText type="link">不参加</ThemedText>
                      </TouchableOpacity>
                    </>
                  )}
                  {reservation.status === 'CHECKED_IN' && (
                    <TouchableOpacity testID={`checkin-action-reverse-${reservation.id}`} onPress={() => handleReverse(reservation.id)}>
                      <ThemedText type="link">受付取消</ThemedText>
                    </TouchableOpacity>
                  )}
                </View>
              </View>
            ))}
          </View>

          <ThemedText type="subtitle" style={styles.sectionTitle}>
            当日券
          </ThemedText>

          <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
            チケット種別
          </ThemedText>
          <View style={styles.radioRow}>
            {activeTickets.map((ticket) => (
              <TouchableOpacity key={ticket.id} testID={`walkup-ticket-${ticket.id}`} onPress={() => setWalkUpTicketId(ticket.id)} style={styles.radioOption}>
                <ThemedText>
                  {walkUpTicketId === ticket.id ? '◉' : '○'} {ticket.name}（{ticket.price}円）
                </ThemedText>
              </TouchableOpacity>
            ))}
          </View>

          <ThemedText type="small" themeColor="textSecondary">
            購入者氏名
          </ThemedText>
          <ThemedTextInput testID="walkup-booker-name" value={walkUpName} onChangeText={setWalkUpName} style={styles.input} />

          <ThemedText type="small" themeColor="textSecondary">
            連絡先メールアドレス
          </ThemedText>
          <ThemedTextInput testID="walkup-booker-email" value={walkUpEmail} onChangeText={setWalkUpEmail} style={styles.input} />

          <ThemedText type="small" themeColor="textSecondary">
            人数
          </ThemedText>
          <ThemedTextInput
            testID="walkup-guest-count"
            value={walkUpGuestCount}
            onChangeText={setWalkUpGuestCount}
            keyboardType="number-pad"
            style={styles.input}
          />

          <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
            誰扱い（任意）
          </ThemedText>
          <View style={styles.radioRow}>
            <TouchableOpacity testID="walkup-attribution-none" onPress={() => setWalkUpAttributedPersonId(null)} style={styles.radioOption}>
              <ThemedText>{walkUpAttributedPersonId === null ? '◉' : '○'} 未設定</ThemedText>
            </TouchableOpacity>
            {memberParticipants.map((participant) => (
              <TouchableOpacity
                key={participant.id}
                testID={`walkup-attribution-${participant.subject_id}`}
                onPress={() => setWalkUpAttributedPersonId(participant.subject_id)}
                style={styles.radioOption}
              >
                <ThemedText>
                  {walkUpAttributedPersonId === participant.subject_id ? '◉' : '○'} {participant.display_name ?? '（名前未設定）'}
                </ThemedText>
              </TouchableOpacity>
            ))}
          </View>

          <TouchableOpacity
            testID="production-checkin-walkup-submit"
            onPress={handleWalkUpFirstStage}
            disabled={!walkUpTicketId || !walkUpName.trim() || !walkUpEmail.trim() || createWalkUp.isPending}
            style={[styles.button, (!walkUpTicketId || !walkUpName.trim() || !walkUpEmail.trim() || createWalkUp.isPending) && styles.buttonDisabled]}
          >
            <ThemedText style={styles.buttonText}>
              {createWalkUp.isPending ? '登録中…' : '当日券を発行して受付する'}
            </ThemedText>
          </TouchableOpacity>
        </>
      )}
    </>
  );
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.two },
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.one },
  fieldLabel: { marginTop: Spacing.one },
  performanceRow: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two, marginBottom: Spacing.two },
  performanceChip: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.one,
  },
  performanceChipActive: { borderColor: BrandColors.warmAmber, backgroundColor: '#fff6e8' },
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
  actionButtons: { flexDirection: 'row', gap: Spacing.two },
  radioRow: { gap: Spacing.one, marginBottom: Spacing.two },
  radioOption: { paddingVertical: Spacing.half },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
    marginBottom: Spacing.two,
  },
  error: { color: '#a6483a', marginTop: Spacing.two },
  message: { color: '#3a7a4e', marginTop: Spacing.two },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
    marginTop: Spacing.two,
    alignSelf: 'flex-start',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
});
