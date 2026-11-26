import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import {
  useMyParticipantInvitations,
  useRespondToParticipantInvitation,
} from '@/features/participantInvitation/useParticipantInvitation';
import { PARTICIPANT_TYPE_LABEL, UNAVAILABLE_REASON_LABEL } from '@/features/participantInvitation/labels';
import { formatServerDateTime, getErrorMessage } from '@/utils/errorMessage';


type Outcome = 'accepted' | 'rejected';

/**
 * PR #7 R14-B (design doc §10 「招待確認」, §7.2 N5, D7, U7): one invitation
 * of the logged-in Person, opened from the invitation list.
 *
 * - Shown from GET /me/participant-invitations (refetched when the list is
 *   not in memory, e.g. after a reload) - there is no API for a single
 *   invitation.
 * - An id that is not in that list (expired, answered, not theirs, or
 *   unknown - not told apart) shows no details, only the common notice and
 *   the way back to the list.
 * - The result of 「参加する」 / 「参加しない」 is what the API answers (200 /
 *   403 / 404 / 409 / 410 / 429 ...), never the list's can_accept, which
 *   only decides which buttons are shown: can_accept=false shows
 *   「この招待は現在承諾できません」 and only 「参加しない」.
 */
export default function ParticipantInvitationScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const invitationsQuery = useMyParticipantInvitations();
  const { accept, reject } = useRespondToParticipantInvitation();
  const [outcome, setOutcome] = useState<Outcome | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const invitation = (invitationsQuery.data ?? []).find((candidate) => candidate.id === id);
  const busy = accept.isPending || reject.isPending;

  async function respond(kind: Outcome) {
    if (!id) return;
    setErrorMessage(null);
    try {
      if (kind === 'accepted') {
        await accept.mutateAsync(id);
      } else {
        await reject.mutateAsync(id);
      }
      setOutcome(kind);
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  const backToList = (
    <TouchableOpacity testID="participant-invitation-back" onPress={() => router.replace('/participant-invitations')}>
      <ThemedText type="link">招待一覧へ戻る</ThemedText>
    </TouchableOpacity>
  );

  // The answer has been recorded: the invitation leaves the list, so this is shown from local state.
  if (outcome !== null) {
    return (
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText testID={`participant-invitation-${outcome}`}>
          {outcome === 'accepted' ? '参加しました。公演のメンバーになりました。' : '招待を辞退しました。'}
        </ThemedText>
        {outcome === 'accepted' && (
          <TouchableOpacity testID="participant-invitation-to-participations" onPress={() => router.replace('/participating-productions')}>
            <ThemedText type="link">参加している公演・活動を開く</ThemedText>
          </TouchableOpacity>
        )}
        {backToList}
      </ScrollView>
    );
  }

  if (invitationsQuery.isLoading) {
    return <ActivityIndicator testID="participant-invitation-loading" />;
  }

  if (invitationsQuery.isError) {
    return (
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText testID="participant-invitation-load-error">{getErrorMessage(invitationsQuery.error)}</ThemedText>
        {backToList}
      </ScrollView>
    );
  }

  if (!invitation) {
    return (
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText testID="participant-invitation-not-shown">この招待は表示できません。招待一覧から確認してください。</ThemedText>
        {backToList}
      </ScrollView>
    );
  }

  const expiresAt = formatServerDateTime(invitation.expires_at);

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <ThemedText type="title" style={styles.title}>
        公演への招待
      </ThemedText>

      <View style={styles.details} testID="participant-invitation-details">
        <ThemedText type="smallBold">{invitation.production_name}</ThemedText>
        {invitation.organization_name && <ThemedText type="small">団体: {invitation.organization_name}</ThemedText>}
        <ThemedText type="small">役割: {PARTICIPANT_TYPE_LABEL[invitation.participant_type] ?? invitation.participant_type}</ThemedText>
        <ThemedText type="small">招待者: {invitation.invited_by_display_name}</ThemedText>
        {expiresAt && <ThemedText type="small">有効期限: {expiresAt}</ThemedText>}
      </View>

      {invitation.already_participant && (
        <ThemedText type="small" testID="participant-invitation-already-participant">
          あなたは既にこの公演のメンバーです。承諾すると、参加への同意が記録されます。
        </ThemedText>
      )}

      {invitation.can_accept ? (
        <ThemedText type="small" themeColor="textSecondary" testID="participant-invitation-sharing">
          参加すると、あなたの氏名とメールアドレスがこの公演の管理者に共有されます。
        </ThemedText>
      ) : (
        <ThemedText type="small" style={styles.unavailable} testID="participant-invitation-cannot-accept">
          この招待は現在承諾できません
          {invitation.unavailable_reason && UNAVAILABLE_REASON_LABEL[invitation.unavailable_reason]
            ? `（${UNAVAILABLE_REASON_LABEL[invitation.unavailable_reason]}）`
            : ''}
        </ThemedText>
      )}

      {errorMessage && (
        <ThemedText testID="participant-invitation-error" style={styles.error}>
          {errorMessage}
        </ThemedText>
      )}

      <View style={styles.buttons}>
        {invitation.can_accept && (
          <TouchableOpacity
            testID="participant-invitation-accept"
            onPress={() => respond('accepted')}
            disabled={busy}
            style={[styles.acceptButton, busy && styles.disabled]}
          >
            {accept.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.acceptText}>参加する</ThemedText>}
          </TouchableOpacity>
        )}
        {invitation.can_reject && (
          <TouchableOpacity
            testID="participant-invitation-reject"
            onPress={() => respond('rejected')}
            disabled={busy}
            style={[styles.rejectButton, busy && styles.disabled]}
          >
            {reject.isPending ? <ActivityIndicator /> : <ThemedText style={styles.rejectText}>参加しない</ThemedText>}
          </TouchableOpacity>
        )}
      </View>

      {backToList}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.three },
  title: { fontSize: 22, lineHeight: 28 },
  details: { borderWidth: 1, borderColor: '#e1dee6', borderRadius: 10, padding: Spacing.three, gap: Spacing.one },
  unavailable: { color: '#a6483a' },
  error: { color: '#a6483a' },
  buttons: { flexDirection: 'row', gap: Spacing.two },
  acceptButton: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
  },
  acceptText: { color: '#fff', fontWeight: '600' },
  rejectButton: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
  },
  rejectText: { color: BrandColors.warmAmber, fontWeight: '600' },
  disabled: { opacity: 0.6 },
});
