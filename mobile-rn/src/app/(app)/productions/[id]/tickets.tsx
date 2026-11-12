import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import {
  useArchiveTicket,
  useCreateTicket,
  usePerformanceTicketAvailability,
  useTickets,
  useUpdateTicket,
} from '@/features/ticket/useTickets';
import { useProduction } from '@/features/production/useProductions';
import type { Ticket } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

const PERFORMANCE_STATUS_LABEL: Record<string, string> = {
  PUBLISHED: '公開中',
  SOLD_OUT: '満席',
  FINISHED: '終了',
  CANCELLED: '中止',
};

/**
 * StageArt チケット管理の整理: この画面は「チケット管理」として、チケット
 * 種別の登録・編集・アーカイブと、公演スケジュールごとの販売可能状態の確認
 * のみを扱う。公開日時・販売開始日時・販売終了ルールは公演情報画面
 * (productions/[id]/edit.tsx) に、チケットノルマ／チケットバック設定は
 * 専用メニューの quota.tsx に、それぞれ分離済み - この画面に戻すことは
 * しない。Ticket Type二軸Matrix UIは作らない（フラットなTicket名+料金の
 * み）。
 */
export default function ProductionTicketsScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const ticketsQuery = useTickets(id);
  const availabilityQuery = usePerformanceTicketAvailability(id);
  const createTicket = useCreateTicket(id);
  const archiveTicket = useArchiveTicket(id);

  const isPrimaryManager = !!production?.is_primary_manager;
  const canManage = isPrimaryManager || !!production?.delegate_roles?.includes('TICKET_MANAGER');

  const [editingTicketId, setEditingTicketId] = useState<string | null>(null);
  const [newName, setNewName] = useState('');
  const [newPrice, setNewPrice] = useState('');
  const [newRemarks, setNewRemarks] = useState('');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  async function handleAddTicket() {
    setErrorMessage(null);
    try {
      await createTicket.mutateAsync({
        name: newName.trim(),
        price: Number(newPrice.trim()),
        remarks: newRemarks.trim() || undefined,
      });
      setNewName('');
      setNewPrice('');
      setNewRemarks('');
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handleArchive(ticketId: string) {
    setErrorMessage(null);
    try {
      await archiveTicket.mutateAsync(ticketId);
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }



  if (productionQuery.isLoading) {
    return (
      <>
        <ActivityIndicator testID="production-tickets-loading" />
      </>
    );
  }

  if (!production) {
    return (
      <>
        <ThemedText testID="production-tickets-not-found">この公演が見つかりません。</ThemedText>
      </>
    );
  }

  if (!canManage) {
    return (
      <>
        <ThemedText testID="production-tickets-forbidden">
          チケット管理はPrimaryManagerまたはチケット管理の権限を持つ担当者のみ利用できます。
        </ThemedText>
      </>
    );
  }

  const activeTickets = (ticketsQuery.data ?? []).filter((t) => t.status === 'ACTIVE');
  const archivedTickets = (ticketsQuery.data ?? []).filter((t) => t.status === 'ARCHIVED');

  return (
    <>
      <ThemedText type="title" style={styles.pageTitle}>
        チケット管理
      </ThemedText>

      {errorMessage && (
        <ThemedText testID="production-tickets-error" style={styles.error}>
          {errorMessage}
        </ThemedText>
      )}

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        登録済みチケット
      </ThemedText>

      {ticketsQuery.isLoading && <ActivityIndicator testID="production-tickets-list-loading" />}

      {activeTickets.length === 0 && !ticketsQuery.isLoading && (
        <ThemedText testID="production-tickets-empty" themeColor="textSecondary">
          まだチケットがありません。
        </ThemedText>
      )}

      {activeTickets.length > 0 && (
        <View style={styles.list} testID="production-tickets-list">
          {activeTickets.map((ticket) =>
            editingTicketId === ticket.id ? (
              <TicketEditRow key={ticket.id} ticket={ticket} onDone={() => setEditingTicketId(null)} onError={setErrorMessage} productionId={id} />
            ) : (
              <View key={ticket.id} style={styles.row} testID={`ticket-row-${ticket.id}`}>
                <ThemedText style={styles.colName}>{ticket.name}</ThemedText>
                <ThemedText style={styles.colPrice}>{ticket.price}円</ThemedText>
                <ThemedText style={styles.colRemarks} themeColor="textSecondary">
                  {ticket.remarks ?? ''}
                </ThemedText>
                <View style={styles.actionButtons}>
                  <TouchableOpacity testID={`ticket-edit-${ticket.id}`} onPress={() => setEditingTicketId(ticket.id)}>
                    <ThemedText type="link">編集</ThemedText>
                  </TouchableOpacity>
                  <TouchableOpacity testID={`ticket-archive-${ticket.id}`} onPress={() => handleArchive(ticket.id)}>
                    <ThemedText type="link">削除</ThemedText>
                  </TouchableOpacity>
                </View>
              </View>
            )
          )}
        </View>
      )}

      {archivedTickets.length > 0 && (
        <ThemedText type="small" themeColor="textSecondary" style={styles.archivedNote}>
          削除済み（履歴）：{archivedTickets.map((t) => t.name).join('、')}
        </ThemedText>
      )}

      <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
        チケット種別
      </ThemedText>
      <ThemedTextInput testID="production-tickets-new-name" value={newName} onChangeText={setNewName} style={styles.input} />

      <ThemedText type="small" themeColor="textSecondary">
        料金（円）
      </ThemedText>
      <ThemedTextInput testID="production-tickets-new-price" value={newPrice} onChangeText={setNewPrice} keyboardType="number-pad" style={styles.input} />

      <ThemedText type="small" themeColor="textSecondary">
        備考
      </ThemedText>
      <ThemedTextInput testID="production-tickets-new-remarks" value={newRemarks} onChangeText={setNewRemarks} style={styles.input} />

      <TouchableOpacity
        testID="production-tickets-add"
        onPress={handleAddTicket}
        disabled={createTicket.isPending || !newName.trim() || !newPrice.trim()}
        style={[styles.button, createTicket.isPending && styles.buttonDisabled]}
      >
        <ThemedText style={styles.buttonText}>＋ チケットを追加</ThemedText>
      </TouchableOpacity>

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        公演スケジュールごとの販売可能状態
      </ThemedText>

      {availabilityQuery.isLoading && <ActivityIndicator testID="production-tickets-availability-loading" />}

      {!availabilityQuery.isLoading && (availabilityQuery.data ?? []).length === 0 && (
        <ThemedText type="small" themeColor="textSecondary" testID="production-tickets-availability-empty">
          公演スケジュールがまだ登録されていません。
        </ThemedText>
      )}

      {(availabilityQuery.data ?? []).length > 0 && (
        <View style={styles.list} testID="production-tickets-availability-list">
          {availabilityQuery.data!.map((item) => (
            <View key={item.performance_id} style={styles.row} testID={`ticket-availability-row-${item.performance_id}`}>
              <ThemedText style={styles.colName}>
                {item.performance_date} {item.start_time.slice(0, 5)}
              </ThemedText>
              <ThemedText style={styles.colPrice} themeColor="textSecondary">
                {PERFORMANCE_STATUS_LABEL[item.performance_status] ?? item.performance_status}
              </ThemedText>
              <ThemedText
                style={item.is_sales_open ? styles.availabilityOpen : styles.availabilityClosed}
                testID={`ticket-availability-status-${item.performance_id}`}
              >
                {item.is_sales_open ? '販売中' : '販売不可'}
              </ThemedText>
            </View>
          ))}
        </View>
      )}
    </>
  );
}

function TicketEditRow({
  ticket,
  onDone,
  onError,
  productionId,
}: {
  ticket: Ticket;
  onDone: () => void;
  onError: (message: string) => void;
  productionId: string;
}) {
  const [name, setName] = useState(ticket.name);
  const [price, setPrice] = useState(String(ticket.price));
  const [remarks, setRemarks] = useState(ticket.remarks ?? '');
  const updateTicket = useUpdateTicket(productionId);

  async function handleSave() {
    try {
      await updateTicket.mutateAsync({ ticketId: ticket.id, name: name.trim(), price: Number(price.trim()), remarks: remarks.trim() || null });
      onDone();
    } catch (error) {
      onError(getErrorMessage(error));
    }
  }

  return (
    <View style={styles.editRow} testID={`ticket-edit-row-${ticket.id}`}>
      <ThemedTextInput testID={`ticket-edit-name-${ticket.id}`} value={name} onChangeText={setName} style={styles.smallInput} />
      <ThemedTextInput testID={`ticket-edit-price-${ticket.id}`} value={price} onChangeText={setPrice} keyboardType="number-pad" style={styles.smallInput} />
      <ThemedTextInput testID={`ticket-edit-remarks-${ticket.id}`} value={remarks} onChangeText={setRemarks} style={styles.smallInput} />
      <TouchableOpacity testID={`ticket-edit-save-${ticket.id}`} onPress={handleSave}>
        <ThemedText type="link">保存</ThemedText>
      </TouchableOpacity>
      <TouchableOpacity testID={`ticket-edit-cancel-${ticket.id}`} onPress={onDone}>
        <ThemedText type="link">キャンセル</ThemedText>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.two },
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.one },
  fieldLabel: { marginTop: Spacing.one },
  list: { gap: Spacing.one },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
    gap: Spacing.two,
  },
  editRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
    gap: Spacing.two,
  },
  colName: { width: 140 },
  colPrice: { width: 90 },
  colRemarks: { flex: 1 },
  actionButtons: { flexDirection: 'row', gap: Spacing.two },
  archivedNote: { marginTop: Spacing.one },
  radioRow: { gap: Spacing.one, marginBottom: Spacing.two },
  radioOption: { paddingVertical: Spacing.half },
  toggleRow: { paddingVertical: Spacing.one, marginBottom: Spacing.one },
  conditionRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two, marginBottom: Spacing.one },
  smallInput: {
    width: 90,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.one,
  },
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
    marginTop: Spacing.two,
    alignSelf: 'flex-start',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  secondaryButton: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.one,
    paddingHorizontal: Spacing.three,
    alignItems: 'center',
    alignSelf: 'flex-start',
    marginBottom: Spacing.two,
  },
  secondaryButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  availabilityOpen: { color: '#2f7a4a', fontWeight: '600' },
  availabilityClosed: { color: '#a6483a', fontWeight: '600' },
});
