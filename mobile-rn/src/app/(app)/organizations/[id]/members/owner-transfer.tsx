import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Radius, Spacing } from '@/constants/theme';
import { useOrganizationMembers, useOrganizations, useTransferOrganizationOwner } from '@/features/organization/useOrganizations';
import { getErrorMessage } from '@/utils/errorMessage';

const DANGER_COLOR = '#a6483a';

/**
 * StageArt Organization管理UI/UX統一 "代表者交代": select -> inline
 * confirm -> the real POST /organizations/{id}/owner-transfer
 * (OwnerTransferUseCase - そのまま利用、ロジックは変更しない)。この回の§7
 * により、確認画面は「危険な操作」として視覚的に分かるよう、現在の代表者→
 * 新しい代表者を明示する（amberではなくエラー色で強調）。処理成功後は
 * Organization ContextではなくHome画面へ移動する。
 */
export default function OrganizationMembersOwnerTransferScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const organizationsQuery = useOrganizations();
  const organization = organizationsQuery.data?.find((candidate) => candidate.id === id) ?? null;
  const isOwner = organization?.current_person_role === 'OWNER';

  const { query: membersQuery } = useOrganizationMembers(isOwner ? id : undefined);
  const transferOwner = useTransferOrganizationOwner(id);

  const [selectedPersonId, setSelectedPersonId] = useState<string | null>(null);

  if (organizationsQuery.isLoading || membersQuery.isLoading) {
    return <ActivityIndicator testID="organization-owner-transfer-loading" />;
  }

  if (!organization) {
    return <ThemedText testID="organization-owner-transfer-not-found">この団体が見つからないか、参加していません。</ThemedText>;
  }

  if (!isOwner) {
    return <ThemedText testID="organization-owner-transfer-forbidden">代表者交代はオーナーのみ行えます。</ThemedText>;
  }

  const currentOwner = (membersQuery.data ?? []).find((member) => member.role_key === 'OWNER') ?? null;
  const candidates = (membersQuery.data ?? []).filter((member) => member.role_key !== 'OWNER');
  const selected = candidates.find((member) => member.person_id === selectedPersonId) ?? null;

  async function handleConfirm() {
    if (!selected) {
      return;
    }

    await transferOwner.mutateAsync(selected.person_id);
    router.replace('/dashboard' as Href);
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <ThemedText type="title" style={styles.pageTitle}>
        代表者交代
      </ThemedText>
      <ThemedText type="small" themeColor="textSecondary" style={styles.lead}>
        新しい代表者を選択してください。交代を実行すると、あなたは通常のメンバー権限になります。
      </ThemedText>

      {membersQuery.isError && (
        <ThemedText testID="organization-owner-transfer-error">{getErrorMessage(membersQuery.error)}</ThemedText>
      )}

      {!selected && (
        <View style={styles.list} testID="organization-owner-transfer-candidate-list">
          {candidates.length === 0 && (
            <ThemedText type="small" themeColor="textSecondary" testID="organization-owner-transfer-empty">
              交代できるメンバーがいません。先に「メンバーを追加」してください。
            </ThemedText>
          )}

          {candidates.map((member) => (
            <TouchableOpacity
              key={member.membership_id}
              testID={`organization-owner-transfer-candidate-${member.person_id}`}
              style={styles.row}
              onPress={() => setSelectedPersonId(member.person_id)}
            >
              <ThemedText type="smallBold">
                {member.person_family_name ?? ''} {member.person_given_name ?? ''}
              </ThemedText>
              <ThemedText type="small" themeColor="textSecondary">
                {member.role_key === 'ORGANIZATION_MANAGER' ? '代理人' : 'メンバー'}
              </ThemedText>
            </TouchableOpacity>
          ))}
        </View>
      )}

      {selected && (
        <View style={styles.confirmCard} testID="organization-owner-transfer-confirm">
          <ThemedText type="smallBold" style={styles.dangerLabel}>
            ⚠ この操作は取り消せません
          </ThemedText>

          <View style={styles.transferDiagram} testID="organization-owner-transfer-diagram">
            <View style={styles.transferBox}>
              <ThemedText type="small" themeColor="textSecondary">
                現在の代表者
              </ThemedText>
              <ThemedText type="smallBold">
                {currentOwner ? `${currentOwner.person_family_name ?? ''} ${currentOwner.person_given_name ?? ''}` : 'あなた'}
              </ThemedText>
            </View>
            <ThemedText style={styles.transferArrow}>↓</ThemedText>
            <View style={styles.transferBox}>
              <ThemedText type="small" themeColor="textSecondary">
                新しい代表者
              </ThemedText>
              <ThemedText type="smallBold">
                {selected.person_family_name ?? ''} {selected.person_given_name ?? ''}
              </ThemedText>
            </View>
          </View>

          <ThemedText type="small">
            本当に代表者を {selected.person_family_name ?? ''} {selected.person_given_name ?? ''} さんに交代しますか？
          </ThemedText>

          {transferOwner.isError && (
            <ThemedText testID="organization-owner-transfer-confirm-error" style={styles.error}>
              {getErrorMessage(transferOwner.error)}
            </ThemedText>
          )}

          <View style={styles.actions}>
            <TouchableOpacity
              testID="organization-owner-transfer-confirm-submit"
              onPress={handleConfirm}
              disabled={transferOwner.isPending}
              style={styles.dangerButton}
            >
              {transferOwner.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.dangerButtonText}>交代する</ThemedText>}
            </TouchableOpacity>
            <TouchableOpacity
              testID="organization-owner-transfer-confirm-cancel"
              onPress={() => setSelectedPersonId(null)}
              disabled={transferOwner.isPending}
              style={styles.secondaryButton}
            >
              <ThemedText style={styles.secondaryButtonText}>やめる</ThemedText>
            </TouchableOpacity>
          </View>
        </View>
      )}

      <TouchableOpacity testID="organization-owner-transfer-back" onPress={() => router.push(`/organizations/${id}/members` as Href)} style={styles.backLink}>
        <ThemedText type="link">メンバー管理へ戻る</ThemedText>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.two },
  pageTitle: { marginBottom: Spacing.one },
  lead: { marginBottom: Spacing.three, maxWidth: 480 },
  list: { gap: Spacing.two, maxWidth: 480 },
  row: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.half,
  },
  confirmCard: {
    borderWidth: 2,
    borderColor: DANGER_COLOR,
    borderRadius: Radius.medium,
    padding: Spacing.four,
    gap: Spacing.three,
    maxWidth: 480,
  },
  dangerLabel: { color: DANGER_COLOR },
  transferDiagram: { alignItems: 'center', gap: Spacing.one },
  transferBox: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
    minWidth: 180,
  },
  transferArrow: { fontSize: 20, color: DANGER_COLOR },
  actions: { flexDirection: 'row', gap: Spacing.two },
  dangerButton: { backgroundColor: DANGER_COLOR, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.four, alignItems: 'center' },
  dangerButtonText: { color: '#fff', fontWeight: '600' },
  secondaryButton: { borderWidth: 1, borderColor: DANGER_COLOR, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.four, alignItems: 'center' },
  secondaryButtonText: { color: DANGER_COLOR, fontWeight: '600' },
  error: { color: DANGER_COLOR },
  backLink: { marginTop: Spacing.three },
});
