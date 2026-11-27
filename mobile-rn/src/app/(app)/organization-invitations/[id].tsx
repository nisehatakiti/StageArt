import { useLocalSearchParams, useRouter } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useMyOrganizationInvitation } from '@/features/organization/useOrganizations';
import { getErrorMessage } from '@/utils/errorMessage';

const STATUS_TEXT = {
  REGISTERED: 'この団体のメンバーとして登録されています。',
  PENDING: '招待されたメールアドレスの確認が完了すると、この団体のメンバーとして登録されます。',
  EXPIRED: 'この招待は有効期限が切れています。団体の管理者に再送を依頼してください。',
  CANCELLED: 'この招待は取り消されました。',
  CLOSED: 'この招待は終了しています。',
} as const;

/**
 * The screen the organization invitation mail's link opens,
 * `/organization-invitations/{id}` - the same rule as the Production one
 * (participant-invitations/[id].tsx; invitation policy 2026-11-27): login
 * first, nothing to accept or reject, shown only to the account whose
 * verified address is the invited one.
 */
export default function OrganizationInvitationScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const invitationQuery = useMyOrganizationInvitation(id);
  const invitation = invitationQuery.data;

  const toHome = (
    <TouchableOpacity testID="organization-invitation-home" onPress={() => router.replace('/home')}>
      <ThemedText type="link">ホームへ</ThemedText>
    </TouchableOpacity>
  );

  if (invitationQuery.isLoading) {
    return <ActivityIndicator testID="organization-invitation-loading" />;
  }

  if (invitationQuery.isError || !invitation) {
    return (
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText testID="organization-invitation-not-shown">
          この招待は表示できません。招待メールを受け取ったメールアドレスでログインしているか確認してください。
        </ThemedText>
        {invitationQuery.isError && (
          <ThemedText type="small" themeColor="textSecondary" testID="organization-invitation-load-error">
            {getErrorMessage(invitationQuery.error)}
          </ThemedText>
        )}
        {toHome}
      </ScrollView>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <ThemedText type="title" style={styles.title}>
        団体への招待
      </ThemedText>

      <View style={styles.details} testID="organization-invitation-details">
        <ThemedText type="smallBold">{invitation.organization_name}</ThemedText>
      </View>

      <ThemedText testID={`organization-invitation-status-${invitation.status}`}>{STATUS_TEXT[invitation.status]}</ThemedText>

      {invitation.status === 'REGISTERED' && (
        <TouchableOpacity
          testID="organization-invitation-open-organization"
          onPress={() => router.replace(`/organizations/${invitation.organization_id}`)}
        >
          <ThemedText type="link">団体を開く</ThemedText>
        </TouchableOpacity>
      )}

      {toHome}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.three },
  title: { fontSize: 22, lineHeight: 28 },
  details: { borderWidth: 1, borderColor: '#e1dee6', borderRadius: 10, padding: Spacing.three, gap: Spacing.one },
});
