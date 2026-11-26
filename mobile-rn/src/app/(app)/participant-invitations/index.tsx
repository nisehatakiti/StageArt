import { useRouter } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { PARTICIPANT_TYPE_LABEL, UNAVAILABLE_REASON_LABEL } from '@/features/participantInvitation/labels';
import { useMyParticipantInvitations } from '@/features/participantInvitation/useParticipantInvitation';
import { formatServerDateTime, getErrorMessage } from '@/utils/errorMessage';


/**
 * PR #7 R14-B (design doc §10 「招待一覧」, §7.2, §9.2): the invitation
 * mail's link target, `/participant-invitations`. It sits in the (app)
 * group, so a logged-out visitor is sent to login first (and back here
 * afterwards) - nothing about any invitation is shown before login.
 *
 * Lists GET /me/participant-invitations: invitations to the logged-in
 * Person's verified addresses only, with what the Backend says about them
 * (can_accept / can_reject / unavailable_reason) - nothing is derived
 * here. A row opens the invitation's own screen.
 */
export default function ParticipantInvitationsScreen() {
  const router = useRouter();
  const invitationsQuery = useMyParticipantInvitations();
  const invitations = invitationsQuery.data ?? [];

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <ThemedText type="title" style={styles.title}>
        招待
      </ThemedText>

      {invitationsQuery.isLoading && <ActivityIndicator testID="participant-invitations-loading" />}

      {invitationsQuery.isError && (
        <ThemedText testID="participant-invitations-error">{getErrorMessage(invitationsQuery.error)}</ThemedText>
      )}

      {!invitationsQuery.isLoading && !invitationsQuery.isError && invitations.length === 0 && (
        <ThemedText testID="participant-invitations-empty" themeColor="textSecondary" style={styles.empty}>
          招待が見つからない場合は、招待メールを受け取ったメールアドレスでログインしてください。登録したばかりの場合は、メールアドレスの確認を済ませてください。
        </ThemedText>
      )}

      {invitations.length > 0 && (
        <ThemedView style={styles.list} testID="participant-invitations-list">
          {invitations.map((invitation) => {
            const expiresAt = formatServerDateTime(invitation.expires_at);
            return (
              <TouchableOpacity
                key={invitation.id}
                testID={`participant-invitation-${invitation.id}`}
                style={styles.card}
                onPress={() => router.push(`/participant-invitations/${invitation.id}`)}
              >
                <ThemedText type="smallBold">{invitation.production_name}</ThemedText>
                {invitation.organization_name && (
                  <ThemedText type="small" themeColor="textSecondary">{invitation.organization_name}</ThemedText>
                )}
                <ThemedText type="small">
                  役割: {PARTICIPANT_TYPE_LABEL[invitation.participant_type] ?? invitation.participant_type}
                  {'　'}招待者: {invitation.invited_by_display_name}
                </ThemedText>
                {expiresAt && <ThemedText type="small" themeColor="textSecondary">有効期限: {expiresAt}</ThemedText>}
                {!invitation.can_accept && (
                  <ThemedText type="small" style={styles.unavailable} testID={`participant-invitation-unavailable-${invitation.id}`}>
                    {(invitation.unavailable_reason && UNAVAILABLE_REASON_LABEL[invitation.unavailable_reason]) || 'この招待は現在承諾できません'}
                  </ThemedText>
                )}
              </TouchableOpacity>
            );
          })}
        </ThemedView>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.three },
  title: { fontSize: 22, lineHeight: 28 },
  empty: { paddingTop: Spacing.two },
  list: { gap: Spacing.two },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.one,
  },
  unavailable: { color: '#a6483a' },
});
