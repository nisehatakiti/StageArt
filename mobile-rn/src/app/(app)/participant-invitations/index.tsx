import { Redirect } from 'expo-router';

/**
 * `/participant-invitations` - the link of the Production invitation mails
 * sent before the 2026-11-27 invitation policy. There is no invitation list
 * any more (nobody accepts or rejects; the mails now open
 * /participant-invitations/{id}), so it goes to Home.
 */
export default function ParticipantInvitationsScreen() {
  return <Redirect href="/home" />;
}
