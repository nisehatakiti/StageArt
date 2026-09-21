import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';
import QRCode from 'react-native-qrcode-svg';

import { ApiError } from '@/api/errors';
import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useCancelReservation, useReservationLookup, useUpdateReservation } from '@/features/reservation/useReservations';
import { getErrorMessage } from '@/utils/errorMessage';

const STATUS_LABEL: Record<string, string> = {
  RESERVED: '予約中',
  CHECKED_IN: '受付済み',
  CANCELLED: 'キャンセル済み',
  NO_SHOW: '不参加',
};

/**
 * StageArt Phase 3 Ticket/Reservation基盤 §10/§32/§51: 予約番号＋予約時
 * メールアドレスによる本人確認（StageArtアカウント不要）で、自分の予約の
 * 確認・変更・キャンセルを行う。最終的な許可判定は必ずApplication/API側で
 * 行われる（§51 - このUIの表示はあくまで案内であり、拒否判定の正本ではない）。
 */
export default function MyReservationScreen() {
  const [reservationNumber, setReservationNumber] = useState('');
  const [email, setEmail] = useState('');
  const [submitted, setSubmitted] = useState(false);
  const [guestCountInput, setGuestCountInput] = useState('');
  const [actionError, setActionError] = useState<string | null>(null);

  const lookupQuery = useReservationLookup(reservationNumber.trim(), email.trim(), submitted);
  const updateReservation = useUpdateReservation();
  const cancelReservation = useCancelReservation();

  function handleLookup() {
    setActionError(null);
    setSubmitted(true);
    if (lookupQuery.data) {
      setGuestCountInput(String(lookupQuery.data.guest_count));
    }
  }

  async function handleUpdate() {
    setActionError(null);
    try {
      await updateReservation.mutateAsync({
        reservationNumber: reservationNumber.trim(),
        email: email.trim(),
        guestCount: Number(guestCountInput.trim()),
      });
      await lookupQuery.refetch();
    } catch (error) {
      setActionError(getErrorMessage(error));
    }
  }

  async function handleCancel() {
    setActionError(null);
    try {
      await cancelReservation.mutateAsync({ reservationNumber: reservationNumber.trim(), email: email.trim() });
      await lookupQuery.refetch();
    } catch (error) {
      setActionError(getErrorMessage(error));
    }
  }

  const reservation = lookupQuery.data;

  return (
    <AppShell scroll>
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText type="title" style={styles.title}>
          予約の確認・変更・キャンセル
        </ThemedText>

        <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
          予約番号
        </ThemedText>
        <ThemedTextInput testID="my-reservation-number" value={reservationNumber} onChangeText={setReservationNumber} autoCapitalize="characters" style={styles.input} />

        <ThemedText type="small" themeColor="textSecondary">
          予約時のメールアドレス
        </ThemedText>
        <ThemedTextInput testID="my-reservation-email" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" style={styles.input} />

        <TouchableOpacity
          testID="my-reservation-lookup"
          onPress={handleLookup}
          disabled={!reservationNumber.trim() || !email.trim()}
          style={styles.button}
        >
          <ThemedText style={styles.buttonText}>予約を確認する</ThemedText>
        </TouchableOpacity>

        {submitted && lookupQuery.isLoading && <ActivityIndicator testID="my-reservation-loading" style={styles.spacerTop} />}

        {submitted && lookupQuery.isError && (
          <ThemedText testID="my-reservation-not-found" style={styles.error}>
            {lookupQuery.error instanceof ApiError && lookupQuery.error.statusCode === 403
              ? '予約番号またはメールアドレスが一致しません。'
              : getErrorMessage(lookupQuery.error)}
          </ThemedText>
        )}

        {reservation && (
          <View testID="my-reservation-detail" style={styles.detail}>
            <ThemedText type="smallBold">状態：{STATUS_LABEL[reservation.status] ?? reservation.status}</ThemedText>
            <ThemedText>人数：{reservation.guest_count}名</ThemedText>
            <ThemedText>金額（1名あたり）：{reservation.price_snapshot}円</ThemedText>

            {reservation.status === 'RESERVED' && (
              <View testID="my-reservation-qr" style={styles.qrBox}>
                <QRCode value={reservation.reservation_number} size={160} />
                <ThemedText type="small" themeColor="textSecondary" style={styles.hint}>
                  当日はこのQRコードを受付で提示してください。スクリーンショットでの提示も可能です。
                </ThemedText>
              </View>
            )}

            {reservation.status === 'RESERVED' && (
              <>
                <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
                  人数を変更する
                </ThemedText>
                <ThemedTextInput
                  testID="my-reservation-guest-count"
                  value={guestCountInput}
                  onChangeText={setGuestCountInput}
                  keyboardType="number-pad"
                  style={styles.input}
                />

                {actionError && (
                  <ThemedText testID="my-reservation-action-error" style={styles.error}>
                    {actionError}
                  </ThemedText>
                )}

                <View style={styles.actionRow}>
                  <TouchableOpacity testID="my-reservation-update" onPress={handleUpdate} disabled={updateReservation.isPending} style={styles.secondaryButton}>
                    <ThemedText style={styles.secondaryButtonText}>人数を更新</ThemedText>
                  </TouchableOpacity>
                  <TouchableOpacity testID="my-reservation-cancel" onPress={handleCancel} disabled={cancelReservation.isPending} style={styles.dangerButton}>
                    <ThemedText style={styles.dangerButtonText}>キャンセルする</ThemedText>
                  </TouchableOpacity>
                </View>

                <ThemedText type="small" themeColor="textSecondary" style={styles.hint}>
                  ※販売終了後は人数の増加はできません。開演後は変更・キャンセルできません。
                </ThemedText>
              </>
            )}
          </View>
        )}
      </ScrollView>
    </AppShell>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four },
  title: { marginBottom: Spacing.three },
  fieldLabel: { marginTop: Spacing.two },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
    marginBottom: Spacing.two,
  },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
    alignSelf: 'flex-start',
  },
  buttonText: { color: '#fff', fontWeight: '600' },
  spacerTop: { marginTop: Spacing.three },
  error: { color: '#a6483a', marginTop: Spacing.two },
  detail: { marginTop: Spacing.four, gap: Spacing.one },
  actionRow: { flexDirection: 'row', gap: Spacing.two, marginTop: Spacing.two },
  secondaryButton: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
  },
  secondaryButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  dangerButton: {
    borderWidth: 1,
    borderColor: '#a6483a',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
  },
  dangerButtonText: { color: '#a6483a', fontWeight: '600' },
  hint: { marginTop: Spacing.two },
  qrBox: { alignItems: 'flex-start', marginVertical: Spacing.two },
});
