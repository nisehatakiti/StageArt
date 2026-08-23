import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ApiClient } from '@/api/client';
import { getPendingSession, setPendingSession } from '@/auth/pendingVerificationAccess';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { requestEmailVerification } from '@/features/auth/api';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * A real-device correction to this Phase's original design: reaching
 * this screen no longer means the user has a real, persisted StageArt
 * session (see AuthContext.tsx's registerWithEmail/loginWithEmail
 * docblocks - an unverified account's session is never established at
 * all). Resend still calls the existing authenticated Backend Phase 2
 * endpoint (POST /user-accounts/email-credential/verify-request,
 * unchanged), but via a one-off ApiClient built from
 * pendingVerificationAccess.ts's transient, non-persisted Access Token
 * rather than the shared AuthContext session. "違うメールアドレスの場合"
 * has no dedicated edit-email API, so it just clears that transient
 * token and returns to the login screen - there is no session to sign
 * out of.
 *
 * A real-device follow-up: an explicit, always-visible "ログイン画面に
 * 戻る" link is offered separately from "メールアドレスが間違っている場合"
 * - a user can land here after successfully logging into an
 * already-registered-but-still-unverified account (see login.tsx), in
 * which case nothing about their email was wrong; they just need a
 * guaranteed way back to the login screen once they've verified (or to
 * try a different account) without that link's "wrong email" framing.
 */
export default function RegistrationPendingScreen() {
  const router = useRouter();
  const { email } = useLocalSearchParams<{ email?: string }>();

  const [resending, setResending] = useState(false);
  const [resendMessage, setResendMessage] = useState<string | null>(null);

  async function handleResend() {
    setResending(true);
    setResendMessage(null);
    try {
      const pending = getPendingSession();
      if (!pending) {
        setResendMessage('再送に失敗しました。お手数ですが、もう一度ログインをお試しください。');
        return;
      }
      const client = new ApiClient(() => pending.accessToken);
      await requestEmailVerification(client);
      setResendMessage('確認メールを再送しました。');
    } catch (error) {
      setResendMessage(getErrorMessage(error));
    } finally {
      setResending(false);
    }
  }

  function handleWrongEmail() {
    setPendingSession(null);
    router.replace('/login');
  }

  function handleBackToLogin() {
    setPendingSession(null);
    router.replace('/login');
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <ThemedView style={styles.container}>
        <ThemedText type="title" style={styles.title}>
          確認メールを送信しました
        </ThemedText>

        <ThemedText style={styles.description}>
          {email ? (
            <>
              <ThemedText style={styles.emailText} testID="registration-pending-email">
                {email}
              </ThemedText>
              {' 宛に確認メールをお送りしました。'}
            </>
          ) : (
            '登録いただいたメールアドレス宛に確認メールをお送りしました。'
          )}
        </ThemedText>

        <ThemedText type="small" themeColor="textSecondary" style={styles.description}>
          メール本文内のリンクをタップして、メールアドレスの確認を完了してください。確認が完了すると、ログイン画面からStageArtをご利用いただけます。
        </ThemedText>

        <TouchableOpacity
          testID="registration-pending-resend"
          onPress={handleResend}
          disabled={resending}
          style={[styles.button, resending && styles.buttonDisabled]}
        >
          {resending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>確認メールを再送する</ThemedText>}
        </TouchableOpacity>

        {resendMessage && (
          <ThemedText testID="registration-pending-resend-feedback" type="small" themeColor="textSecondary" style={styles.description}>
            {resendMessage}
          </ThemedText>
        )}

        <TouchableOpacity testID="registration-pending-back-to-login" onPress={handleBackToLogin}>
          <ThemedText type="linkPrimary" style={styles.linkCentered}>
            ログイン画面に戻る
          </ThemedText>
        </TouchableOpacity>

        <TouchableOpacity testID="registration-pending-wrong-email" onPress={handleWrongEmail}>
          <ThemedText type="link" style={styles.linkCentered}>
            メールアドレスが間違っている場合はこちら
          </ThemedText>
        </TouchableOpacity>
      </ThemedView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  container: { flex: 1, justifyContent: 'center', paddingHorizontal: Spacing.four, gap: Spacing.three },
  title: { fontSize: 24, lineHeight: 30, textAlign: 'center' },
  description: { textAlign: 'center' },
  emailText: { fontWeight: '700' },
  button: {
    backgroundColor: '#4a3f7a',
    borderRadius: 8,
    paddingVertical: Spacing.three,
    alignItems: 'center',
    marginTop: Spacing.two,
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  linkCentered: { textAlign: 'center' },
});
