import { useState } from 'react';
import { ActivityIndicator, StyleSheet, Switch, TouchableOpacity, View } from 'react-native';

import { ApiError } from '@/api/errors';
import { GoogleSignInCancelledError, isGoogleSignInAvailable } from '@/auth/googleSignIn';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useAddEmailCredential, useChangePassword, useLinkGoogleAccount, useRequestEmailVerification } from '@/features/mypage/useAccountLinking';
import { useLogout } from '@/features/mypage/useLogout';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import { usePushPreference, useUpdatePushPreference } from '@/features/pushPreference/usePushPreference';
import { confirmAlert } from '@/utils/confirmAlert';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Blueprint再構成 Phase 1d: `/account`'s single Web/Native-shared
 * implementation - StageArt's "アカウント管理" (login/authentication/
 * security), split out of `/profile` (which now shows Person information
 * only - see features/person/ProfileContent.tsx). Every hook here is
 * reused unchanged from the pre-existing WebProfileContent.tsx/
 * MyPageContent.tsx (see useAccountLinking.ts's own docblock) - no new
 * Account hook, no new endpoint, no new Credential concept.
 *
 * `email_verified` comes from `useCurrentPerson()` (the same Query
 * Profile's own screen already uses) purely because that is the only
 * endpoint that currently exposes it (GetCurrentPersonUseCase's
 * response shape - a Backend/Domain fact, not something this Client
 * changes). It is displayed here, in Account, not in Profile - which
 * `/me` field a value happens to travel over does not decide where the
 * Client shows it (Phase 1d §7's explicit instruction).
 */
export function AccountContent() {
  const currentPersonQuery = useCurrentPerson();
  const pushPreferenceQuery = usePushPreference();
  const updatePushPreference = useUpdatePushPreference();
  const logout = useLogout();
  const [loggingOut, setLoggingOut] = useState(false);

  function handleLogout() {
    confirmAlert('ログアウト', 'ログアウトしますか？', [
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
  }

  return (
    <View style={styles.container}>
      <ThemedText type="title" testID="account-title">
        アカウント管理
      </ThemedText>

      <SectionCard title="メールアドレス" testID="account-email-section">
        {currentPersonQuery.isLoading && <ActivityIndicator testID="account-email-loading" />}
        {currentPersonQuery.isError && <ThemedText testID="account-email-error">{getErrorMessage(currentPersonQuery.error)}</ThemedText>}
        {currentPersonQuery.data && (
          <View style={styles.row}>
            <ThemedText type="default">メールアドレスの確認</ThemedText>
            <StatusPill ok={currentPersonQuery.data.email_verified} okLabel="確認済み" ngLabel="未確認" />
          </View>
        )}
        <ResendVerificationRow />
      </SectionCard>

      <SecurityCard />

      <SectionCard title="通知" testID="account-notifications-section">
        {pushPreferenceQuery.isLoading && <ActivityIndicator testID="account-push-loading" />}
        {pushPreferenceQuery.isError && <ThemedText testID="account-push-error">{getErrorMessage(pushPreferenceQuery.error)}</ThemedText>}
        {pushPreferenceQuery.data && (
          <View style={styles.row}>
            <ThemedText type="default">Push通知</ThemedText>
            <Switch
              testID="account-push-switch"
              value={pushPreferenceQuery.data.enabled}
              disabled={updatePushPreference.isPending}
              onValueChange={(next) => updatePushPreference.mutate(next)}
            />
          </View>
        )}
      </SectionCard>

      <TouchableOpacity
        onPress={handleLogout}
        disabled={loggingOut}
        testID="account-logout-button"
        accessibilityRole="button"
        accessibilityLabel="ログアウト"
        style={styles.logoutButton}
      >
        {loggingOut ? <ActivityIndicator testID="account-logout-loading" /> : <ThemedText type="linkPrimary">ログアウト</ThemedText>}
      </TouchableOpacity>
    </View>
  );
}

function ResendVerificationRow() {
  const requestEmailVerification = useRequestEmailVerification();
  const [feedback, setFeedback] = useState<string | null>(null);

  async function handleRequestVerification() {
    setFeedback(null);
    try {
      await requestEmailVerification.mutateAsync();
      setFeedback('確認メールを送信しました。');
    } catch (error) {
      setFeedback(mapRequestVerificationError(error));
    }
  }

  return (
    <>
      <TouchableOpacity
        testID="account-resend-verification-button"
        onPress={handleRequestVerification}
        disabled={requestEmailVerification.isPending}
        style={styles.linkRow}
      >
        {requestEmailVerification.isPending ? <ActivityIndicator /> : <ThemedText type="linkPrimary">確認メールを再送する</ThemedText>}
      </TouchableOpacity>
      {feedback && (
        <ThemedText testID="account-resend-verification-feedback" type="small" themeColor="textSecondary">
          {feedback}
        </ThemedText>
      )}
    </>
  );
}

function SectionCard({ title, testID, children }: { title: string; testID?: string; children: React.ReactNode }) {
  return (
    <View style={styles.card} testID={testID}>
      <ThemedText type="subtitle" style={styles.cardTitle}>
        {title}
      </ThemedText>
      {children}
    </View>
  );
}

function StatusPill({ ok, okLabel, ngLabel }: { ok: boolean; okLabel: string; ngLabel: string }) {
  return (
    <View style={[styles.pill, ok ? styles.pillOk : styles.pillNg]}>
      <ThemedText type="small" style={ok ? styles.pillTextOk : styles.pillTextNg}>
        {ok ? okLabel : ngLabel}
      </ThemedText>
    </View>
  );
}

/**
 * Password change / Google linking / Email+Password linking, unchanged
 * from WebProfileContent.tsx/MyPageContent.tsx's own identical
 * SecurityCard/AccountSecurityCard (this Phase's own §6/§15 - reuse, not
 * rebuild). Google linking hidden entirely when isGoogleSignInAvailable()
 * is false (always true on Web today).
 */
function SecurityCard() {
  const changePassword = useChangePassword();
  const linkGoogleAccount = useLinkGoogleAccount();
  const addEmailCredential = useAddEmailCredential();

  const [passwordFormOpen, setPasswordFormOpen] = useState(false);
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [passwordFeedback, setPasswordFeedback] = useState<string | null>(null);

  const [emailFormOpen, setEmailFormOpen] = useState(false);
  const [linkEmail, setLinkEmail] = useState('');
  const [linkPassword, setLinkPassword] = useState('');
  const [emailFeedback, setEmailFeedback] = useState<string | null>(null);

  const [googleFeedback, setGoogleFeedback] = useState<string | null>(null);

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

  return (
    <SectionCard title="ログイン方法" testID="account-security-section">
      <TouchableOpacity testID="account-change-password-toggle" onPress={() => setPasswordFormOpen((open) => !open)} style={styles.linkRow}>
        <ThemedText type="linkPrimary">パスワードを変更</ThemedText>
      </TouchableOpacity>
      {passwordFormOpen && (
        <View style={styles.inlineForm}>
          <ThemedTextInput
            testID="account-current-password"
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
          <ThemedTextInput
            testID="account-new-password"
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
            testID="account-change-password-submit"
            onPress={handleChangePassword}
            disabled={changePassword.isPending}
            style={styles.smallButton}
          >
            {changePassword.isPending ? <ActivityIndicator /> : <ThemedText style={styles.smallButtonText}>変更する</ThemedText>}
          </TouchableOpacity>
        </View>
      )}
      {passwordFeedback && (
        <ThemedText testID="account-change-password-feedback" type="small" themeColor="textSecondary">
          {passwordFeedback}
        </ThemedText>
      )}

      {isGoogleSignInAvailable() && (
        <>
          <TouchableOpacity testID="account-link-google-button" onPress={handleLinkGoogle} disabled={linkGoogleAccount.isPending} style={styles.linkRow}>
            {linkGoogleAccount.isPending ? <ActivityIndicator /> : <ThemedText type="linkPrimary">Googleアカウントを連携</ThemedText>}
          </TouchableOpacity>
          {googleFeedback && (
            <ThemedText testID="account-link-google-feedback" type="small" themeColor="textSecondary">
              {googleFeedback}
            </ThemedText>
          )}
        </>
      )}

      <TouchableOpacity testID="account-link-email-toggle" onPress={() => setEmailFormOpen((open) => !open)} style={styles.linkRow}>
        <ThemedText type="linkPrimary">メールアドレス＋パスワードを追加</ThemedText>
      </TouchableOpacity>
      {emailFormOpen && (
        <View style={styles.inlineForm}>
          <ThemedTextInput
            testID="account-link-email"
            placeholder="メールアドレス"
            value={linkEmail}
            onChangeText={setLinkEmail}
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="email-address"
            style={styles.input}
          />
          <ThemedTextInput
            testID="account-link-email-password"
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
            testID="account-link-email-submit"
            onPress={handleAddEmailCredential}
            disabled={addEmailCredential.isPending}
            style={styles.smallButton}
          >
            {addEmailCredential.isPending ? <ActivityIndicator /> : <ThemedText style={styles.smallButtonText}>設定する</ThemedText>}
          </TouchableOpacity>
        </View>
      )}
      {emailFeedback && (
        <ThemedText testID="account-link-email-feedback" type="small" themeColor="textSecondary">
          {emailFeedback}
        </ThemedText>
      )}
    </SectionCard>
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
  container: { padding: Spacing.four, gap: Spacing.four, maxWidth: 640, width: '100%' },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.two,
    backgroundColor: '#fff',
  },
  cardTitle: { marginBottom: Spacing.half },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  linkRow: { paddingVertical: Spacing.half },
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
    backgroundColor: BrandColors.warmAmber,
    borderRadius: 8,
    paddingVertical: Spacing.two,
    alignItems: 'center',
  },
  smallButtonText: { color: '#fff', fontWeight: '600' },
  pill: { paddingHorizontal: Spacing.two, paddingVertical: Spacing.half, borderRadius: Radius.medium },
  pillOk: { backgroundColor: '#e3f3e8' },
  pillNg: { backgroundColor: '#f7e4de' },
  pillTextOk: { color: '#2f7a4a', fontWeight: '600' },
  pillTextNg: { color: '#a6483a', fontWeight: '600' },
  logoutButton: { alignItems: 'center', paddingVertical: Spacing.three },
});
