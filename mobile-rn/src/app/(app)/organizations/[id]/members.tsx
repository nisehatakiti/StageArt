import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Platform, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import {
  useCreateOrganizationInvitation,
  useOrganizationInvitations,
  useOrganizationMembers,
  useOrganizations,
} from '@/features/organization/useOrganizations';
import type { OrganizationMember } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

export default function OrganizationMembersScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const organizationsQuery = useOrganizations();
  const organization = organizationsQuery.data?.find((candidate) => candidate.id === id) ?? null;
  const role = organization?.current_person_role ?? null;
  const isOwner = role === 'OWNER';
  const canManage = isOwner || role === 'ORGANIZATION_MANAGER';

  const { query: membersQuery, updateRemarks, assignDelegate, revokeDelegate } = useOrganizationMembers(canManage ? id : undefined);
  const { query: invitationsQuery, resend, cancel } = useOrganizationInvitations(canManage ? id : undefined);
  const createInvitation = useCreateOrganizationInvitation(id);

  const pendingInvitations = (invitationsQuery.data ?? []).filter((invitation) => invitation.status === 'PENDING');
  const [remarksEdits, setRemarksEdits] = useState<Record<string, string>>({});
  const [newMemberName, setNewMemberName] = useState('');
  const [newMemberEmail, setNewMemberEmail] = useState('');
  const [newMemberRemarks, setNewMemberRemarks] = useState('');
  const [saving, setSaving] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [savedMessage, setSavedMessage] = useState<string | null>(null);
  const [addMemberMessage, setAddMemberMessage] = useState<string | null>(null);

  useEffect(() => {
    if (membersQuery.data) {
      setRemarksEdits((current) => {
        const next = { ...current };
        for (const member of membersQuery.data) {
          if (next[member.person_id] === undefined) {
            next[member.person_id] = member.remarks ?? '';
          }
        }
        return next;
      });
    }
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

  async function handleAddMember() {
    const name = newMemberName.trim();
    const email = newMemberEmail.trim();
    if (!name || !email || createInvitation.isPending) return;

    setErrorMessage(null);
    setAddMemberMessage(null);
    try {
      const result = await createInvitation.mutateAsync({
        name,
        email,
        remarks: newMemberRemarks.trim() || undefined,
      });
      setAddMemberMessage(
        result.outcome === 'MEMBER_ADDED'
          ? 'メンバーを追加しました。'
          : result.outcome === 'INVITATION_RESENT'
            ? '既に招待済みのため、登録案内メールを再送しました。'
            : '登録案内メールを送信しました。本人の登録が完了するとメンバーへ追加されます。'
      );
      setNewMemberName('');
      setNewMemberEmail('');
      setNewMemberRemarks('');
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handleToggleDelegate(member: OrganizationMember) {
    if (!isOwner || member.role_key === 'OWNER') return;
    setErrorMessage(null);
    try {
      if (member.role_key === 'ORGANIZATION_MANAGER') {
        await revokeDelegate.mutateAsync(member.person_id);
      } else {
        await assignDelegate.mutateAsync(member.person_id);
      }
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  if (organizationsQuery.isLoading) return <ActivityIndicator testID="organization-members-loading" />;
  if (organizationsQuery.isError) return <ThemedText testID="organization-members-error">{getErrorMessage(organizationsQuery.error)}</ThemedText>;
  if (!organization) return <ThemedText testID="organization-members-not-found">この団体が見つからないか、参加していません。</ThemedText>;
  if (!canManage) {
    return <ThemedText testID="organization-members-list-unavailable" themeColor="textSecondary">メンバー一覧の閲覧・管理は、オーナーまたは代理人のみ行えます。</ThemedText>;
  }

  const memberTable = (
    <View style={styles.memberTable} testID="organization-members-list">
      <View style={styles.memberTableHeader}>
        <ThemedText style={styles.nameHeader}>名前</ThemedText>
        <ThemedText style={styles.roleHeader}>役割</ThemedText>
        <ThemedText style={styles.remarksHeader}>備考</ThemedText>
        <ThemedText style={styles.emailHeader}>メールアドレス</ThemedText>
        <ThemedText style={styles.actionHeader}>操作</ThemedText>
        {isOwner && <ThemedText style={styles.delegateHeader}>代理人</ThemedText>}
      </View>

      {(membersQuery.data ?? []).map((member) => (
        <View key={member.membership_id} style={styles.memberTableRow} testID={`organization-member-${member.person_id}`}>
          <ThemedText style={styles.nameCell}>
            {member.person_family_name ?? ''} {member.person_given_name ?? ''}
          </ThemedText>
          <ThemedText style={styles.roleCell}>{roleLabel(member.role_key)}</ThemedText>
          <ThemedTextInput
            testID={`organization-member-remarks-${member.person_id}`}
            value={remarksEdits[member.person_id] ?? ''}
            onChangeText={(value) => setRemarksEdits((current) => ({ ...current, [member.person_id]: value }))}
            placeholder="備考"
            style={styles.remarksCell}
          />
          <ThemedText type="small" themeColor="textSecondary" style={styles.emailCell}>
            {member.email ?? ''}
          </ThemedText>
          <View style={styles.actionCell} />
          {isOwner && (
            <TouchableOpacity
              testID={`organization-member-delegate-${member.person_id}`}
              accessibilityRole="checkbox"
              accessibilityState={{ checked: member.role_key === 'ORGANIZATION_MANAGER', disabled: member.role_key === 'OWNER' || assignDelegate.isPending || revokeDelegate.isPending }}
              disabled={member.role_key === 'OWNER' || assignDelegate.isPending || revokeDelegate.isPending}
              onPress={() => handleToggleDelegate(member)}
              style={styles.delegateCell}
            >
              <ThemedText style={styles.checkbox}>{member.role_key === 'ORGANIZATION_MANAGER' ? '☑' : '□'}</ThemedText>
            </TouchableOpacity>
          )}
        </View>
      ))}

      <View style={styles.addMemberTableRow} testID="organization-members-add-member-form">
        <ThemedTextInput
          testID="organization-members-new-member-name"
          value={newMemberName}
          onChangeText={setNewMemberName}
          placeholder="氏名"
          style={styles.addNameInput}
        />
        <View style={styles.addRoleCell}>
          <ThemedText type="small" themeColor="textSecondary">メンバー</ThemedText>
        </View>
        <ThemedTextInput
          testID="organization-members-new-member-remarks"
          value={newMemberRemarks}
          onChangeText={setNewMemberRemarks}
          placeholder="備考"
          style={styles.addRemarksInput}
        />
        <ThemedTextInput
          testID="organization-members-new-member-email"
          value={newMemberEmail}
          onChangeText={setNewMemberEmail}
          placeholder="メールアドレス"
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="email-address"
          style={styles.addEmailInput}
        />
        <TouchableOpacity
          testID="organization-members-add-member"
          onPress={handleAddMember}
          disabled={!newMemberName.trim() || !newMemberEmail.trim() || createInvitation.isPending}
          style={styles.addMemberAction}
        >
          {createInvitation.isPending ? <ActivityIndicator color={BrandColors.warmAmber} /> : <ThemedText style={styles.addButtonText}>＋ メンバーを追加</ThemedText>}
        </TouchableOpacity>
        {isOwner && <View style={styles.delegateCell} />}
      </View>
    </View>
  );

  const invitationTable = (
    <View style={styles.invitationTable} testID="organization-invitations-list">
      <View style={styles.memberTableHeader}>
        <ThemedText style={styles.invitationNameHeader}>氏名</ThemedText>
        <ThemedText style={styles.invitationEmailHeader}>メールアドレス</ThemedText>
        <ThemedText style={styles.invitationStatusHeader}>状態</ThemedText>
        <ThemedText style={styles.invitationExpiresHeader}>有効期限</ThemedText>
        <View style={styles.invitationActionsHeader} />
      </View>
      {pendingInvitations.map((invitation) => (
        <View key={invitation.id} style={styles.memberTableRow} testID={`organization-invitation-row-${invitation.id}`}>
          <ThemedText style={styles.invitationNameCell}>{invitation.name}</ThemedText>
          <ThemedText style={styles.invitationEmailCell}>{invitation.email}</ThemedText>
          <ThemedText style={styles.invitationStatusCell}>{invitation.is_expired ? '期限切れ' : '招待中'}</ThemedText>
          <ThemedText type="small" themeColor="textSecondary" style={styles.invitationExpiresCell}>{new Date(invitation.expires_at).toLocaleString('ja-JP')}</ThemedText>
          <View style={styles.invitationActionsCell}>
            <TouchableOpacity testID={`organization-invitation-resend-${invitation.id}`} onPress={() => resend.mutate(invitation.id)} disabled={resend.isPending || cancel.isPending} style={styles.invitationActionButton}>
              <ThemedText type="small">再送</ThemedText>
            </TouchableOpacity>
            <TouchableOpacity testID={`organization-invitation-cancel-${invitation.id}`} onPress={() => cancel.mutate(invitation.id)} disabled={resend.isPending || cancel.isPending} style={styles.invitationActionButton}>
              <ThemedText type="small">取消</ThemedText>
            </TouchableOpacity>
          </View>
        </View>
      ))}
    </View>
  );

  return (
    <ScrollView contentContainerStyle={styles.pageContainer}>
      <ThemedText type="title" style={styles.pageTitle}>メンバー管理</ThemedText>

      <View style={styles.actionsRow}>
        {isOwner && (
          <TouchableOpacity testID="organization-members-owner-transfer" onPress={() => router.push(`/organizations/${id}/members/owner-transfer` as Href)} style={styles.secondaryButton}>
            <ThemedText style={styles.secondaryButtonText}>代表者交代</ThemedText>
          </TouchableOpacity>
        )}
        <TouchableOpacity testID="organization-members-invite" onPress={() => router.push(`/organizations/${id}/invite` as Href)} style={styles.secondaryButton}>
          <ThemedText style={styles.secondaryButtonText}>参加コード</ThemedText>
        </TouchableOpacity>
      </View>

      <ThemedText type="subtitle" style={styles.sectionTitle}>登録済みメンバー</ThemedText>

      {membersQuery.isLoading && <ActivityIndicator testID="organization-members-list-loading" />}
      {membersQuery.isError && <ThemedText testID="organization-members-list-error">{getErrorMessage(membersQuery.error)}</ThemedText>}
      {!membersQuery.isLoading && !membersQuery.isError && (
        Platform.OS === 'web' ? (
          <View style={styles.webTableScroll} testID="organization-members-table-scroll">{memberTable}</View>
        ) : (
          <ScrollView horizontal showsHorizontalScrollIndicator testID="organization-members-table-scroll">{memberTable}</ScrollView>
        )
      )}

      {addMemberMessage && <ThemedText testID="organization-members-add-result" style={styles.success}>{addMemberMessage}</ThemedText>}
      {errorMessage && <ThemedText testID="organization-members-save-error" style={styles.error}>{errorMessage}</ThemedText>}
      {savedMessage && <ThemedText testID="organization-members-saved" style={styles.success}>{savedMessage}</ThemedText>}

      <TouchableOpacity testID="organization-members-save" onPress={handleSave} disabled={saving} style={[styles.button, saving && styles.buttonDisabled]}>
        {saving ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>更新</ThemedText>}
      </TouchableOpacity>

      {pendingInvitations.length > 0 && (
        <>
          <ThemedText type="subtitle" style={styles.sectionTitle}>招待中のメンバー</ThemedText>
          {Platform.OS === 'web' ? (
            <View style={styles.webTableScroll} testID="organization-invitations-table-scroll">{invitationTable}</View>
          ) : (
            <ScrollView horizontal showsHorizontalScrollIndicator testID="organization-invitations-table-scroll">{invitationTable}</ScrollView>
          )}
        </>
      )}
    </ScrollView>
  );
}

function roleLabel(roleKey: string | null): string {
  switch (roleKey) {
    case 'OWNER': return 'オーナー（代表者）';
    case 'ORGANIZATION_MANAGER': return '代理人';
    default: return 'メンバー';
  }
}

const styles = StyleSheet.create({
  pageContainer: { padding: Spacing.five, paddingBottom: Spacing.six },
  pageTitle: { marginBottom: Spacing.two },
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.one },
  actionsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two, marginBottom: Spacing.three },
  secondaryButton: { borderWidth: 1, borderColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.three },
  secondaryButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  webTableScroll: { width: '100%', overflow: 'scroll', maxWidth: '100%', marginBottom: Spacing.two },
  memberTable: { minWidth: 1220, borderWidth: 1, borderColor: '#ddd', backgroundColor: '#fff' },
  memberTableHeader: { flexDirection: 'row', alignItems: 'stretch', backgroundColor: '#f5f3ef', borderBottomWidth: 1, borderBottomColor: '#ddd' },
  memberTableRow: { flexDirection: 'row', alignItems: 'stretch', minHeight: 58, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: '#e5e5e5' },
  nameHeader: { width: 180, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  nameCell: { width: 180, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600', textAlignVertical: 'center' },
  roleHeader: { width: 150, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  roleCell: { width: 150, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  remarksHeader: { width: 260, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  remarksCell: { width: 244, margin: Spacing.one, borderWidth: 1, borderColor: '#ccc', borderRadius: 6, paddingHorizontal: Spacing.one, paddingVertical: 6 },
  emailHeader: { width: 260, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  emailCell: { width: 260, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  actionHeader: { width: 190, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600', textAlign: 'center' },
  actionCell: { width: 190 },
  delegateHeader: { width: 100, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600', textAlign: 'center' },
  delegateCell: { width: 100, justifyContent: 'center', alignItems: 'center' },
  checkbox: { fontSize: 22 },
  addMemberTableRow: { flexDirection: 'row', alignItems: 'stretch', minHeight: 58, backgroundColor: '#fffaf0' },
  addNameInput: { width: 170, margin: Spacing.one, borderWidth: 1, borderColor: '#ccc', borderRadius: 6, paddingHorizontal: Spacing.one, paddingVertical: 6 },
  addRoleCell: { width: 150, justifyContent: 'center', paddingHorizontal: Spacing.two },
  addRemarksInput: { width: 244, margin: Spacing.one, borderWidth: 1, borderColor: '#ccc', borderRadius: 6, paddingHorizontal: Spacing.one, paddingVertical: 6 },
  addEmailInput: { width: 260, margin: Spacing.one, borderWidth: 1, borderColor: '#ccc', borderRadius: 6, paddingHorizontal: Spacing.one, paddingVertical: 6 },
  addMemberAction: { width: 190, justifyContent: 'center', paddingHorizontal: Spacing.two },
  addButtonText: { color: BrandColors.warmAmber, fontWeight: '700' },
  error: { color: '#a6483a', marginTop: Spacing.two },
  success: { color: '#2e7d32', marginTop: Spacing.two },
  button: { backgroundColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.four, alignItems: 'center', marginTop: Spacing.three, alignSelf: 'flex-start' },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  invitationTable: { minWidth: 920, borderWidth: 1, borderColor: '#ddd', backgroundColor: '#fff' },
  invitationNameHeader: { width: 180, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  invitationNameCell: { width: 180, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  invitationEmailHeader: { width: 280, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  invitationEmailCell: { width: 280, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  invitationStatusHeader: { width: 120, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  invitationStatusCell: { width: 120, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  invitationExpiresHeader: { width: 200, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  invitationExpiresCell: { width: 200, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  invitationActionsHeader: { width: 140 },
  invitationActionsCell: { width: 140, flexDirection: 'row', gap: Spacing.two, alignItems: 'center', paddingHorizontal: Spacing.two },
  invitationActionButton: { borderWidth: 1, borderColor: '#ccc', borderRadius: Radius.medium, paddingVertical: 4, paddingHorizontal: 8 },
});
