import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, KeyboardAvoidingView, Platform, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ApiError } from '@/api/errors';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { resetPassword } from '@/features/auth/api';

/**
 * Step 2 of Backend Phase 2's password reset flow: POST
 * /auth/password/reset. The Backend's reset email currently sends the
 * raw token value in plain text, not a deep link (see the Backend Phase
 * 2 report's disclosed simplification - a deep-link landing page is a
 * future mobile-rn refinement, not built yet), so this screen accepts a
 * manually pasted/typed token rather than only reading one from a route
 * param. `token` is still read from an optional route param so a future
 * deep link can pre-fill it without any change to this screen.
 *
 * A successful reset does NOT log the user in automatically - Backend's
 * ResetPasswordUseCase returns no tokens (and, deliberately, revokes any
 * existing sessions for this account - see that Use Case's docblock) -
 * so this screen sends the user back to /login to sign in with the new
 * password, exactly like a normal first login.
 */
export default function ResetPasswordScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{ token?: string }>();

  const [token, setToken] = useState(params.token ?? '');
  const [newPassword, setNewPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  async function handleSubmit() {
    setSubmitting(true);
    setErrorMessage(null);

    try {
      await resetPassword(token.trim(), newPassword);
      router.replace('/login');
    } catch (error) {
      if (error instanceof ApiError && error.statusCode === 401) {
        setErrorMessage('このコードは無効か、有効期限が切れています。もう一度パスワード再設定をお試しください。');
      } else if (error instanceof ApiError && error.statusCode === 422) {
        setErrorMessage('パスワードは8文字以上で入力してください。');
      } else {
        setErrorMessage('パスワードの再設定に失敗しました。時間をおいて再度お試しください。');
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
        <ThemedView style={styles.container}>
          <ThemedText type="title" style={styles.brand}>
            パスワードの再設定
          </ThemedText>
          <ThemedText type="small" themeColor="textSecondary" style={styles.description}>
            メールに記載されたコードと、新しいパスワードを入力してください。
          </ThemedText>

          <ThemedTextInput
            testID="reset-password-token"
            placeholder="メールに記載されたコード"
            value={token}
            onChangeText={setToken}
            autoCapitalize="none"
            autoCorrect={false}
            style={styles.input}
          />
          {/* textContentType="oneTimeCode": deliberate, not an oversight -
              suppresses iOS's "Use Strong Password?" suggestion overlay,
              which was confirmed (register.tsx, reached the same way via
              Stack push) to fight this controlled TextInput's value on
              every keystroke when textContentType="newPassword" was used
              instead. See register.tsx's own comment for the full
              real-device evidence. */}
          <ThemedTextInput
            testID="reset-password-new-password"
            placeholder="新しいパスワード（8文字以上）"
            value={newPassword}
            onChangeText={setNewPassword}
            autoCapitalize="none"
            autoCorrect={false}
            secureTextEntry
            textContentType="oneTimeCode"
            style={styles.input}
          />

          {errorMessage && (
            <ThemedText testID="reset-password-error" themeColor="text" style={styles.error}>
              {errorMessage}
            </ThemedText>
          )}

          <TouchableOpacity
            testID="reset-password-submit"
            onPress={handleSubmit}
            disabled={submitting}
            style={[styles.button, submitting && styles.buttonDisabled]}
          >
            {submitting ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>再設定する</ThemedText>}
          </TouchableOpacity>

          <TouchableOpacity testID="reset-password-login-link" onPress={() => router.replace('/login')} disabled={submitting}>
            <ThemedText type="link" style={styles.linkCentered}>
              ログイン画面へ戻る
            </ThemedText>
          </TouchableOpacity>
        </ThemedView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  flex: { flex: 1 },
  container: { flex: 1, justifyContent: 'center', paddingHorizontal: Spacing.four, gap: Spacing.three },
  brand: { fontSize: 24, lineHeight: 30, textAlign: 'center' },
  description: { textAlign: 'center', marginBottom: Spacing.two },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
  },
  error: { color: '#a6483a' },
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
