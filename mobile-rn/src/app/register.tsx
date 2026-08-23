import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, KeyboardAvoidingView, Platform, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useAuth } from '@/auth/AuthContext';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';

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

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

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
    <SafeAreaView style={styles.safeArea}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
        <ThemedView style={styles.container}>
          <ThemedText type="title" style={styles.brand}>
            アカウントを新規登録
          </ThemedText>
          <ThemedText type="small" themeColor="textSecondary" style={styles.description}>
            メールアドレスとパスワード（8文字以上）を入力してください。
          </ThemedText>

          <ThemedTextInput
            testID="register-email"
            placeholder="メールアドレス"
            value={email}
            onChangeText={setEmail}
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="email-address"
            style={styles.input}
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
            style={styles.input}
          />

          {errorMessage && (
            <ThemedText testID="register-error" themeColor="text" style={styles.error}>
              {errorMessage}
            </ThemedText>
          )}

          <TouchableOpacity
            testID="register-submit"
            onPress={handleSubmit}
            disabled={submitting}
            style={[styles.button, submitting && styles.buttonDisabled]}
          >
            {submitting ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>登録する</ThemedText>}
          </TouchableOpacity>

          <TouchableOpacity testID="register-login-link" onPress={() => router.back()} disabled={submitting}>
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
  brand: { fontSize: 28, lineHeight: 34, textAlign: 'center' },
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
