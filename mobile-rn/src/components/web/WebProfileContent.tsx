import { useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Alert, StyleSheet, Switch, TouchableOpacity, View } from 'react-native';

import { ApiError } from '@/api/errors';
import { GoogleSignInCancelledError, isGoogleSignInAvailable } from '@/auth/googleSignIn';
import { dedupeProductions } from '@/app/participating-productions';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { WebLayout } from '@/components/web/WebLayout';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useMyDashboard } from '@/features/dashboard/useDashboard';
import { useLogout } from '@/features/mypage/useLogout';
import { useAddEmailCredential, useChangePassword, useLinkGoogleAccount, useRequestEmailVerification } from '@/features/mypage/useAccountLinking';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import { usePushPreference, useUpdatePushPreference } from '@/features/pushPreference/usePushPreference';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Web版 プロフィール Phase: `/profile`'s Web-only presentation -
 * `profile.tsx` itself Platform-branches to this component on web,
 * keeping the existing native `<MyPageContent />` (mobile AppShell,
 * unchanged) on every other platform. Every data hook here is the exact
 * same one MyPageContent.tsx already uses (useCurrentPerson,
 * useOrganizations, usePushPreference/useUpdatePushPreference,
 * useChangePassword/useLinkGoogleAccount/useAddEmailCredential/
 * useRequestEmailVerification, useLogout, isGoogleSignInAvailable) -
 * only the JSX/layout is new, matching this whole redesign's own rule
 * ("モバイルUIをそのままWebにしない" cuts both ways: reuse the data layer,
 * never the mobile screen's markup). set-name.tsx (姓名編集) is reused
 * unchanged too - it was already platform-agnostic (no AppShell), and
 * MyPageContent.tsx's own `return_to=/profile` pattern already sends the
 * user back here after saving, so this screen only needs to link to it,
 * not rebuild it.
 */
export function WebProfileContent() {
  const router = useRouter();
  const currentPersonQuery = useCurrentPerson();
  const organizationsQuery = useOrganizations();
  const dashboardQuery = useMyDashboard();
  const pushPreferenceQuery = usePushPreference();
  const updatePushPreference = useUpdatePushPreference();
  const logout = useLogout();
  const [loggingOut, setLoggingOut] = useState(false);
  const [detailsOpen, setDetailsOpen] = useState(false);

  const person = currentPersonQuery.data;
  const displayName = person ? [person.family_name, person.given_name].filter(Boolean).join(' ') : '';
  const participatingProductions = dashboardQuery.data ? dedupeProductions(dashboardQuery.data.upcoming_rehearsals) : [];

  function handleLogout() {
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
  }

  return (
    <WebLayout breadcrumbs={[{ label: 'StageArt', href: '/dashboard' as Href }, { label: 'マイページ' }]} activeTopLevel="profile">
      <View style={styles.headerRow}>
        <View>
          <ThemedText type="title" testID="web-profile-display-name">
            {displayName || 'マイページ'}
          </ThemedText>
          {currentPersonQuery.isLoading && <ActivityIndicator testID="web-profile-loading" />}
          {currentPersonQuery.isError && (
            <ThemedText testID="web-profile-error">{getErrorMessage(currentPersonQuery.error)}</ThemedText>
          )}
        </View>
        <TouchableOpacity
          testID="web-profile-edit-link"
          onPress={() =>
            router.push({
              pathname: '/set-name',
              params: { family_name_hint: person?.family_name ?? '', given_name_hint: person?.given_name ?? '', return_to: '/profile' },
            })
          }
          style={styles.secondaryButton}
        >
          <ThemedText type="linkPrimary">プロフィールを編集</ThemedText>
        </TouchableOpacity>
      </View>

      <View style={styles.columns}>
        <View style={styles.mainColumn}>
          {/* 基本情報 */}
          <SectionCard title="基本情報" testID="web-profile-basic-info">
            {person && (
              <View style={styles.row}>
                <ThemedText type="default">メールアドレスの確認</ThemedText>
                <StatusPill ok={person.email_verified} okLabel="確認済み" ngLabel="未確認" />
              </View>
            )}
            <TouchableOpacity testID="web-profile-details-toggle" onPress={() => setDetailsOpen((open) => !open)}>
              <ThemedText type="small" themeColor="textSecondary">
                {detailsOpen ? '詳細情報を隠す' : '詳細情報を表示'}
              </ThemedText>
            </TouchableOpacity>
            {detailsOpen && person && (
              <View style={styles.row}>
                <ThemedText type="small" themeColor="textSecondary">
                  Person ID
                </ThemedText>
                <ThemedText type="small" themeColor="textSecondary" testID="web-profile-person-id">
                  {person.id}
                </ThemedText>
              </View>
            )}
          </SectionCard>

          {/* アカウント・セキュリティ */}
          <SecurityCard />

          {/* 所属団体 */}
          <SectionCard title="所属団体" testID="web-profile-organizations">
            {organizationsQuery.isLoading && <ActivityIndicator testID="web-profile-organizations-loading" />}
            {organizationsQuery.isError && (
              <ThemedText testID="web-profile-organizations-error">{getErrorMessage(organizationsQuery.error)}</ThemedText>
            )}
            {!organizationsQuery.isLoading && !organizationsQuery.isError && (organizationsQuery.data?.length ?? 0) === 0 && (
              <ThemedText type="small" themeColor="textSecondary" testID="web-profile-organizations-empty">
                まだ所属している団体がありません。
              </ThemedText>
            )}
            {(organizationsQuery.data?.length ?? 0) > 0 && (
              <View style={styles.list} testID="web-profile-organizations-list">
                {organizationsQuery.data?.map((organization) => (
                  <TouchableOpacity
                    key={organization.id}
                    testID={`web-profile-organization-${organization.id}`}
                    style={styles.itemRow}
                    onPress={() => router.push(`/organizations/${organization.id}` as Href)}
                  >
                    <ThemedText type="smallBold">{organization.name}</ThemedText>
                    <ThemedText type="small" themeColor="textSecondary">
                      {organization.current_person_role === 'OWNER' ? 'オーナー' : 'メンバー'}
                    </ThemedText>
                  </TouchableOpacity>
                ))}
              </View>
            )}
            <TouchableOpacity testID="web-profile-organizations-view-all" onPress={() => router.push('/organizations')}>
              <ThemedText type="link">団体一覧を見る</ThemedText>
            </TouchableOpacity>
          </SectionCard>

          {/* 参加している公演 */}
          <SectionCard title="参加している公演" testID="web-profile-productions">
            {dashboardQuery.isLoading && <ActivityIndicator testID="web-profile-productions-loading" />}
            {!dashboardQuery.isLoading && participatingProductions.length === 0 && (
              <ThemedText type="small" themeColor="textSecondary" testID="web-profile-productions-empty">
                参加している公演・活動はありません。
              </ThemedText>
            )}
            {participatingProductions.length > 0 && (
              <View style={styles.list} testID="web-profile-productions-list">
                {participatingProductions.map((production) => (
                  <TouchableOpacity
                    key={production.productionId}
                    testID={`web-profile-production-${production.productionId}`}
                    style={styles.itemRow}
                    onPress={() => router.push(`/production/${production.productionId}/schedule` as Href)}
                  >
                    <ThemedText type="smallBold">{production.productionName}</ThemedText>
                  </TouchableOpacity>
                ))}
              </View>
            )}
            <TouchableOpacity testID="web-profile-productions-view-all" onPress={() => router.push('/participating-productions')}>
              <ThemedText type="link">すべて見る</ThemedText>
            </TouchableOpacity>
          </SectionCard>
        </View>

        <View style={styles.sideColumn}>
          {/* 通知 */}
          <SectionCard title="通知" testID="web-profile-notifications">
            {pushPreferenceQuery.isLoading && <ActivityIndicator testID="web-profile-push-loading" />}
            {pushPreferenceQuery.isError && (
              <ThemedText testID="web-profile-push-error">{getErrorMessage(pushPreferenceQuery.error)}</ThemedText>
            )}
            {pushPreferenceQuery.data && (
              <View style={styles.row}>
                <ThemedText type="default">Push通知</ThemedText>
                <Switch
                  testID="web-profile-push-switch"
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
            testID="web-profile-logout-button"
            accessibilityRole="button"
            accessibilityLabel="ログアウト"
            style={styles.logoutButton}
          >
            {loggingOut ? <ActivityIndicator testID="web-profile-logout-loading" /> : <ThemedText type="linkPrimary">ログアウト</ThemedText>}
          </TouchableOpacity>
        </View>
      </View>
    </WebLayout>
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
 * Same account-linking actions as MyPageContent.tsx's own
 * AccountSecurityCard (password change / Google linking / Email+Password
 * linking / verification re-send), reusing the identical hooks - only
 * the layout is Web-specific. Google linking hidden entirely when
 * isGoogleSignInAvailable() is false (always true on Web today - see
 * googleSignIn.ts's own docblock), same treatment as login.tsx and
 * MyPageContent.tsx.
 */
function SecurityCard() {
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
    <SectionCard title="アカウント・セキュリティ" testID="web-profile-security">
      <TouchableOpacity testID="web-profile-change-password-toggle" onPress={() => setPasswordFormOpen((open) => !open)}>
        <ThemedText type="linkPrimary">パスワードを変更</ThemedText>
      </TouchableOpacity>
      {passwordFormOpen && (
        <View style={styles.inlineForm}>
          <ThemedTextInput
            testID="web-profile-current-password"
            placeholder="現在のパスワード"
            value={currentPassword}
            onChangeText={setCurrentPassword}
            secureTextEntry
            autoCapitalize="none"
            autoCorrect={false}
            style={styles.input}
          />
          <ThemedTextInput
            testID="web-profile-new-password"
            placeholder="新しいパスワード（8文字以上）"
            value={newPassword}
            onChangeText={setNewPassword}
            secureTextEntry
            autoCapitalize="none"
            autoCorrect={false}
            style={styles.input}
          />
          <TouchableOpacity
            testID="web-profile-change-password-submit"
            onPress={handleChangePassword}
            disabled={changePassword.isPending}
            style={styles.smallButton}
          >
            {changePassword.isPending ? <ActivityIndicator /> : <ThemedText style={styles.smallButtonText}>変更する</ThemedText>}
          </TouchableOpacity>
        </View>
      )}
      {passwordFeedback && (
        <ThemedText testID="web-profile-change-password-feedback" type="small" themeColor="textSecondary">
          {passwordFeedback}
        </ThemedText>
      )}

      <TouchableOpacity
        testID="web-profile-resend-verification-button"
        onPress={handleRequestVerification}
        disabled={requestEmailVerification.isPending}
      >
        {requestEmailVerification.isPending ? <ActivityIndicator /> : <ThemedText type="linkPrimary">確認メールを再送する</ThemedText>}
      </TouchableOpacity>
      {verificationFeedback && (
        <ThemedText testID="web-profile-resend-verification-feedback" type="small" themeColor="textSecondary">
          {verificationFeedback}
        </ThemedText>
      )}

      {isGoogleSignInAvailable() && (
        <>
          <TouchableOpacity testID="web-profile-link-google-button" onPress={handleLinkGoogle} disabled={linkGoogleAccount.isPending}>
            {linkGoogleAccount.isPending ? <ActivityIndicator /> : <ThemedText type="linkPrimary">Googleアカウントを連携</ThemedText>}
          </TouchableOpacity>
          {googleFeedback && (
            <ThemedText testID="web-profile-link-google-feedback" type="small" themeColor="textSecondary">
              {googleFeedback}
            </ThemedText>
          )}
        </>
      )}

      <TouchableOpacity testID="web-profile-link-email-toggle" onPress={() => setEmailFormOpen((open) => !open)}>
        <ThemedText type="linkPrimary">メールアドレス＋パスワードを追加</ThemedText>
      </TouchableOpacity>
      {emailFormOpen && (
        <View style={styles.inlineForm}>
          <ThemedTextInput
            testID="web-profile-link-email"
            placeholder="メールアドレス"
            value={linkEmail}
            onChangeText={setLinkEmail}
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="email-address"
            style={styles.input}
          />
          <ThemedTextInput
            testID="web-profile-link-email-password"
            placeholder="パスワード（8文字以上）"
            value={linkPassword}
            onChangeText={setLinkPassword}
            secureTextEntry
            autoCapitalize="none"
            autoCorrect={false}
            style={styles.input}
          />
          <TouchableOpacity
            testID="web-profile-link-email-submit"
            onPress={handleAddEmailCredential}
            disabled={addEmailCredential.isPending}
            style={styles.smallButton}
          >
            {addEmailCredential.isPending ? <ActivityIndicator /> : <ThemedText style={styles.smallButtonText}>設定する</ThemedText>}
          </TouchableOpacity>
        </View>
      )}
      {emailFeedback && (
        <ThemedText testID="web-profile-link-email-feedback" type="small" themeColor="textSecondary">
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
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: Spacing.four },
  secondaryButton: {
    borderWidth: 1,
    borderColor: '#C6892B',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
  },
  columns: { flexDirection: 'row', gap: Spacing.four, alignItems: 'flex-start' },
  mainColumn: { flex: 2, minWidth: 320, gap: Spacing.four },
  sideColumn: { flex: 1, minWidth: 260, gap: Spacing.four },
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
  list: { gap: Spacing.two },
  itemRow: {
    borderWidth: 1,
    borderColor: '#eee',
    borderRadius: Radius.medium,
    padding: Spacing.two,
    gap: Spacing.half,
  },
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
  logoutButton: { paddingVertical: Spacing.two },
});
