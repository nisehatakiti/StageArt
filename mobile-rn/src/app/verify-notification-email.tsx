import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity } from 'react-native';

import { verifyNotificationEmailChange } from '@/features/notificationEmail/api';
import { AuthLayout } from '@/components/auth/AuthLayout';
import { authStyles } from '@/components/auth/authStyles';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { getErrorMessage } from '@/utils/errorMessage';

type VerifyState = 'idle' | 'verifying' | 'success' | 'error';

/**
 * 通知用Email確認・変更機能 §17/§21: deep link / Web landing screen for
 * WordPressAuthMailer.php's sendNotificationEmailChangeVerificationEmail()
 * link (`/verify-notification-email?token=...`) - deliberately its own
 * route, not a reuse of verify-email.tsx, since a stale link from the
 * wrong flow must never land on the wrong verification screen (see that
 * Backend method's own docblock). Unlike verify-email.tsx, this never
 * establishes or touches a StageArt session - POST /notification-email/
 * verify only ever changes NotificationEmail, so there is no
 * "complete pending registration" step to run afterward; a successful
 * verification simply routes to /account, whose own route guard handles
 * both the already-logged-in case (shows the new notification email
 * immediately) and the logged-out case (redirects to /login) without any
 * extra logic here.
 */
export default function VerifyNotificationEmailScreen() {
  const router = useRouter();
  const { token: tokenParam } = useLocalSearchParams<{ token?: string }>();

  const [manualToken, setManualToken] = useState('');
  const [state, setState] = useState<VerifyState>(() => (tokenParam ? 'verifying' : 'idle'));
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  async function runVerification(token: string) {
    setState('verifying');
    setErrorMessage(null);
    try {
      await verifyNotificationEmailChange(token);
      setState('success');
    } catch (error) {
      setState('error');
      setErrorMessage(getErrorMessage(error));
    }
  }

  useEffect(() => {
    if (tokenParam) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      runVerification(tokenParam);
    }
  }, [tokenParam]);

  return (
    <AuthLayout>
      <ThemedText type="title" style={authStyles.title}>
        通知先メールアドレスの確認
      </ThemedText>

      {state === 'idle' && (
        <>
          <ThemedText type="small" style={authStyles.description}>
            確認メール内のリンクからこの画面が開けなかった場合は、メールに記載されているトークンを入力してください。
          </ThemedText>
          <ThemedTextInput
            testID="verify-notification-email-token-input"
            placeholder="トークン"
            value={manualToken}
            onChangeText={setManualToken}
            autoCapitalize="none"
            autoCorrect={false}
            style={authStyles.input}
          />
          <TouchableOpacity
            testID="verify-notification-email-submit"
            onPress={() => runVerification(manualToken.trim())}
            disabled={manualToken.trim().length === 0}
            style={[authStyles.button, manualToken.trim().length === 0 && authStyles.buttonDisabled]}
          >
            <ThemedText style={authStyles.buttonText}>確認する</ThemedText>
          </TouchableOpacity>
        </>
      )}

      {state === 'verifying' && (
        <ThemedView style={styles.centered}>
          <ActivityIndicator testID="verify-notification-email-loading" />
        </ThemedView>
      )}

      {state === 'success' && (
        <>
          <ThemedText testID="verify-notification-email-success" style={authStyles.description}>
            通知先メールアドレスの確認が完了しました。以後、このメールアドレスにStageArtからのお知らせが送信されます。
          </ThemedText>
          <TouchableOpacity testID="verify-notification-email-continue" onPress={() => router.replace('/account')} style={authStyles.button}>
            <ThemedText style={authStyles.buttonText}>アカウント画面へ</ThemedText>
          </TouchableOpacity>
        </>
      )}

      {state === 'error' && (
        <>
          <ThemedText testID="verify-notification-email-error" style={authStyles.description}>
            {errorMessage ?? '確認に失敗しました。リンクの有効期限が切れている可能性があります。'}
          </ThemedText>
          <TouchableOpacity
            testID="verify-notification-email-retry"
            onPress={() => {
              setState('idle');
              setErrorMessage(null);
            }}
            style={authStyles.button}
          >
            <ThemedText style={authStyles.buttonText}>もう一度入力する</ThemedText>
          </TouchableOpacity>
        </>
      )}
    </AuthLayout>
  );
}

const styles = StyleSheet.create({
  centered: { alignItems: 'center', justifyContent: 'center', padding: Spacing.four, backgroundColor: 'transparent' },
});
