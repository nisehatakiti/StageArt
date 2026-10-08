import { useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useProduction } from '@/features/production/useProductions';
import { useQuotaAndTicketBackSettings, useUpdateQuotaAndTicketBackSettings } from '@/features/ticket/useTickets';
import type { TicketBackCondition } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

export default function ProductionTicketQuotaScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const settingsQuery = useQuotaAndTicketBackSettings(id);
  const updateSettings = useUpdateQuotaAndTicketBackSettings(id);

  const canManage = !!production?.is_primary_manager || !!production?.delegate_roles?.includes('TICKET_MANAGER');
  const [seeded, setSeeded] = useState(false);
  const [quotaEnabled, setQuotaEnabled] = useState(false);
  const [quotaCount, setQuotaCount] = useState('');
  const [quotaBuybackEnabled, setQuotaBuybackEnabled] = useState(false);
  const [quotaShortfallUnitPrice, setQuotaShortfallUnitPrice] = useState('');
  const [ticketBackEnabled, setTicketBackEnabled] = useState(false);
  const [ticketBackMode, setTicketBackMode] = useState<'PROGRESSIVE' | 'SEPARATED'>('PROGRESSIVE');
  const [ticketBackConditions, setTicketBackConditions] = useState<TicketBackCondition[]>([]);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    if (settingsQuery.data && !seeded) {
      setSeeded(true);
      setQuotaEnabled(settingsQuery.data.quota_enabled);
      setQuotaCount(settingsQuery.data.quota_count === null ? '' : String(settingsQuery.data.quota_count));
      setQuotaBuybackEnabled(settingsQuery.data.quota_buyback_enabled);
      setQuotaShortfallUnitPrice(
        settingsQuery.data.quota_shortfall_unit_price === null ? '' : String(settingsQuery.data.quota_shortfall_unit_price)
      );
      setTicketBackEnabled(settingsQuery.data.ticket_back_mode !== null);
      if (settingsQuery.data.ticket_back_mode) setTicketBackMode(settingsQuery.data.ticket_back_mode);
      setTicketBackConditions(settingsQuery.data.ticket_back_conditions);
    }
  }, [settingsQuery.data, seeded]);

  function addCondition() {
    setTicketBackConditions((current) => [
      ...current,
      { priority: current.length + 1, threshold: 1, comparator: 'GTE', rate_percent: 10 },
    ]);
  }

  function updateCondition(index: number, patch: Partial<TicketBackCondition>) {
    setTicketBackConditions((current) => current.map((condition, i) => (i === index ? { ...condition, ...patch } : condition)));
  }

  function removeCondition(index: number) {
    setTicketBackConditions((current) => current.filter((_, i) => i !== index));
  }

  async function handleSave() {
    setErrorMessage(null);
    try {
      await updateSettings.mutateAsync({
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

  if (productionQuery.isLoading) return <ActivityIndicator testID="production-ticket-quota-loading" />;
  if (!production) return <ThemedText testID="production-ticket-quota-not-found">この公演が見つかりません。</ThemedText>;
  if (!canManage) {
    return <ThemedText testID="production-ticket-quota-forbidden">チケットノルマ管理はPrimaryManagerまたはチケット管理の権限を持つ担当者のみ利用できます。</ThemedText>;
  }

  return (
    <View style={styles.page}>
      <ThemedText type="title" style={styles.pageTitle}>チケットノルマ</ThemedText>
      <ThemedText type="small" themeColor="textSecondary" style={styles.caption}>
        チケット種別の登録は公演情報で行い、ここではメンバーのチケットノルマとチケットバックを管理します。
      </ThemedText>

      <ThemedText type="subtitle" style={styles.sectionTitle}>チケットノルマ</ThemedText>
      <TouchableOpacity testID="production-ticket-quota-toggle" onPress={() => setQuotaEnabled((v) => !v)} style={styles.toggleRow}>
        <ThemedText>{quotaEnabled ? '☑' : '☐'} ノルマを設定する</ThemedText>
      </TouchableOpacity>

      {quotaEnabled && (
        <>
          <ThemedText type="small" themeColor="textSecondary">ノルマ枚数</ThemedText>
          <ThemedTextInput testID="production-ticket-quota-count" value={quotaCount} onChangeText={setQuotaCount} keyboardType="number-pad" style={styles.input} />
          <TouchableOpacity testID="production-ticket-quota-buyback-toggle" onPress={() => setQuotaBuybackEnabled((v) => !v)} style={styles.toggleRow}>
            <ThemedText>{quotaBuybackEnabled ? '◉' : '○'} 買取にする</ThemedText>
          </TouchableOpacity>
          {quotaBuybackEnabled && (
            <>
              <ThemedText type="small" themeColor="textSecondary">未達チケット単価（円）</ThemedText>
              <ThemedTextInput
                testID="production-ticket-quota-shortfall-unit-price"
                value={quotaShortfallUnitPrice}
                onChangeText={setQuotaShortfallUnitPrice}
                keyboardType="number-pad"
                style={styles.input}
              />
            </>
          )}
        </>
      )}

      <ThemedText type="subtitle" style={styles.sectionTitle}>チケットバック</ThemedText>
      <TouchableOpacity testID="production-ticket-back-toggle" onPress={() => setTicketBackEnabled((v) => !v)} style={styles.toggleRow}>
        <ThemedText>{ticketBackEnabled ? '☑' : '☐'} チケットバックを設定する</ThemedText>
      </TouchableOpacity>

      {ticketBackEnabled && (
        <>
          <View style={styles.radioRow}>
            <TouchableOpacity testID="production-ticket-back-mode-progressive" onPress={() => setTicketBackMode('PROGRESSIVE')} style={styles.radioOption}>
              <ThemedText>{ticketBackMode === 'PROGRESSIVE' ? '◉' : '○'} 累進方式</ThemedText>
            </TouchableOpacity>
            <TouchableOpacity testID="production-ticket-back-mode-separated" onPress={() => setTicketBackMode('SEPARATED')} style={styles.radioOption}>
              <ThemedText>{ticketBackMode === 'SEPARATED' ? '◉' : '○'} 分離方式</ThemedText>
            </TouchableOpacity>
          </View>

          {ticketBackConditions.map((condition, index) => (
            <View key={index} style={styles.conditionRow} testID={`production-ticket-back-condition-${index}`}>
              <ThemedTextInput testID={`production-ticket-back-condition-${index}-priority`} value={String(condition.priority)} onChangeText={(v) => updateCondition(index, { priority: Number(v) || 0 })} keyboardType="number-pad" style={styles.smallInput} />
              <ThemedTextInput testID={`production-ticket-back-condition-${index}-threshold`} value={String(condition.threshold)} onChangeText={(v) => updateCondition(index, { threshold: Number(v) || 0 })} keyboardType="number-pad" style={styles.smallInput} />
              <ThemedTextInput testID={`production-ticket-back-condition-${index}-rate`} value={String(condition.rate_percent)} onChangeText={(v) => updateCondition(index, { rate_percent: Number(v) || 0 })} keyboardType="number-pad" style={styles.smallInput} />
              <TouchableOpacity testID={`production-ticket-back-condition-${index}-remove`} onPress={() => removeCondition(index)}>
                <ThemedText type="link">削除</ThemedText>
              </TouchableOpacity>
            </View>
          ))}
          <TouchableOpacity testID="production-ticket-back-add-condition" onPress={addCondition} style={styles.secondaryButton}>
            <ThemedText style={styles.secondaryButtonText}>＋ 条件を追加</ThemedText>
          </TouchableOpacity>
        </>
      )}

      {errorMessage && <ThemedText testID="production-ticket-quota-error" style={styles.error}>{errorMessage}</ThemedText>}

      <TouchableOpacity testID="production-ticket-quota-save" onPress={handleSave} disabled={updateSettings.isPending} style={styles.button}>
        {updateSettings.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>ノルマ／チケットバック設定を更新</ThemedText>}
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  page: { padding: Spacing.five, maxWidth: 960 },
  pageTitle: { marginBottom: Spacing.two, fontSize: 32, lineHeight: 40 },
  sectionTitle: { marginTop: Spacing.four, marginBottom: Spacing.one },
  caption: { marginBottom: Spacing.two },
  input: { borderWidth: 1, borderColor: '#ccc', borderRadius: 8, paddingHorizontal: Spacing.three, paddingVertical: Spacing.two, fontSize: 16, width: 360, maxWidth: '100%', marginBottom: Spacing.two },
  toggleRow: { paddingVertical: Spacing.one, marginBottom: Spacing.one },
  radioRow: { gap: Spacing.one, marginBottom: Spacing.two },
  radioOption: { paddingVertical: Spacing.half },
  conditionRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two, marginBottom: Spacing.one, flexWrap: 'wrap' },
  smallInput: { width: 90, borderWidth: 1, borderColor: '#ccc', borderRadius: 8, paddingHorizontal: Spacing.two, paddingVertical: Spacing.one },
  button: { backgroundColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.four, alignItems: 'center', alignSelf: 'flex-start', marginTop: Spacing.three },
  buttonText: { color: '#fff', fontWeight: '600' },
  secondaryButton: { borderWidth: 1, borderColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.one, paddingHorizontal: Spacing.three, alignSelf: 'flex-start', marginBottom: Spacing.two },
  secondaryButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  error: { color: '#a6483a', marginTop: Spacing.two },
});
