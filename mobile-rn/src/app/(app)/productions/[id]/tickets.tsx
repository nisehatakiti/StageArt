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
  useQuotaAndTicketBackSettings,
  useTickets,
  useTicketSalesSettings,
  useUpdateQuotaAndTicketBackSettings,
  useUpdateTicket,
  useUpdateTicketSalesSettings,
} from '@/features/ticket/useTickets';
import { useProduction } from '@/features/production/useProductions';
import type { Ticket, TicketBackCondition } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

const PERFORMANCE_STATUS_LABEL: Record<string, string> = {
  PUBLISHED: '公開中',
  SOLD_OUT: '満席',
  FINISHED: '終了',
  CANCELLED: '中止',
};

/**
 * StageArt Phase 3 Ticket/Reservation基盤 §49: チケット設定
 * (Chapter 32 §3の「登録済みチケット」＋「公開設定」＋「販売設定」) と
 * チケットバック／ノルマ設定 (Chapter 32 §4) を1画面にまとめる - Ch32自身の
 * Production Management Menu (§2) が両方を「チケット管理」1メニュー配下の
 * 2セクションとして扱っているのに合わせた。Ticket Type二軸Matrix UIは
 * 作らない（§5/§49 - フラットなTicket名+料金のみ）。
 */
export default function ProductionTicketsScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const ticketsQuery = useTickets(id);
  const salesSettingsQuery = useTicketSalesSettings(id);
  const quotaAndTicketBackQuery = useQuotaAndTicketBackSettings(id);
  const availabilityQuery = usePerformanceTicketAvailability(id);
  const createTicket = useCreateTicket(id);
  const archiveTicket = useArchiveTicket(id);
  const updateSalesSettings = useUpdateTicketSalesSettings(id);
  const updateQuotaAndTicketBack = useUpdateQuotaAndTicketBackSettings(id);

  const isPrimaryManager = !!production?.is_primary_manager;
  const canManage = isPrimaryManager || production?.delegate_role === 'TICKET_MANAGER';

  const [editingTicketId, setEditingTicketId] = useState<string | null>(null);
  const [newName, setNewName] = useState('');
  const [newPrice, setNewPrice] = useState('');
  const [newRemarks, setNewRemarks] = useState('');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const [publicationAt, setPublicationAt] = useState('');
  const [salesStartAt, setSalesStartAt] = useState('');
  const [salesEndRule, setSalesEndRule] = useState<'DAY_BEFORE_AT_TIME' | 'HOURS_BEFORE_START'>('HOURS_BEFORE_START');
  const [salesEndParameter, setSalesEndParameter] = useState('');
  // Seeded from the server exactly once real data arrives, matching this
  // codebase's own established "adjust state during render, once"
  // pattern (see e.g. production edit screens) - a background refetch
  // must not silently discard whatever the user has already typed.
  const [salesSettingsSeeded, setSalesSettingsSeeded] = useState(false);

  if (salesSettingsQuery.data && !salesSettingsSeeded) {
    setSalesSettingsSeeded(true);
    setPublicationAt(salesSettingsQuery.data.ticket_publication_at ?? '');
    setSalesStartAt(salesSettingsQuery.data.ticket_sales_start_at ?? '');
    if (salesSettingsQuery.data.ticket_sales_end_rule === 'DAY_BEFORE_AT_TIME' || salesSettingsQuery.data.ticket_sales_end_rule === 'HOURS_BEFORE_START') {
      setSalesEndRule(salesSettingsQuery.data.ticket_sales_end_rule);
    }
    setSalesEndParameter(salesSettingsQuery.data.ticket_sales_end_parameter ?? '');
  }

  const [quotaEnabled, setQuotaEnabled] = useState(false);
  const [quotaCount, setQuotaCount] = useState('');
  const [quotaBuybackEnabled, setQuotaBuybackEnabled] = useState(false);
  const [quotaShortfallUnitPrice, setQuotaShortfallUnitPrice] = useState('');

  const [ticketBackEnabled, setTicketBackEnabled] = useState(false);
  const [ticketBackMode, setTicketBackMode] = useState<'PROGRESSIVE' | 'SEPARATED'>('PROGRESSIVE');
  const [ticketBackConditions, setTicketBackConditions] = useState<TicketBackCondition[]>([]);
  const [quotaSettingsSeeded, setQuotaSettingsSeeded] = useState(false);

  if (quotaAndTicketBackQuery.data && !quotaSettingsSeeded) {
    setQuotaSettingsSeeded(true);
    setQuotaEnabled(quotaAndTicketBackQuery.data.quota_enabled);
    setQuotaCount(quotaAndTicketBackQuery.data.quota_count !== null ? String(quotaAndTicketBackQuery.data.quota_count) : '');
    setQuotaBuybackEnabled(quotaAndTicketBackQuery.data.quota_buyback_enabled);
    setQuotaShortfallUnitPrice(
      quotaAndTicketBackQuery.data.quota_shortfall_unit_price !== null ? String(quotaAndTicketBackQuery.data.quota_shortfall_unit_price) : ''
    );
    setTicketBackEnabled(quotaAndTicketBackQuery.data.ticket_back_mode !== null);
    if (quotaAndTicketBackQuery.data.ticket_back_mode !== null) {
      setTicketBackMode(quotaAndTicketBackQuery.data.ticket_back_mode);
    }
    setTicketBackConditions(quotaAndTicketBackQuery.data.ticket_back_conditions);
  }

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

  async function handleSaveSalesSettings() {
    setErrorMessage(null);
    try {
      await updateSalesSettings.mutateAsync({
        ticketPublicationAt: publicationAt.trim() || null,
        ticketSalesStartAt: salesStartAt.trim() || null,
        ticketSalesEndRule: salesEndParameter.trim() ? salesEndRule : null,
        ticketSalesEndParameter: salesEndParameter.trim() || null,
      });
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  function addTicketBackCondition() {
    setTicketBackConditions((current) => [
      ...current,
      { priority: current.length + 1, threshold: 1, comparator: 'GTE', rate_percent: 10 },
    ]);
  }

  function removeTicketBackCondition(index: number) {
    setTicketBackConditions((current) => current.filter((_, i) => i !== index));
  }

  function updateTicketBackCondition(index: number, patch: Partial<TicketBackCondition>) {
    setTicketBackConditions((current) => current.map((c, i) => (i === index ? { ...c, ...patch } : c)));
  }

  async function handleSaveQuotaAndTicketBack() {
    setErrorMessage(null);
    try {
      await updateQuotaAndTicketBack.mutateAsync({
        quotaEnabled,
        quotaCount: quotaEnabled ? Number(quotaCount.trim()) : null,
        quotaBuybackEnabled: quotaEnabled && quotaBuybackEnabled,
        quotaShortfallUnitPrice: quotaEnabled && quotaBuybackEnabled ? Number(quotaShortfallUnitPrice.trim()) : null,
        ticketBackMode: ticketBackEnabled ? ticketBackMode : null,
        ticketBackConditions: ticketBackEnabled ? ticketBackConditions : [],
      });
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
        チケット名
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
        公開・販売設定
      </ThemedText>

      <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
        情報公開日時
      </ThemedText>
      <ThemedTextInput
        testID="production-tickets-publication-at"
        value={publicationAt}
        onChangeText={setPublicationAt}
        placeholder="2026-09-01T00:00:00+09:00"
        style={styles.input}
      />

      <ThemedText type="small" themeColor="textSecondary">
        販売開始日時
      </ThemedText>
      <ThemedTextInput
        testID="production-tickets-sales-start-at"
        value={salesStartAt}
        onChangeText={setSalesStartAt}
        placeholder="2026-09-10T00:00:00+09:00"
        style={styles.input}
      />

      <ThemedText type="small" themeColor="textSecondary">
        販売終了ルール
      </ThemedText>
      <View style={styles.radioRow}>
        <TouchableOpacity testID="production-tickets-sales-end-rule-day-before" onPress={() => setSalesEndRule('DAY_BEFORE_AT_TIME')} style={styles.radioOption}>
          <ThemedText>{salesEndRule === 'DAY_BEFORE_AT_TIME' ? '◉' : '○'} 公演前日の指定時刻まで（HH:MM）</ThemedText>
        </TouchableOpacity>
        <TouchableOpacity testID="production-tickets-sales-end-rule-hours-before" onPress={() => setSalesEndRule('HOURS_BEFORE_START')} style={styles.radioOption}>
          <ThemedText>{salesEndRule === 'HOURS_BEFORE_START' ? '◉' : '○'} 開演の指定時間前まで（時間数）</ThemedText>
        </TouchableOpacity>
      </View>
      <ThemedTextInput
        testID="production-tickets-sales-end-parameter"
        value={salesEndParameter}
        onChangeText={setSalesEndParameter}
        placeholder={salesEndRule === 'DAY_BEFORE_AT_TIME' ? '23:00' : '3'}
        style={styles.input}
      />

      <TouchableOpacity testID="production-tickets-save-sales-settings" onPress={handleSaveSalesSettings} style={styles.button}>
        <ThemedText style={styles.buttonText}>公開・販売設定を更新</ThemedText>
      </TouchableOpacity>

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        チケットノルマ
      </ThemedText>

      <TouchableOpacity testID="production-tickets-quota-toggle" onPress={() => setQuotaEnabled((v) => !v)} style={styles.toggleRow}>
        <ThemedText>{quotaEnabled ? '☑' : '☐'} ノルマを設定する</ThemedText>
      </TouchableOpacity>

      {quotaEnabled && (
        <>
          <ThemedText type="small" themeColor="textSecondary">
            ノルマ枚数
          </ThemedText>
          <ThemedTextInput testID="production-tickets-quota-count" value={quotaCount} onChangeText={setQuotaCount} keyboardType="number-pad" style={styles.input} />

          <TouchableOpacity testID="production-tickets-quota-buyback-toggle" onPress={() => setQuotaBuybackEnabled((v) => !v)} style={styles.toggleRow}>
            <ThemedText>{quotaBuybackEnabled ? '◉' : '○'} 買取にする</ThemedText>
          </TouchableOpacity>

          {quotaBuybackEnabled && (
            <>
              <ThemedText type="small" themeColor="textSecondary">
                未達チケット単価（円）
              </ThemedText>
              <ThemedTextInput
                testID="production-tickets-quota-shortfall-unit-price"
                value={quotaShortfallUnitPrice}
                onChangeText={setQuotaShortfallUnitPrice}
                keyboardType="number-pad"
                style={styles.input}
              />
            </>
          )}
        </>
      )}

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        チケットバック
      </ThemedText>

      <TouchableOpacity testID="production-tickets-ticket-back-toggle" onPress={() => setTicketBackEnabled((v) => !v)} style={styles.toggleRow}>
        <ThemedText>{ticketBackEnabled ? '☑' : '☐'} チケットバックを設定する</ThemedText>
      </TouchableOpacity>

      {ticketBackEnabled && (
        <>
          <View style={styles.radioRow}>
            <TouchableOpacity testID="production-tickets-ticket-back-mode-progressive" onPress={() => setTicketBackMode('PROGRESSIVE')} style={styles.radioOption}>
              <ThemedText>{ticketBackMode === 'PROGRESSIVE' ? '◉' : '○'} 累進方式</ThemedText>
            </TouchableOpacity>
            <TouchableOpacity testID="production-tickets-ticket-back-mode-separated" onPress={() => setTicketBackMode('SEPARATED')} style={styles.radioOption}>
              <ThemedText>{ticketBackMode === 'SEPARATED' ? '◉' : '○'} 分離方式</ThemedText>
            </TouchableOpacity>
          </View>

          {ticketBackConditions.map((condition, index) => (
            <View key={index} style={styles.conditionRow} testID={`ticket-back-condition-${index}`}>
              <ThemedTextInput
                testID={`ticket-back-condition-${index}-priority`}
                value={String(condition.priority)}
                onChangeText={(v) => updateTicketBackCondition(index, { priority: Number(v) || 0 })}
                keyboardType="number-pad"
                style={styles.smallInput}
              />
              <ThemedTextInput
                testID={`ticket-back-condition-${index}-threshold`}
                value={String(condition.threshold)}
                onChangeText={(v) => updateTicketBackCondition(index, { threshold: Number(v) || 0 })}
                keyboardType="number-pad"
                style={styles.smallInput}
              />
              <ThemedTextInput
                testID={`ticket-back-condition-${index}-rate`}
                value={String(condition.rate_percent)}
                onChangeText={(v) => updateTicketBackCondition(index, { rate_percent: Number(v) || 0 })}
                keyboardType="number-pad"
                style={styles.smallInput}
              />
              <TouchableOpacity testID={`ticket-back-condition-${index}-remove`} onPress={() => removeTicketBackCondition(index)}>
                <ThemedText type="link">削除</ThemedText>
              </TouchableOpacity>
            </View>
          ))}

          <TouchableOpacity testID="production-tickets-add-condition" onPress={addTicketBackCondition} style={styles.secondaryButton}>
            <ThemedText style={styles.secondaryButtonText}>＋ 条件を追加</ThemedText>
          </TouchableOpacity>
        </>
      )}

      <TouchableOpacity testID="production-tickets-save-quota-ticket-back" onPress={handleSaveQuotaAndTicketBack} style={styles.button}>
        <ThemedText style={styles.buttonText}>ノルマ／チケットバック設定を更新</ThemedText>
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
