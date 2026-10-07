import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useOrganizationMembers, useOrganizations, useTransferOrganizationOwner } from '@/features/organization/useOrganizations';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt 団体管理UI整理 "代表者交代": owner-only
 * (OwnerTransferUseCase - "既存メンバーから新しい代表者を選択して実行する").
 * An inline confirm step (select -> confirm -> execute) stands in for a
 * native confirmation dialog, so this stays testable the same way the
 * rest of this app's destructive actions are. On success, navigates to
 * Home (per the confirmed spec: "処理成功後はOrganization Contextではなく
 * Home画面へ移動する") - this Person is no longer the Owner, so staying
 * inside this same Organization Context's management screens would be
 * confusing.
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
          <ThemedText type="smallBold">
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
              style={styles.primaryButton}
            >
              {transferOwner.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.primaryButtonText}>交代する</ThemedText>}
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
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    padding: Spacing.four,
    gap: Spacing.three,
    maxWidth: 480,
  },
  actions: { flexDirection: 'row', gap: Spacing.two },
  primaryButton: { backgroundColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.four, alignItems: 'center' },
  primaryButtonText: { color: '#fff', fontWeight: '600' },
  secondaryButton: { borderWidth: 1, borderColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.four, alignItems: 'center' },
  secondaryButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  error: { color: '#a6483a' },
  backLink: { marginTop: Spacing.three },
});
