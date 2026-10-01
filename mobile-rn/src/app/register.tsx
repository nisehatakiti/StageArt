import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity } from 'react-native';

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
 * clarified that an ORDINARY self-registration must NOT be treated as
 * "the user is now logged into StageArt": AuthContext.registerWithEmail()
 * never persists a session for a freshly-registered, still-unverified
 * account (see its own docblock) - status stays 'unauthenticated', and
 * this screen navigates to registration-pending.tsx, never /home.
 *
 * StageArt 招待登録のメール確認省略ラウンド: this no longer holds for a
 * registration reached via a valid Production invitation link
 * (register.tsx?token=...) - the invitation link itself already proves
 * the address is reachable, so the Backend marks that EmailCredential
 * verified immediately and never sends a confirmation mail for it (see
 * RegisterWithEmailUseCase's own docblock). `handleSubmit()` below
 * forwards `invitationToken` only once the preview fetch has confirmed
 * it resolves to a real, usable invitation, and AuthContext's
 * registerWithEmail() establishes a real session right away for that
 * case (its own `probePersonGate()` call reflects the Backend's actual
 * verified status now, not a hardcoded assumption) - this screen routes
 * straight to /home or /set-name instead of registration-pending.tsx
 * when that happens. Ordinary self-registration (no token, or a token
 * that failed to preview) is completely unaffected.
 */
export default function RegisterScreen() {
  const { registerWithEmail } = useAuth();
  const router = useRouter();
  const { token: invitationToken } = useLocalSearchParams<{ token?: string }>();

  const [email, setEmail] = useState('');
  const [name, setName] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [invitationProductionName, setInvitationProductionName] = useState<string | null>(null);

  /**
   * StageArt Productionメンバー追加(氏名＋メールアドレス統一)ラウンド §3/§23:
   * arriving via an invitation link (register.tsx?token=...) pre-fills
   * the invited email AND name as a courtesy.
   *
   * StageArt 招待登録のメール確認省略ラウンド §7: this fetch succeeding is
   * also what this screen treats as "this token is confirmed usable" -
   * `invitationProductionName` being set gates both forwarding
   * `invitationToken` to registerWithEmail() on submit (see
   * handleSubmit() below) and fixing the email field so it cannot be
   * changed to something other than the invited address (the Backend
   * now uses the invitation's own recorded email unconditionally for
   * this path regardless, but the field is fixed here too so the UI
   * never implies an edit that would silently be ignored). `name` stays
   * editable (§3: "本人が登録時に氏名を変更できることを妨げない") - it is
   * unrelated to which email ends up registered. If this fetch fails
   * (invalid/expired/cancelled/unknown token, or no token at all), this
   * silently falls back to an ordinary, fully-editable self-registration
   * - never a half-invited submission with a token the Backend would
   * reject anyway.
   *
   * IMPORTANT (disclosed limitation, not silently decided - unchanged by
   * this round): `name` here is still UI-only. POST /auth/email/register
   * (RegisterWithEmailUseCase) has no name parameter - Person.familyName/
   * givenName are only ever set later via the existing, separate
   * UpdatePersonNameUseCase (set-name.tsx). Wiring this pre-filled name
   * through to the real Person would mean extending either
   * RegisterWithEmailUseCase or set-name.tsx, both outside this round's
   * authorized change scope - see this round's final report for this
   * open item.
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
        setName(preview.name);
        setInvitationProductionName(preview.production_name);
      })
      .catch(() => {
        // An invalid/expired/consumed token must never block normal
        // registration - silently fall back to the blank, editable
        // email/name fields a direct /register visit already has.
      });

    return () => {
      cancelled = true;
    };
  }, [invitationToken]);

  /**
   * StageArt 招待登録のメール確認省略ラウンド: `invitationToken` is only
   * ever forwarded once `invitationProductionName` is set - i.e. once
   * the preview fetch above has actually confirmed this token resolves
   * to a usable ParticipantInvitation. If that preview failed (expired/
   * cancelled/unknown token, or no token at all), this degrades to an
   * ordinary self-registration exactly as before - never a half-invited
   * submission with a token the Backend would reject anyway.
   */
  async function handleSubmit() {
    setSubmitting(true);
    setErrorMessage(null);

    const trimmedEmail = email.trim();
    const result = await registerWithEmail(
      trimmedEmail,
      password,
      invitationProductionName ? invitationToken : undefined
    );

    setSubmitting(false);
    if (!result.ok) {
      setErrorMessage(result.message);
      return;
    }

    if (!result.emailVerified) {
      router.replace(`/registration-pending?email=${encodeURIComponent(trimmedEmail)}`);
      return;
    }

    // The invitation link itself already proved this address is
    // reachable - registerWithEmail() has already established a real
    // session (see its own docblock), so there is no "確認メールを
    // お送りしました" step to show here at all.
    router.replace(result.hasName ? '/home' : '/set-name');
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
          「{invitationProductionName}」への招待です。このメールアドレスで登録すると、確認メールなしで自動的にメンバーへ追加されます。
        </ThemedText>
      )}

      {invitationProductionName && (
        <ThemedTextInput
          testID="register-invitation-name"
          placeholder="氏名"
          value={name}
          onChangeText={setName}
          style={authStyles.input}
        />
      )}

      <ThemedTextInput
        testID="register-email"
        placeholder="メールアドレス"
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        autoCorrect={false}
        keyboardType="email-address"
        editable={!invitationProductionName}
        style={[authStyles.input, invitationProductionName ? styles.fixedInput : null]}
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

const styles = StyleSheet.create({
  fixedInput: { opacity: 0.6 },
});
