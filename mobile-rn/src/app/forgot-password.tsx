import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, TouchableOpacity } from 'react-native';

import { AuthLayout } from '@/components/auth/AuthLayout';
import { authStyles } from '@/components/auth/authStyles';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { requestPasswordReset } from '@/features/auth/api';
import { NetworkError } from '@/api/errors';

/**
 * Step 1 of Backend Phase 2's password reset flow: POST
 * /auth/password/reset-request. Always shows the same success message
 * regardless of whether the email address is actually registered - the
 * Backend itself is anti-enumeration by design (always 200), and this
 * screen must not undermine that by branching UI on the response (this
 * Phase's explicit instruction: "常にアカウント存在を推測できないレスポンス
 * として扱う").
 */
export default function ForgotPasswordScreen() {
  const router = useRouter();

  const [email, setEmail] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  async function handleSubmit() {
    setSubmitting(true);
    setErrorMessage(null);

    try {
      await requestPasswordReset(email.trim());
      setSubmitted(true);
    } catch (error) {
      // Only a genuine connectivity failure is worth surfacing here - any
      // server response (even one implying "no such account", which the
      // Backend never actually sends) must still read as success.
      if (error instanceof NetworkError) {
        setErrorMessage('サーバーへ接続できませんでした。通信環境を確認してください。');
      } else {
        setSubmitted(true);
      }
    } finally {
      setSubmitting(false);
    }
  }

  if (submitted) {
    return (
      <AuthLayout>
        <ThemedText type="title" style={authStyles.title}>
          確認メールを送信しました
        </ThemedText>
        <ThemedText testID="forgot-password-success" type="default" style={authStyles.description}>
          ご入力いただいたメールアドレス宛にパスワード再設定用のご案内をお送りしました。メール内のコードを次の画面で入力してください。
        </ThemedText>

        <TouchableOpacity testID="forgot-password-to-reset-link" onPress={() => router.push('/reset-password')} style={authStyles.button}>
          <ThemedText style={authStyles.buttonText}>パスワードを再設定する</ThemedText>
        </TouchableOpacity>

        <TouchableOpacity testID="forgot-password-login-link" onPress={() => router.replace('/login')}>
          <ThemedText type="link" style={authStyles.linkCentered}>
            ← ログイン画面へ戻る
          </ThemedText>
        </TouchableOpacity>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout>
      <ThemedText type="title" style={authStyles.title}>
        パスワードを忘れた場合
      </ThemedText>
      <ThemedText type="small" style={authStyles.description}>
        登録済みのメールアドレスを入力してください。パスワード再設定用のご案内をお送りします。
      </ThemedText>

      <ThemedTextInput
        testID="forgot-password-email"
        placeholder="メールアドレス"
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        autoCorrect={false}
        keyboardType="email-address"
        style={authStyles.input}
      />

      {errorMessage && (
        <ThemedText testID="forgot-password-error" style={authStyles.error}>
          {errorMessage}
        </ThemedText>
      )}

      <TouchableOpacity
        testID="forgot-password-submit"
        onPress={handleSubmit}
        disabled={submitting}
        style={[authStyles.button, submitting && authStyles.buttonDisabled]}
      >
        {submitting ? <ActivityIndicator color="#fff" /> : <ThemedText style={authStyles.buttonText}>送信する</ThemedText>}
      </TouchableOpacity>

      <TouchableOpacity testID="forgot-password-login-link" onPress={() => router.back()} disabled={submitting}>
        <ThemedText type="link" style={authStyles.linkCentered}>
          ← ログイン画面へ戻る
        </ThemedText>
      </TouchableOpacity>
    </AuthLayout>
  );
}
