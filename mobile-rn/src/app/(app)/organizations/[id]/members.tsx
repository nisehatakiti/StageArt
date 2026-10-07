import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Platform, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useOrganizationInvitations, useOrganizationMembers, useOrganizations } from '@/features/organization/useOrganizations';
import type { OrganizationMember } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Organization管理UI/UX統一 "メンバー管理": Productionのメンバー
 * 管理画面 (productions/[id]/participants.tsx) を基準に、
 * 「メンバー一覧を中心にして、上部に操作をまとめる」構成へ全面的に作り直す -
 * 「あなたの役割」カードや単純なメンバー一覧カードをバラバラに置かない。
 * 代理人を設定/代表者交代は単純なroleトグルではなく専用画面のまま（この回の
 * 確認済み仕様）- ここでは「代理人」列は状態表示のみ。備考はProductionの
 * 「更新」ボタンと同じ、1つの保存ボタンでまとめて送る編集フィールド。
 */
export default function OrganizationMembersScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const organizationsQuery = useOrganizations();
  const router = useRouter();

  const organization = organizationsQuery.data?.find((candidate) => candidate.id === id) ?? null;
  const role = organization?.current_person_role ?? null;
  const isOwner = role === 'OWNER';
  const isManager = role === 'ORGANIZATION_MANAGER';
  const canManage = isOwner || isManager;

  const { query: membersQuery, updateRemarks } = useOrganizationMembers(canManage ? id : undefined);
  const { query: invitationsQuery, resend, cancel } = useOrganizationInvitations(canManage ? id : undefined);
  const pendingInvitations = (invitationsQuery.data ?? []).filter((invitation) => invitation.status === 'PENDING');

  const [remarksEdits, setRemarksEdits] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [savedMessage, setSavedMessage] = useState<string | null>(null);

  useEffect(() => {
    if (membersQuery.data) {
      setRemarksEdits((current) => {
        const next = { ...current };
        for (const member of membersQuery.data ?? []) {
          if (next[member.person_id] === undefined) {
            next[member.person_id] = member.remarks ?? '';
          }
        }
        return next;
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [membersQuery.data]);

  async function handleSave() {
    setSaving(true);
    setErrorMessage(null);
    setSavedMessage(null);

    try {
      for (const member of membersQuery.data ?? []) {
        const edited = remarksEdits[member.person_id] ?? '';
        if (edited !== (member.remarks ?? '')) {
          await updateRemarks.mutateAsync({ personId: member.person_id, remarks: edited.trim() ? edited.trim() : null });
        }
      }
      setSavedMessage('メンバー情報を保存しました。');
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  if (organizationsQuery.isLoading) {
    return <ActivityIndicator testID="organization-members-loading" />;
  }

  if (organizationsQuery.isError) {
    return <ThemedText testID="organization-members-error">{getErrorMessage(organizationsQuery.error)}</ThemedText>;
  }

  if (!organization) {
    return <ThemedText testID="organization-members-not-found">この団体が見つからないか、参加していません。</ThemedText>;
  }

  if (!canManage) {
    return (
      <ThemedText testID="organization-members-list-unavailable" themeColor="textSecondary">
        メンバー一覧の閲覧・管理は、オーナーまたは代理人のみ行えます。
      </ThemedText>
    );
  }

  const memberTable = (
    <View style={styles.table} testID="organization-members-list">
      <View style={styles.tableHeader}>
        <ThemedText style={styles.nameHeader}>名前</ThemedText>
        <ThemedText style={styles.roleHeader}>役割</ThemedText>
        <ThemedText style={styles.emailHeader}>メールアドレス</ThemedText>
        <ThemedText style={styles.remarksHeader}>備考</ThemedText>
        <ThemedText style={styles.delegateHeader}>代理人</ThemedText>
        <ThemedText style={styles.actionsHeader}>操作</ThemedText>
      </View>

      {(membersQuery.data ?? []).map((member) => (
        <MemberRow
          key={member.membership_id}
          member={member}
          remarks={remarksEdits[member.person_id] ?? ''}
          onChangeRemarks={(value) => setRemarksEdits((current) => ({ ...current, [member.person_id]: value }))}
        />
      ))}
    </View>
  );

  const invitationTable = (
    <View style={styles.invitationTable} testID="organization-invitations-list">
      <View style={styles.tableHeader}>
        <ThemedText style={styles.invitationNameHeader}>氏名</ThemedText>
        <ThemedText style={styles.invitationEmailHeader}>メールアドレス</ThemedText>
        <ThemedText style={styles.invitationStatusHeader}>状態</ThemedText>
        <ThemedText style={styles.invitationExpiresHeader}>有効期限</ThemedText>
        <View style={styles.invitationActionsHeader} />
      </View>
      {pendingInvitations.map((invitation) => (
        <View key={invitation.id} style={styles.tableRow} testID={`organization-invitation-row-${invitation.id}`}>
          <ThemedText style={styles.invitationNameCell}>{invitation.name}</ThemedText>
          <ThemedText style={styles.invitationEmailCell}>{invitation.email}</ThemedText>
          <ThemedText style={styles.invitationStatusCell}>招待中</ThemedText>
          <ThemedText type="small" themeColor="textSecondary" style={styles.invitationExpiresCell}>
            {new Date(invitation.expires_at).toLocaleString('ja-JP')}
          </ThemedText>
          <View style={styles.invitationActionsCell}>
            <TouchableOpacity
              testID={`organization-invitation-resend-${invitation.id}`}
              onPress={() => resend.mutate(invitation.id)}
              disabled={resend.isPending || cancel.isPending}
              style={styles.invitationActionButton}
            >
              <ThemedText type="small">再送</ThemedText>
            </TouchableOpacity>
            <TouchableOpacity
              testID={`organization-invitation-cancel-${invitation.id}`}
              onPress={() => cancel.mutate(invitation.id)}
              disabled={resend.isPending || cancel.isPending}
              style={styles.invitationActionButton}
            >
              <ThemedText type="small">取消</ThemedText>
            </TouchableOpacity>
          </View>
        </View>
      ))}
    </View>
  );

  return (
    <ScrollView contentContainerStyle={styles.pageContainer}>
      <ThemedText type="title" style={styles.pageTitle}>
        メンバー管理
      </ThemedText>

      <View style={styles.actionsRow} testID="organization-members-actions">
        <TouchableOpacity testID="organization-members-add" onPress={() => router.push(`/organizations/${id}/members/add` as Href)} style={styles.primaryButton}>
          <ThemedText style={styles.primaryButtonText}>メンバーを追加</ThemedText>
        </TouchableOpacity>
        {isOwner && (
          <TouchableOpacity testID="organization-members-delegate" onPress={() => router.push(`/organizations/${id}/members/delegate` as Href)} style={styles.secondaryButton}>
            <ThemedText style={styles.secondaryButtonText}>代理人を設定</ThemedText>
          </TouchableOpacity>
        )}
        {isOwner && (
          <TouchableOpacity
            testID="organization-members-owner-transfer"
            onPress={() => router.push(`/organizations/${id}/members/owner-transfer` as Href)}
            style={styles.secondaryButton}
          >
            <ThemedText style={styles.secondaryButtonText}>代表者交代</ThemedText>
          </TouchableOpacity>
        )}
        <TouchableOpacity testID="organization-members-invite" onPress={() => router.push(`/organizations/${id}/invite` as Href)} style={styles.secondaryButton}>
          <ThemedText style={styles.secondaryButtonText}>参加コード</ThemedText>
        </TouchableOpacity>
      </View>

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        登録済みメンバー
      </ThemedText>

      {membersQuery.isLoading && <ActivityIndicator testID="organization-members-list-loading" />}
      {membersQuery.isError && <ThemedText testID="organization-members-list-error">{getErrorMessage(membersQuery.error)}</ThemedText>}

      {!membersQuery.isLoading &&
        !membersQuery.isError &&
        (Platform.OS === 'web' ? (
          <View style={styles.webTableScroll} testID="organization-members-table-scroll">
            {memberTable}
          </View>
        ) : (
          <ScrollView horizontal showsHorizontalScrollIndicator testID="organization-members-table-scroll">
            {memberTable}
          </ScrollView>
        ))}

      {errorMessage && (
        <ThemedText testID="organization-members-save-error" style={styles.error}>
          {errorMessage}
        </ThemedText>
      )}
      {savedMessage && (
        <ThemedText testID="organization-members-saved" style={styles.success}>
          {savedMessage}
        </ThemedText>
      )}

      <TouchableOpacity testID="organization-members-save" onPress={handleSave} disabled={saving} style={[styles.button, saving && styles.buttonDisabled]}>
        {saving ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>保存する</ThemedText>}
      </TouchableOpacity>

      {pendingInvitations.length > 0 && (
        <>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            招待中のメンバー
          </ThemedText>
          {Platform.OS === 'web' ? (
            <View style={styles.webTableScroll} testID="organization-invitations-table-scroll">
              {invitationTable}
            </View>
          ) : (
            <ScrollView horizontal showsHorizontalScrollIndicator testID="organization-invitations-table-scroll">
              {invitationTable}
            </ScrollView>
          )}
        </>
      )}
    </ScrollView>
  );
}

function MemberRow({
  member,
  remarks,
  onChangeRemarks,
}: {
  member: OrganizationMember;
  remarks: string;
  onChangeRemarks: (value: string) => void;
}) {
  return (
    <View style={styles.tableRow} testID={`organization-member-${member.person_id}`}>
      <ThemedText style={styles.nameCell}>
        {member.person_family_name ?? ''} {member.person_given_name ?? ''}
      </ThemedText>
      <ThemedText style={styles.roleCell}>{roleLabel(member.role_key)}</ThemedText>
      <ThemedText testID={`organization-member-email-${member.person_id}`} type="small" themeColor="textSecondary" style={styles.emailCell}>
        {member.email ?? ''}
      </ThemedText>
      <ThemedTextInput
        testID={`organization-member-remarks-${member.person_id}`}
        value={remarks}
        onChangeText={onChangeRemarks}
        placeholder="備考"
        style={styles.remarksCell}
      />
      <View style={styles.delegateCell}>
        {member.role_key === 'ORGANIZATION_MANAGER' && (
          <ThemedText type="small" style={styles.delegateBadgeText}>
            代理人
          </ThemedText>
        )}
      </View>
      <View style={styles.actionsCell} />
    </View>
  );
}

function roleLabel(roleKey: string | null): string {
  switch (roleKey) {
    case 'OWNER':
      return 'オーナー（代表者）';
    case 'ORGANIZATION_MANAGER':
      return '代理人';
    case 'MEMBER':
      return 'メンバー';
    default:
      return 'メンバー';
  }
}

const styles = StyleSheet.create({
  pageContainer: { padding: Spacing.five, paddingBottom: Spacing.six },
  pageTitle: { marginBottom: Spacing.two },
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.one },
  actionsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two, marginBottom: Spacing.three },
  primaryButton: { backgroundColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.three },
  primaryButtonText: { color: '#fff', fontWeight: '600' },
  secondaryButton: { borderWidth: 1, borderColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.three },
  secondaryButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  webTableScroll: { width: '100%', overflow: 'scroll', maxWidth: '100%', marginBottom: Spacing.two },
  table: { minWidth: 1160, borderWidth: 1, borderColor: '#ddd', backgroundColor: '#fff' },
  tableHeader: { flexDirection: 'row', alignItems: 'stretch', backgroundColor: '#f5f3ef', borderBottomWidth: 1, borderBottomColor: '#ddd' },
  tableRow: { flexDirection: 'row', alignItems: 'stretch', minHeight: 58, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: '#e5e5e5' },
  nameHeader: { width: 180, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  nameCell: { width: 180, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600', textAlignVertical: 'center' },
  roleHeader: { width: 170, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  roleCell: { width: 170, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  emailHeader: { width: 260, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  emailCell: { width: 260, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  remarksHeader: { width: 300, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  remarksCell: { width: 284, margin: Spacing.one, borderWidth: 1, borderColor: '#ccc', borderRadius: 6, paddingHorizontal: Spacing.one, paddingVertical: 6 },
  delegateHeader: { width: 100, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600', textAlign: 'center' },
  delegateCell: { width: 100, justifyContent: 'center', alignItems: 'center' },
  delegateBadgeText: { color: BrandColors.warmAmber, fontWeight: '600' },
  actionsHeader: { width: 150, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600', textAlign: 'center' },
  actionsCell: { width: 150 },
  error: { color: '#a6483a', marginTop: Spacing.two },
  success: { color: '#2e7d32', marginTop: Spacing.two },
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
  invitationTable: { minWidth: 920, borderWidth: 1, borderColor: '#ddd', backgroundColor: '#fff' },
  invitationNameHeader: { width: 160, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  invitationNameCell: { width: 160, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  invitationEmailHeader: { width: 260, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  invitationEmailCell: { width: 260, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  invitationStatusHeader: { width: 100, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  invitationStatusCell: { width: 100, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  invitationExpiresHeader: { width: 200, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  invitationExpiresCell: { width: 200, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  invitationActionsHeader: { width: 140 },
  invitationActionsCell: { width: 140, flexDirection: 'row', gap: Spacing.two, alignItems: 'center', paddingHorizontal: Spacing.two },
  invitationActionButton: { borderWidth: 1, borderColor: '#ccc', borderRadius: Radius.medium, paddingVertical: 4, paddingHorizontal: 8 },
});
