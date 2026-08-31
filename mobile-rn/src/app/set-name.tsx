import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, KeyboardAvoidingView, Platform, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { updatePersonName } from '@/features/auth/api';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Authentication Phase 6: reached only once a real, persisted
 * session already exists (index.tsx's boot gate and login.tsx's
 * post-login routing both check GET /me's family_name/given_name and
 * land here when either is unset - see their own docblocks). Both Email
 * and Google-authenticated users are routed here identically; there is
 * no "Google skips this" branch.
 *
 * `family_name_hint`/`given_name_hint` route params (present only for a
 * Google login where Google's own ID Token happened to carry them - see
 * AuthenticationResult.php's docblock) pre-fill the form but are always
 * editable - saving is the only thing that actually confirms a name,
 * never the hint's mere presence.
 *
 * `return_to` (optional route param): defaults to '/home' (the
 * onboarding path - a brand-new user has nowhere else to go). Profile's
 * own "プロフィールを編集" link passes `return_to=/profile` so editing an
 * already-set name from Profile returns there instead of detouring
 * through Home.
 */
export default function SetNameScreen() {
  const { apiClient } = useAuth();
  const router = useRouter();
  const queryClient = useQueryClient();
  const params = useLocalSearchParams<{ family_name_hint?: string; given_name_hint?: string; return_to?: string }>();

  const [familyName, setFamilyName] = useState(params.family_name_hint ?? '');
  const [givenName, setGivenName] = useState(params.given_name_hint ?? '');
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  async function handleSubmit() {
    setSubmitting(true);
    setErrorMessage(null);

    try {
      await updatePersonName(apiClient, familyName.trim(), givenName.trim());
      await queryClient.invalidateQueries({ queryKey: ['me'] });
      router.replace((params.return_to ?? '/home') as Href);
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    } finally {
      setSubmitting(false);
    }
  }

  const canSubmit = familyName.trim().length > 0 && givenName.trim().length > 0;

  return (
    <SafeAreaView style={styles.safeArea}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
        <ThemedView style={styles.container}>
          <ThemedText type="title" style={styles.brand}>
            お名前を教えてください
          </ThemedText>
          <ThemedText type="small" themeColor="textSecondary" style={styles.description}>
            StageArtでの表示に使用します。あとから変更することもできます。
          </ThemedText>

          <ThemedView style={styles.row}>
            <ThemedView style={styles.field}>
              <ThemedText type="small" themeColor="textSecondary">
                姓
              </ThemedText>
              <ThemedTextInput
                testID="set-name-family-name"
                placeholder="舞台"
                value={familyName}
                onChangeText={setFamilyName}
                autoCorrect={false}
                style={styles.input}
              />
            </ThemedView>
            <ThemedView style={styles.field}>
              <ThemedText type="small" themeColor="textSecondary">
                名
              </ThemedText>
              <ThemedTextInput
                testID="set-name-given-name"
                placeholder="芸術"
                value={givenName}
                onChangeText={setGivenName}
                autoCorrect={false}
                style={styles.input}
              />
            </ThemedView>
          </ThemedView>

          {errorMessage && (
            <ThemedText testID="set-name-error" style={styles.error}>
              {errorMessage}
            </ThemedText>
          )}

          <TouchableOpacity
            testID="set-name-submit"
            onPress={handleSubmit}
            disabled={submitting || !canSubmit}
            style={[styles.button, (submitting || !canSubmit) && styles.buttonDisabled]}
          >
            {submitting ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>登録する</ThemedText>}
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
  row: { flexDirection: 'row', gap: Spacing.three },
  field: { flex: 1, gap: Spacing.one },
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
});
