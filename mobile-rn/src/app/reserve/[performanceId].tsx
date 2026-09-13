import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { usePublicTickets } from '@/features/ticket/useTickets';
import { useCreateReservation } from '@/features/reservation/useReservations';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Phase 3 Ticket/Reservation基盤 §50: Public予約フロー
 * (Production -> Performance -> Ticket -> Guest Count -> 予約者情報 ->
 * 予約確認 -> Reservation成立)。V1では1 Reservationにつき1 Ticket種別の
 * みを扱う（複数Ticket種別を組み合わせる予約カートは作らない）。
 */
export default function ReservationCreateScreen() {
  const { performanceId, productionId } = useLocalSearchParams<{ performanceId: string; productionId: string }>();
  const ticketsQuery = usePublicTickets(productionId);
  const createReservation = useCreateReservation(performanceId);

  const [selectedTicketId, setSelectedTicketId] = useState<string | null>(null);
  const [guestCount, setGuestCount] = useState('1');
  const [bookerName, setBookerName] = useState('');
  const [bookerEmail, setBookerEmail] = useState('');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successNumber, setSuccessNumber] = useState<string | null>(null);

  const tickets = ticketsQuery.data?.tickets ?? [];

  async function handleSubmit() {
    if (!selectedTicketId) {
      return;
    }

    setErrorMessage(null);

    try {
      const result = await createReservation.mutateAsync({
        ticketId: selectedTicketId,
        bookerName: bookerName.trim(),
        bookerEmail: bookerEmail.trim(),
        guestCount: Number(guestCount.trim()),
      });
      setSuccessNumber(result.reservation_number);
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  return (
    <AppShell scroll>
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText type="title" style={styles.title}>
          チケット予約
        </ThemedText>

        {successNumber ? (
          <View testID="reservation-create-success">
            <ThemedText type="smallBold">予約が完了しました。</ThemedText>
            <ThemedText testID="reservation-create-number" style={styles.numberText}>
              予約番号：{successNumber}
            </ThemedText>
            <ThemedText type="small" themeColor="textSecondary">
              予約番号とメールアドレスは、予約の確認・変更・キャンセルに必要です。大切に保管してください。
            </ThemedText>
          </View>
        ) : (
          <>
            {ticketsQuery.isLoading && <ActivityIndicator testID="reservation-create-tickets-loading" />}

            {tickets.length === 0 && !ticketsQuery.isLoading && (
              <ThemedText testID="reservation-create-no-tickets" themeColor="textSecondary">
                現在購入可能なチケットはありません。
              </ThemedText>
            )}

            {tickets.length > 0 && (
              <>
                <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
                  チケット種別
                </ThemedText>
                {tickets.map((ticket) => (
                  <TouchableOpacity
                    key={ticket.id}
                    testID={`reservation-create-ticket-${ticket.id}`}
                    onPress={() => setSelectedTicketId(ticket.id)}
                    style={[styles.ticketOption, selectedTicketId === ticket.id && styles.ticketOptionSelected]}
                  >
                    <ThemedText>
                      {selectedTicketId === ticket.id ? '◉' : '○'} {ticket.name}（{ticket.price}円）
                    </ThemedText>
                  </TouchableOpacity>
                ))}

                <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
                  人数
                </ThemedText>
                <ThemedTextInput testID="reservation-create-guest-count" value={guestCount} onChangeText={setGuestCount} keyboardType="number-pad" style={styles.input} />

                <ThemedText type="small" themeColor="textSecondary">
                  お名前
                </ThemedText>
                <ThemedTextInput testID="reservation-create-booker-name" value={bookerName} onChangeText={setBookerName} style={styles.input} />

                <ThemedText type="small" themeColor="textSecondary">
                  メールアドレス
                </ThemedText>
                <ThemedTextInput
                  testID="reservation-create-booker-email"
                  value={bookerEmail}
                  onChangeText={setBookerEmail}
                  keyboardType="email-address"
                  autoCapitalize="none"
                  style={styles.input}
                />

                {errorMessage && (
                  <ThemedText testID="reservation-create-error" style={styles.error}>
                    {errorMessage}
                  </ThemedText>
                )}

                <TouchableOpacity
                  testID="reservation-create-submit"
                  onPress={handleSubmit}
                  disabled={createReservation.isPending || !selectedTicketId || !bookerName.trim() || !bookerEmail.trim() || !guestCount.trim()}
                  style={[styles.button, createReservation.isPending && styles.buttonDisabled]}
                >
                  {createReservation.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>予約する</ThemedText>}
                </TouchableOpacity>
              </>
            )}
          </>
        )}
      </ScrollView>
    </AppShell>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four },
  title: { marginBottom: Spacing.three },
  fieldLabel: { marginTop: Spacing.two },
  ticketOption: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
    marginBottom: Spacing.one,
  },
  ticketOptionSelected: { borderColor: BrandColors.warmAmber, backgroundColor: '#FBEFDD' },
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
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
    marginTop: Spacing.three,
    alignSelf: 'flex-start',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  numberText: { fontSize: 20, fontWeight: '700', marginVertical: Spacing.two },
});
