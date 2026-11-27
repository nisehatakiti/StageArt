import { useLocalSearchParams, useRouter } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { PARTICIPANT_TYPE_LABEL } from '@/features/participantInvitation/labels';
import { useMyParticipantInvitation } from '@/features/participantInvitation/useParticipantInvitation';
import { getErrorMessage } from '@/utils/errorMessage';

const STATUS_TEXT = {
  REGISTERED: 'この公演の参加者として登録されています。',
  PENDING: '招待されたメールアドレスの確認が完了すると、この公演の参加者として登録されます。',
  EXPIRED: 'この招待は有効期限が切れています。公演の管理者に再送を依頼してください。',
  CANCELLED: 'この招待は取り消されました。',
  CLOSED: 'この招待は終了しています。',
} as const;

/**
 * The screen the Production invitation mail's link opens,
 * `/participant-invitations/{id}` (invitation policy,
 * docs/03-InvitationAndEnrollmentPolicy.md, 2026-11-27). It sits in the (app)
 * group, so a logged-out visitor logs in first and comes back here
 * (return_to). Opening it is not an acceptance - nobody accepts or rejects:
 * the person is registered when the invited address is verified (or at
 * invitation time for a verified account). The server shows the invitation
 * only to the account whose verified address is the invited one; anything
 * else (another account, a missing invitation) is the same notice.
 */
export default function ParticipantInvitationScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const invitationQuery = useMyParticipantInvitation(id);
  const invitation = invitationQuery.data;

  const toHome = (
    <TouchableOpacity testID="participant-invitation-home" onPress={() => router.replace('/home')}>
      <ThemedText type="link">ホームへ</ThemedText>
    </TouchableOpacity>
  );

  if (invitationQuery.isLoading) {
    return <ActivityIndicator testID="participant-invitation-loading" />;
  }

  if (invitationQuery.isError || !invitation) {
    return (
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText testID="participant-invitation-not-shown">
          この招待は表示できません。招待メールを受け取ったメールアドレスでログインしているか確認してください。
        </ThemedText>
        {invitationQuery.isError && (
          <ThemedText type="small" themeColor="textSecondary" testID="participant-invitation-load-error">
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
        公演への招待
      </ThemedText>

      <View style={styles.details} testID="participant-invitation-details">
        <ThemedText type="smallBold">{invitation.production_name}</ThemedText>
        <ThemedText type="small">役割: {PARTICIPANT_TYPE_LABEL[invitation.participant_type] ?? invitation.participant_type}</ThemedText>
      </View>

      <ThemedText testID={`participant-invitation-status-${invitation.status}`}>{STATUS_TEXT[invitation.status]}</ThemedText>

      {invitation.status === 'REGISTERED' && (
        <TouchableOpacity
          testID="participant-invitation-open-production"
          onPress={() => router.replace(`/production/${invitation.production_id}/overview`)}
        >
          <ThemedText type="link">公演を開く</ThemedText>
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
