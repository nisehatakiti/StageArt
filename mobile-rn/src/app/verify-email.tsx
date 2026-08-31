import * as Linking from 'expo-linking';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Platform, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useAuth } from '@/auth/AuthContext';
import { verifyEmail } from '@/features/auth/api';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { getErrorMessage } from '@/utils/errorMessage';

type VerifyState = 'idle' | 'verifying' | 'success' | 'error';

/**
 * Deep link landing screen for the confirmation email's
 * `stageart://verify-email?token=...` link (WordPressAuthMailer.php's
 * sendEmailVerificationEmail() - see its docblock for the Custom URL
 * Scheme, not Universal Link, caveat: this screen only opens if the app
 * is already installed). Calls the pre-existing POST /auth/email/verify
 * (Backend Phase 2) unchanged - no new Backend endpoint was added for
 * this.
 *
 * A manual token field is offered as a fallback for the case the link
 * itself can't be opened (mail client doesn't render the custom-scheme
 * link as tappable, or the user is reading the email on a different
 * device than the one with the app installed) - the email body itself
 * no longer prints the raw token as visible text (a real-device
 * correction: it must only ever live inside the link's URL), so this
 * fallback is a defensive affordance rather than something the email
 * routinely points a user toward.
 *
 * Verifying itself only sets EmailCredential.emailVerifiedAt on the
 * Backend (via the unchanged POST /auth/email/verify) - it does not, by
 * itself, establish a StageArt session on whatever device/browser this
 * screen is running in. This is the STAGEART v1.5 HTTPS Web
 * confirmation page: reached both natively (stageart://verify-email,
 * kept working alongside the new HTTPS link - see
 * WordPressAuthMailer.php's docblock) and via a browser, on the exact
 * same device that registered or on a completely different one - the
 * whole point of moving off a Custom-URL-Scheme-only email link.
 *
 * StageArt Authentication Phase 6: after a successful verify, this
 * screen tries `completeEmailVerificationFromPending()` - if this is
 * the SAME app session that registered/attempted-login (only possible
 * natively; a Web page's JS process can never have that in-memory
 * state, and neither can a genuinely separate device), it continues
 * straight through to set-name/Home with no extra "please log in"
 * step. Otherwise the success screen shows: on native, "ログイン画面へ";
 * on Web, "StageArtアプリを開く" (via the stageart:// Custom URL Scheme,
 * bare - no token - the Web page already completed verification itself,
 * so there is nothing left for a token to do if the native app also
 * received one; opening the app just lets its own boot gate pick up
 * from GET /me's now-true email_verified). Either way, "Email確認の成功"
 * itself never depends on the app being opened at all.
 */
export default function VerifyEmailScreen() {
  const router = useRouter();
  const { completeEmailVerificationFromPending } = useAuth();
  const { token: tokenParam } = useLocalSearchParams<{ token?: string }>();

  const [manualToken, setManualToken] = useState('');
  // Lazily seeded from tokenParam's presence so the manual-entry form
  // never has a chance to flash on first render when a deep-link token
  // is already present (the effect below still owns actually kicking
  // off verification) - 'idle' is reached for real either when no
  // tokenParam was ever present, or via the retry button after an error.
  const [state, setState] = useState<VerifyState>(() => (tokenParam ? 'verifying' : 'idle'));
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  async function runVerification(token: string) {
    setState('verifying');
    setErrorMessage(null);
    try {
      await verifyEmail(token);

      const outcome = await completeEmailVerificationFromPending();
      if (outcome.hasSession) {
        router.replace(outcome.hasName ? '/home' : '/set-name');
        return;
      }

      setState('success');
    } catch (error) {
      setState('error');
      setErrorMessage(getErrorMessage(error));
    }
  }

  useEffect(() => {
    if (tokenParam) {
      // runVerification's first line is a synchronous setState('verifying')
      // before its first await, which react-hooks/set-state-in-effect
      // flags on principle - but this effect's whole purpose is to kick
      // off exactly one, deliberate state-machine transition the instant
      // a token deep-link param is present (not a loop, not synchronizing
      // with an external system's ongoing changes), so there is nothing
      // to defer this into instead.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      runVerification(tokenParam);
    }
    // runVerification is intentionally omitted: it is redefined every
    // render (not memoized) but this effect must still only re-run when
    // tokenParam itself changes, not on every render - the same
    // one-shot-on-param-change intent as the disable above.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tokenParam]);

  return (
    <SafeAreaView style={styles.safeArea}>
      <ThemedView style={styles.container}>
        <ThemedText type="title" style={styles.title}>
          メールアドレスの確認
        </ThemedText>

        {state === 'idle' && (
          <>
            <ThemedText type="small" themeColor="textSecondary" style={styles.description}>
              確認メール内のリンクからこの画面が開けなかった場合は、メールに記載されているトークンを入力してください。
            </ThemedText>
            <ThemedTextInput
              testID="verify-email-token-input"
              placeholder="トークン"
              value={manualToken}
              onChangeText={setManualToken}
              autoCapitalize="none"
              autoCorrect={false}
              style={styles.input}
            />
            <TouchableOpacity
              testID="verify-email-submit"
              onPress={() => runVerification(manualToken.trim())}
              disabled={manualToken.trim().length === 0}
              style={[styles.button, manualToken.trim().length === 0 && styles.buttonDisabled]}
            >
              <ThemedText style={styles.buttonText}>確認する</ThemedText>
            </TouchableOpacity>
          </>
        )}

        {state === 'verifying' && (
          <ThemedView style={styles.centered}>
            <ActivityIndicator testID="verify-email-loading" />
          </ThemedView>
        )}

        {state === 'success' && (
          <>
            <ThemedText testID="verify-email-success" style={styles.description}>
              メールアドレスの確認が完了しました。
            </ThemedText>
            {Platform.OS === 'web' ? (
              <>
                <ThemedText type="small" themeColor="textSecondary" style={styles.description}>
                  StageArt Webからログインしてください。StageArtアプリをお持ちの場合は、下のボタンからアプリを開くこともできます。
                </ThemedText>
                <TouchableOpacity testID="verify-email-continue" onPress={() => router.replace('/login')} style={styles.button}>
                  <ThemedText style={styles.buttonText}>ログイン画面へ</ThemedText>
                </TouchableOpacity>
                <TouchableOpacity
                  testID="verify-email-open-app"
                  onPress={() => {
                    Linking.openURL('stageart://');
                  }}
                  style={styles.buttonSecondary}
                >
                  <ThemedText style={styles.buttonSecondaryText}>StageArtアプリを開く</ThemedText>
                </TouchableOpacity>
              </>
            ) : (
              <>
                <ThemedText type="small" themeColor="textSecondary" style={styles.description}>
                  ログイン画面からStageArtにログインしてください。
                </ThemedText>
                <TouchableOpacity testID="verify-email-continue" onPress={() => router.replace('/login')} style={styles.button}>
                  <ThemedText style={styles.buttonText}>ログイン画面へ</ThemedText>
                </TouchableOpacity>
              </>
            )}
          </>
        )}

        {state === 'error' && (
          <>
            <ThemedText testID="verify-email-error" style={styles.description}>
              {errorMessage ?? '確認に失敗しました。リンクの有効期限が切れている可能性があります。'}
            </ThemedText>
            <TouchableOpacity
              testID="verify-email-retry"
              onPress={() => {
                setState('idle');
                setErrorMessage(null);
              }}
              style={styles.button}
            >
              <ThemedText style={styles.buttonText}>もう一度入力する</ThemedText>
            </TouchableOpacity>
          </>
        )}
      </ThemedView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  container: { flex: 1, justifyContent: 'center', paddingHorizontal: Spacing.four, gap: Spacing.three },
  title: { fontSize: 24, lineHeight: 30, textAlign: 'center' },
  description: { textAlign: 'center' },
  centered: { alignItems: 'center', justifyContent: 'center', padding: Spacing.four },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
  },
  button: {
    backgroundColor: '#4a3f7a',
    borderRadius: 8,
    paddingVertical: Spacing.three,
    alignItems: 'center',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  buttonSecondary: {
    borderWidth: 1,
    borderColor: '#4a3f7a',
    borderRadius: 8,
    paddingVertical: Spacing.three,
    alignItems: 'center',
    marginTop: Spacing.two,
  },
  buttonSecondaryText: { color: '#4a3f7a', fontWeight: '600' },
});
