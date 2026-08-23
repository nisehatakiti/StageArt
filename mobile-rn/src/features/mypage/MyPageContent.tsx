import { useState } from 'react';
import { ActivityIndicator, Alert, ScrollView, StyleSheet, Switch, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ApiError } from '@/api/errors';
import { GoogleSignInCancelledError } from '@/auth/googleSignIn';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { useLogout } from '@/features/mypage/useLogout';
import { useAddEmailCredential, useChangePassword, useLinkGoogleAccount, useRequestEmailVerification } from '@/features/mypage/useAccountLinking';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import { usePushPreference, useUpdatePushPreference } from '@/features/pushPreference/usePushPreference';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * §27/§28: only information the Backend actually exposes today -
 * GET /me returns { id, word_press_user_id, email_verified }, no
 * display-name API exists, so this screen shows the Person ID rather
 * than inventing a name. Profile (Biography/Image/etc.) is a separate,
 * unimplemented Backend Domain (PersonPublicProfilePolicy.md) - no
 * editor is built for it here.
 *
 * Extracted from what was originally production/[id]/mypage.tsx: this
 * content never actually depended on a Production ID (it is entirely
 * Person-scoped), so the Blueprint v1.5 alignment phase pulled it out
 * into a shared component - both the pre-existing Production-Tabs
 * route and the new top-level /profile route (reachable from Person
 * Home's "プロフィール" entry point, which has no Production context)
 * render this same content.
 */
export function MyPageContent() {
  const currentPersonQuery = useCurrentPerson();
  const pushPreferenceQuery = usePushPreference();
  const updatePushPreference = useUpdatePushPreference();
  const logout = useLogout();
  const [loggingOut, setLoggingOut] = useState(false);

  const handleLogout = () => {
    Alert.alert('ログアウト', 'ログアウトしますか？', [
      { text: 'キャンセル', style: 'cancel' },
      {
        text: 'ログアウト',
        style: 'destructive',
        onPress: async () => {
          setLoggingOut(true);
          await logout();
        },
      },
    ]);
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <ScrollView contentContainerStyle={styles.content}>
        <ThemedText type="title" style={styles.title}>
          マイページ
        </ThemedText>

        <ThemedView style={styles.card}>
          <ThemedText type="subtitle" style={styles.cardTitle}>
            アカウント
          </ThemedText>

          {currentPersonQuery.isLoading && <ActivityIndicator testID="mypage-identity-loading" />}

          {currentPersonQuery.isError && (
            <ThemedText testID="mypage-identity-error">{getErrorMessage(currentPersonQuery.error)}</ThemedText>
          )}

          {currentPersonQuery.data && (
            <ThemedView style={styles.row}>
              <ThemedText type="default">Person ID</ThemedText>
              <ThemedText type="small" themeColor="textSecondary" testID="mypage-person-id">
                {currentPersonQuery.data.id}
              </ThemedText>
            </ThemedView>
          )}
        </ThemedView>

        <AccountSecurityCard />

        <ThemedView style={styles.card}>
          <ThemedText type="subtitle" style={styles.cardTitle}>
            通知設定
          </ThemedText>

          {pushPreferenceQuery.isLoading && <ActivityIndicator testID="push-preference-loading" />}

          {pushPreferenceQuery.isError && (
            <ThemedText testID="push-preference-error">{getErrorMessage(pushPreferenceQuery.error)}</ThemedText>
          )}

          {pushPreferenceQuery.data && (
            <ThemedView style={styles.row}>
              <ThemedText type="default">Push通知</ThemedText>
              <Switch
                testID="push-preference-switch"
                value={pushPreferenceQuery.data.enabled}
                disabled={updatePushPreference.isPending}
                onValueChange={(next) => updatePushPreference.mutate(next)}
              />
            </ThemedView>
          )}

          {updatePushPreference.isError && (
            <ThemedText type="small" themeColor="textSecondary" testID="push-preference-update-error">
              {getErrorMessage(updatePushPreference.error)}
            </ThemedText>
          )}
        </ThemedView>

        <TouchableOpacity
          onPress={handleLogout}
          disabled={loggingOut}
          testID="mypage-logout-button"
          accessibilityRole="button"
          accessibilityLabel="ログアウト"
          style={styles.logoutButton}
        >
          {loggingOut ? <ActivityIndicator testID="mypage-logout-loading" /> : <ThemedText type="linkPrimary">ログアウト</ThemedText>}
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
}

/**
 * Password change / Google linking / Email+Password linking / email
 * verification re-send, all in one card. Each action is always offered
 * (no "already linked" indicator - see useAccountLinking.ts's docblock);
 * feedback comes entirely from the Backend's own response to each
 * attempt, mapped to a StageArt-native message here - never a raw
 * Infrastructure term like "WordPress User" or "EmailCredential" (this
 * Phase's explicit UI/UX requirement).
 */
function AccountSecurityCard() {
  const changePassword = useChangePassword();
  const linkGoogleAccount = useLinkGoogleAccount();
  const addEmailCredential = useAddEmailCredential();
  const requestEmailVerification = useRequestEmailVerification();

  const [passwordFormOpen, setPasswordFormOpen] = useState(false);
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [passwordFeedback, setPasswordFeedback] = useState<string | null>(null);

  const [emailFormOpen, setEmailFormOpen] = useState(false);
  const [linkEmail, setLinkEmail] = useState('');
  const [linkPassword, setLinkPassword] = useState('');
  const [emailFeedback, setEmailFeedback] = useState<string | null>(null);

  const [googleFeedback, setGoogleFeedback] = useState<string | null>(null);
  const [verificationFeedback, setVerificationFeedback] = useState<string | null>(null);

  async function handleChangePassword() {
    setPasswordFeedback(null);
    try {
      await changePassword.mutateAsync({ currentPassword, newPassword });
      setPasswordFeedback('パスワードを変更しました。');
      setCurrentPassword('');
      setNewPassword('');
      setPasswordFormOpen(false);
    } catch (error) {
      setPasswordFeedback(mapChangePasswordError(error));
    }
  }

  async function handleLinkGoogle() {
    setGoogleFeedback(null);
    try {
      await linkGoogleAccount.mutateAsync();
      setGoogleFeedback('Googleアカウントを連携しました。');
    } catch (error) {
      if (error instanceof GoogleSignInCancelledError) {
        return;
      }
      setGoogleFeedback(mapLinkGoogleError(error));
    }
  }

  async function handleAddEmailCredential() {
    setEmailFeedback(null);
    try {
      await addEmailCredential.mutateAsync({ email: linkEmail.trim(), password: linkPassword });
      setEmailFeedback('メールアドレスとパスワードを設定しました。');
      setLinkEmail('');
      setLinkPassword('');
      setEmailFormOpen(false);
    } catch (error) {
      setEmailFeedback(mapAddEmailCredentialError(error));
    }
  }

  async function handleRequestVerification() {
    setVerificationFeedback(null);
    try {
      await requestEmailVerification.mutateAsync();
      setVerificationFeedback('確認メールを送信しました。');
    } catch (error) {
      setVerificationFeedback(mapRequestVerificationError(error));
    }
  }

  return (
    <ThemedView style={styles.card}>
      <ThemedText type="subtitle" style={styles.cardTitle}>
        アカウント連携・セキュリティ
      </ThemedText>

      {/* パスワード変更 */}
      <TouchableOpacity testID="mypage-change-password-toggle" onPress={() => setPasswordFormOpen((open) => !open)}>
        <ThemedText type="linkPrimary">パスワードを変更</ThemedText>
      </TouchableOpacity>
      {passwordFormOpen && (
        <ThemedView style={styles.inlineForm}>
          <ThemedTextInput
            testID="mypage-current-password"
            placeholder="現在のパスワード"
            value={currentPassword}
            onChangeText={setCurrentPassword}
            secureTextEntry
            autoCapitalize="none"
            autoCorrect={false}
            textContentType="password"
            autoComplete="current-password"
            style={styles.input}
          />
          {/* textContentType="oneTimeCode": suppresses iOS's "Use Strong
              Password?" suggestion overlay - see register.tsx's comment
              for the real-device evidence this is based on. */}
          <ThemedTextInput
            testID="mypage-new-password"
            placeholder="新しいパスワード（8文字以上）"
            value={newPassword}
            onChangeText={setNewPassword}
            secureTextEntry
            autoCapitalize="none"
            autoCorrect={false}
            textContentType="oneTimeCode"
            style={styles.input}
          />
          <TouchableOpacity
            testID="mypage-change-password-submit"
            onPress={handleChangePassword}
            disabled={changePassword.isPending}
            style={styles.smallButton}
          >
            {changePassword.isPending ? <ActivityIndicator /> : <ThemedText style={styles.smallButtonText}>変更する</ThemedText>}
          </TouchableOpacity>
        </ThemedView>
      )}
      {passwordFeedback && (
        <ThemedText testID="mypage-change-password-feedback" type="small" themeColor="textSecondary">
          {passwordFeedback}
        </ThemedText>
      )}

      {/* Google連携 */}
      <TouchableOpacity
        testID="mypage-link-google-button"
        onPress={handleLinkGoogle}
        disabled={linkGoogleAccount.isPending}
        style={styles.linkRow}
      >
        {linkGoogleAccount.isPending ? <ActivityIndicator /> : <ThemedText type="linkPrimary">Googleアカウントを連携</ThemedText>}
      </TouchableOpacity>
      {googleFeedback && (
        <ThemedText testID="mypage-link-google-feedback" type="small" themeColor="textSecondary">
          {googleFeedback}
        </ThemedText>
      )}

      {/* Email+Password連携 */}
      <TouchableOpacity testID="mypage-link-email-toggle" onPress={() => setEmailFormOpen((open) => !open)}>
        <ThemedText type="linkPrimary">メールアドレス＋パスワードを追加</ThemedText>
      </TouchableOpacity>
      {emailFormOpen && (
        <ThemedView style={styles.inlineForm}>
          <ThemedTextInput
            testID="mypage-link-email"
            placeholder="メールアドレス"
            value={linkEmail}
            onChangeText={setLinkEmail}
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="email-address"
            style={styles.input}
          />
          {/* textContentType="oneTimeCode": suppresses iOS's "Use Strong
              Password?" suggestion overlay - see register.tsx's comment
              for the real-device evidence this is based on. */}
          <ThemedTextInput
            testID="mypage-link-email-password"
            placeholder="パスワード（8文字以上）"
            value={linkPassword}
            onChangeText={setLinkPassword}
            secureTextEntry
            autoCapitalize="none"
            autoCorrect={false}
            textContentType="oneTimeCode"
            style={styles.input}
          />
          <TouchableOpacity
            testID="mypage-link-email-submit"
            onPress={handleAddEmailCredential}
            disabled={addEmailCredential.isPending}
            style={styles.smallButton}
          >
            {addEmailCredential.isPending ? <ActivityIndicator /> : <ThemedText style={styles.smallButtonText}>設定する</ThemedText>}
          </TouchableOpacity>
        </ThemedView>
      )}
      {emailFeedback && (
        <ThemedText testID="mypage-link-email-feedback" type="small" themeColor="textSecondary">
          {emailFeedback}
        </ThemedText>
      )}

      {/* メール確認再送 */}
      <TouchableOpacity
        testID="mypage-resend-verification-button"
        onPress={handleRequestVerification}
        disabled={requestEmailVerification.isPending}
        style={styles.linkRow}
      >
        {requestEmailVerification.isPending ? (
          <ActivityIndicator />
        ) : (
          <ThemedText type="linkPrimary">確認メールを再送する</ThemedText>
        )}
      </TouchableOpacity>
      {verificationFeedback && (
        <ThemedText testID="mypage-resend-verification-feedback" type="small" themeColor="textSecondary">
          {verificationFeedback}
        </ThemedText>
      )}
    </ThemedView>
  );
}

function mapChangePasswordError(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.statusCode === 401) return '現在のパスワードが正しくありません。';
    if (error.statusCode === 404) return '先にメールアドレスとパスワードを設定してください。';
    if (error.statusCode === 422) return 'パスワードは8文字以上で入力してください。';
  }
  return getErrorMessage(error);
}

function mapLinkGoogleError(error: unknown): string {
  if (error instanceof ApiError && error.statusCode === 409) {
    return 'このGoogleアカウントは既に他のStageArtアカウントに連携されています。';
  }
  return getErrorMessage(error);
}

function mapAddEmailCredentialError(error: unknown): string {
  if (error instanceof ApiError && error.statusCode === 409) {
    return '既にメールアドレスとパスワードが設定されているか、このメールアドレスは他のアカウントで使用されています。';
  }
  if (error instanceof ApiError && error.statusCode === 422) {
    return 'パスワードは8文字以上で入力してください。';
  }
  return getErrorMessage(error);
}

function mapRequestVerificationError(error: unknown): string {
  if (error instanceof ApiError && error.statusCode === 404) {
    return '先にメールアドレスとパスワードを設定してください。';
  }
  return getErrorMessage(error);
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  content: { flexGrow: 1, padding: Spacing.four, gap: Spacing.three },
  title: { fontSize: 24, lineHeight: 30 },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.two,
  },
  cardTitle: { marginBottom: Spacing.one },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: Spacing.one,
  },
  linkRow: { paddingVertical: Spacing.one },
  inlineForm: { gap: Spacing.two, paddingVertical: Spacing.one },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
  },
  smallButton: {
    backgroundColor: '#4a3f7a',
    borderRadius: 8,
    paddingVertical: Spacing.two,
    alignItems: 'center',
  },
  smallButtonText: { color: '#fff', fontWeight: '600' },
  logoutButton: {
    alignItems: 'center',
    paddingVertical: Spacing.three,
  },
});
