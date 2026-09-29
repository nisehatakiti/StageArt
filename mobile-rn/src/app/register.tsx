import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, TouchableOpacity } from 'react-native';

import { useAuth } from '@/auth/AuthContext';
import { AuthLayout } from '@/components/auth/AuthLayout';
import { authStyles } from '@/components/auth/authStyles';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { fetchParticipantInvitationPreview } from '@/features/participantInvitation/api';

/**
 * Email+Password new StageArt Account registration (Backend Phase 2's
 * POST /auth/email/register). The Backend still returns a full
 * Access/Refresh Token pair on success (matching Google's own new-user
 * path, unchanged Backend contract) - but a real-device correction
 * clarified that this must NOT be treated as "the user is now logged
 * into StageArt": AuthContext.registerWithEmail() deliberately never
 * persists a session for a freshly-registered (always-unverified)
 * account (see its own docblock) - status stays 'unauthenticated', and
 * closing/reopening the app lands back on the login screen, not here
 * again. This screen's own job is unchanged: always navigate to
 * registration-pending.tsx on success, never to /home.
 */
export default function RegisterScreen() {
  const { registerWithEmail } = useAuth();
  const router = useRouter();
  const { token: invitationToken } = useLocalSearchParams<{ token?: string }>();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [invitationProductionName, setInvitationProductionName] = useState<string | null>(null);

  /**
   * StageArt メール招待によるProductionParticipant追加機能 §23: arriving
   * via an invitation link (register.tsx?token=...) pre-fills the
   * invited email address as a courtesy - the actual auto-linking on
   * the Backend matches by the email the invitee actually registers
   * with (RegisterWithEmailUseCase -> ResolveParticipantInvitationUseCase),
   * never by this token, so this fetch is display-only and never
   * blocks or alters the normal registration flow below. The email
   * field deliberately stays editable (matching every other field on
   * this screen) - if changed, §14's "different email never auto-
   * links" rule simply means this particular invitation will not
   * resolve, which is the same, already-decided behavior as a Google
   * sign-up using a different email. A note next to the field makes
   * that consequence visible rather than silent.
   */
  useEffect(() => {
    if (!invitationToken) {
      return;
    }

    let cancelled = false;

    fetchParticipantInvitationPreview(invitationToken)
      .then((preview) => {
        if (cancelled) return;
        setEmail(preview.email);
        setInvitationProductionName(preview.production_name);
      })
      .catch(() => {
        // An invalid/expired/consumed token must never block normal
        // registration - silently fall back to the blank, editable
        // email field a direct /register visit already has.
      });

    return () => {
      cancelled = true;
    };
  }, [invitationToken]);

  async function handleSubmit() {
    setSubmitting(true);
    setErrorMessage(null);

    const trimmedEmail = email.trim();
    const result = await registerWithEmail(trimmedEmail, password);

    setSubmitting(false);
    if (!result.ok) {
      setErrorMessage(result.message);
      return;
    }

    router.replace(`/registration-pending?email=${encodeURIComponent(trimmedEmail)}`);
  }

  return (
    <AuthLayout>
      <ThemedText type="title" style={authStyles.title}>
        アカウントを新規登録
      </ThemedText>
      <ThemedText type="small" style={authStyles.description}>
        StageArtをはじめるためのアカウントを作成します。メールアドレスとパスワード（8文字以上）を入力してください。
      </ThemedText>

      {invitationProductionName && (
        <ThemedText testID="register-invitation-notice" type="small" style={authStyles.description}>
          「{invitationProductionName}」への招待です。招待されたメールアドレスで登録すると、自動的にメンバーへ追加されます。異なるメールアドレスで登録した場合、自動追加は行われません。
        </ThemedText>
      )}

      <ThemedTextInput
        testID="register-email"
        placeholder="メールアドレス"
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        autoCorrect={false}
        keyboardType="email-address"
        style={authStyles.input}
      />
          {/*
           * textContentType="oneTimeCode" is deliberate, not an oversight
           * (it was "newPassword" before). Confirmed via a real iPad
           * screenshot: with textContentType="newPassword" on a screen
           * freshly pushed via Stack navigation, iOS shows its native
           * "強力なパスワードを使用しますか？" (Use Strong Password?)
           * suggestion overlay, and while it is showing, onChangeText
           * stops receiving the accumulated string - each call reports
           * only the single most recently typed character ("P" then "a"
           * then "s", never "Pa"/"Pas") - i.e. iOS's own AutoFill UI was
           * fighting this controlled TextInput's value, not a bug in
           * this component's own state/render logic.
           * textContentType="oneTimeCode" is a well-documented,
           * deliberate workaround that suppresses that specific
           * suggestion UI while leaving secureTextEntry's masking
           * behavior untouched.
           */}
      <ThemedTextInput
        testID="register-password"
        placeholder="パスワード（8文字以上）"
        value={password}
        onChangeText={setPassword}
        autoCapitalize="none"
        autoCorrect={false}
        secureTextEntry
        textContentType="oneTimeCode"
        style={authStyles.input}
      />

      {errorMessage && (
        <ThemedText testID="register-error" style={authStyles.error}>
          {errorMessage}
        </ThemedText>
      )}

      <TouchableOpacity
        testID="register-submit"
        onPress={handleSubmit}
        disabled={submitting}
        style={[authStyles.button, submitting && authStyles.buttonDisabled]}
      >
        {submitting ? <ActivityIndicator color="#fff" /> : <ThemedText style={authStyles.buttonText}>登録する</ThemedText>}
      </TouchableOpacity>

      <TouchableOpacity testID="register-login-link" onPress={() => router.back()} disabled={submitting}>
        <ThemedText type="link" style={authStyles.linkCentered}>
          ← ログイン画面へ戻る
        </ThemedText>
      </TouchableOpacity>
    </AuthLayout>
  );
}
