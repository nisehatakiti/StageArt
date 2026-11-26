import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useEffect, useState, type ComponentType } from 'react';
import { ActivityIndicator, Alert, Platform, StyleSheet, TouchableOpacity, type ViewProps } from 'react-native';

import { isGoogleSignInAvailable, signInWithGoogleDiagnostic, type GoogleSignInDiagnosticStep } from '@/auth/googleSignIn';
import { useAuth } from '@/auth/AuthContext';
import { ApiError } from '@/api/errors';
import { clearLoginEmailHandoff, peekLoginEmailHandoff, setEmailHandoff } from '@/auth/loginEmailHandoff';
import { checkAccount, type AccountCheckResult, type AccountCheckStatus } from '@/features/auth/api';
import { appOnlyRoute } from '@/components/app-only-route';
import { AuthLayout } from '@/components/auth/AuthLayout';
import { authStyles } from '@/components/auth/authStyles';
import { STAGE } from '@/components/auth/authTheme';
import { GoogleSignInButtonWeb } from '@/components/auth/GoogleSignInButtonWeb';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { safeReturnTo } from '@/utils/returnTo';

/**
 * StageArt Authentication Phase 5: the official StageArt Mobile login
 * screen. Google is the primary path (Backend Phase 2's official
 * decision); Email+Password is the equal secondary official path. Two
 * things deliberately do NOT appear anywhere on this screen, per this
 * Phase's explicit instruction: a WordPress username/Application
 * Password field, and any Infrastructure-flavored error text - both are
 * legacy/internal concepts a StageArt user must never see.
 *
 * Unlike the Flutter reference implementation - where the whole widget
 * tree reactively rebuilds off a ChangeNotifier and simply swaps which
 * screen is "current" - Expo Router's Stack holds real, imperative
 * navigation state: being on the /login route does not change just
 * because useAuth().status flips to 'authenticated' elsewhere. A
 * successful login must explicitly navigate away, or the app is left
 * silently stuck on the login screen despite being authenticated (a
 * genuine bug caught by src/app-tests/navigation.test.tsx during Phase
 * 5.0, not a hypothetical - see login-flow.test.tsx).
 *
 * A real-device correction: loginWithEmail()'s result now carries
 * `emailVerified` directly (resolved once, internally, right after the
 * Backend call succeeds - see AuthContext.tsx's own docblock) instead
 * of this screen making its own follow-up GET /me call afterward. An
 * unverified account's session is deliberately never persisted by
 * AuthContext in the first place, so this screen only needs to read the
 * flag it's handed and choose where to go.
 *
 * StageArt Authentication Phase 6: `hasName` is checked the same way,
 * for both Email and Google - there is no "Google already proved
 * identity, skip straight to Home" branch (this Phase's explicit
 * requirement). Only called once emailVerified is already known true
 * (registration-pending is handled separately, before this). `hint`
 * fields (populated only for Google, when Google's own ID Token
 * happened to carry them) are forwarded to set-name.tsx as route params
 * purely as default form values - the user still confirms/edits and
 * saves them explicitly.
 *
 * PR #7 R14-B (design doc §10 「ログイン」, §14.2): `returnTo` is the login
 * screen's return_to after safeReturnTo() (an in-app path, or null). A
 * person with a name goes there; a person without one still sets the name
 * first, and the same checked path is handed to set-name.tsx as its own
 * return_to, so they reach it afterwards (e.g. /participant-invitations).
 */
function resolvePostLoginRoute(
  hasName: boolean,
  familyNameHint: string | null,
  givenNameHint: string | null,
  returnTo: string | null
): Href {
  if (hasName) {
    return (returnTo ?? '/home') as Href;
  }
  const params: Record<string, string> = {};
  if (familyNameHint && givenNameHint) {
    params.family_name_hint = familyNameHint;
    params.given_name_hint = givenNameHint;
  }
  if (returnTo) {
    params.return_to = returnTo;
  }
  return Object.keys(params).length > 0 ? { pathname: '/set-name', params } : '/set-name';
}

/**
 * StageArt Google認証 段階的診断 (2026-08-23): renders every stage of
 * signInWithGoogleDiagnostic()'s trace (module registration checks,
 * configure(), hasPlayServices(), signIn()) as one readable block, so
 * "module not registered" / "OAuth Client ID misconfigured" / "the
 * native SDK call itself failed" are visibly distinguishable instead of
 * collapsing into one generic message.
 */
function formatGoogleSignInSteps(steps: GoogleSignInDiagnosticStep[]): string {
  const icon = { ok: '✓', error: '✗', skipped: '–' } as const;
  return steps.map((s) => `${icon[s.status]} ${s.step}: ${s.detail}`).join('\n');
}

type GoogleSigninButtonComponent = ComponentType<
  ViewProps & { size?: number; color?: 'dark' | 'light'; disabled?: boolean; onPress?: () => void }
> & {
  Size: { Icon: number; Standard: number; Wide: number };
  Color: { Dark: 'dark'; Light: 'light' };
};

/**
 * The official Google "G" mark, rendered by
 * @react-native-google-signin/google-signin's own GoogleSigninButton
 * widget (already a dependency of this app for the sign-in flow itself
 * - no new dependency added for this) - Google provides this component
 * so apps render their brand mark correctly rather than an
 * approximation drawn by hand.
 *
 * Loaded dynamically, the same way googleSignIn.ts's signInWithGoogle()
 * already has to (see that file's own docblock): the currently-
 * installed EAS Development Build predates this dependency, and a
 * top-level `import` throws the instant this screen mounts - i.e. on
 * every app launch, breaking Email/Password login too, for an unrelated
 * decorative element. A static top-level import also crashes outright
 * under Jest (`RNGoogleSignin` TurboModule not registered - confirmed
 * by actually running the test suite, not assumed) for the identical
 * reason. Failing to load (old binary, or the test environment) simply
 * leaves the icon absent - the button's existing Japanese label/
 * testID/tap handling are entirely unaffected either way.
 */
function useGoogleSigninButtonComponent(): GoogleSigninButtonComponent | null {
  const [Button, setButton] = useState<GoogleSigninButtonComponent | null>(null);

  useEffect(() => {
    let cancelled = false;

    import('@react-native-google-signin/google-signin')
      .then((mod) => {
        if (!cancelled) {
          setButton(() => mod.GoogleSigninButton as unknown as GoogleSigninButtonComponent);
        }
      })
      .catch(() => {
        // Old binary / test environment - see this function's own
        // docblock. No icon, no error surfaced to the user.
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return Button;
}

/**
 * Login in two steps: the address first (「次へ」 asks POST
 * /auth/account/check which sign-in path it leads to), then the matching
 * form. `fallback` is the ordinary password + Google form, used when the
 * check itself could not be made (rate limit, network, server) so that the
 * check can never lock anyone out of signing in.
 */
type LoginStep = { kind: 'email' } | { kind: 'checked'; check: AccountCheckResult } | { kind: 'fallback' };

const PASSWORD_STATUSES: AccountCheckStatus[] = ['PASSWORD_LOGIN', 'MULTIPLE_AUTH_METHODS', 'EMAIL_VERIFICATION_REQUIRED'];

function accountNotice(check: AccountCheckResult, googleAvailable: boolean): string | null {
  const google = check.methods.includes('google');

  switch (check.status) {
    case 'MULTIPLE_AUTH_METHODS':
      return googleAvailable ? 'パスワード、またはGoogleでログインできます。' : null;
    case 'EMAIL_VERIFICATION_REQUIRED':
      return `このメールアドレスの確認がまだ済んでいません。届いた確認メールのリンクを開いてください。確認メールを再送するには、パスワードを入力して「ログイン」を押してください。${google && googleAvailable ? 'Googleでもログインできます。' : ''}`;
    case 'GOOGLE_LOGIN':
      return googleAvailable
        ? 'このメールアドレスはGoogleアカウントで登録されています。「Googleで続ける」からログインしてください。'
        : 'このメールアドレスはGoogleアカウントで登録されています。この画面ではGoogleログインを利用できないため、Googleログインに対応したStageArtの画面からログインしてください。';
    case 'NOT_FOUND':
      return 'このメールアドレスはまだ登録されていません。新規登録を行ってください。';
    case 'DISABLED':
      return 'このアカウントは現在ご利用いただけません。お心当たりのない場合は、StageArtの運営にお問い合わせください。';
    default:
      return null;
  }
}

function LoginScreen() {
  const { loginWithEmail, loginWithGoogle } = useAuth();
  const router = useRouter();
  // R14-B §10 (R13 Medium): only an in-app path is followed; anything else is ignored.
  const { return_to: returnToParam } = useLocalSearchParams<{ return_to?: string }>();
  const returnTo = safeReturnTo(returnToParam);
  const GoogleIcon = useGoogleSigninButtonComponent();

  // Pre-filled with the address just verified on the confirmation screen
  // (auth/loginEmailHandoff.ts, memory only); empty otherwise. Still editable.
  const [email, setEmail] = useState(() => peekLoginEmailHandoff() ?? '');
  const [password, setPassword] = useState('');
  const [step, setStep] = useState<LoginStep>({ kind: 'email' });
  const [checking, setChecking] = useState(false);
  const [submittingEmail, setSubmittingEmail] = useState(false);
  const [submittingGoogle, setSubmittingGoogle] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Read once: a later visit to the login screen starts empty again.
  useEffect(() => {
    clearLoginEmailHandoff();
  }, []);

  const submitting = submittingEmail || submittingGoogle || checking;
  const googleAvailable = isGoogleSignInAvailable();
  const showPassword = step.kind === 'fallback' || (step.kind === 'checked' && PASSWORD_STATUSES.includes(step.check.status));
  const showGoogle = googleAvailable && (step.kind === 'fallback' || (step.kind === 'checked' && step.check.methods.includes('google')));
  const notice = step.kind === 'checked' ? accountNotice(step.check, googleAvailable) : null;

  /** Back to step 1, keeping the address (the password is dropped). */
  function backToEmailStep() {
    setStep({ kind: 'email' });
    setPassword('');
    setErrorMessage(null);
  }

  /** The address stays editable on every step; changing it after a check asks again. */
  function handleEmailChange(next: string) {
    setEmail(next);
    if (step.kind !== 'email') {
      backToEmailStep();
    }
  }

  async function handleNext() {
    if (checking) {
      return;
    }

    const trimmedEmail = email.trim();
    setErrorMessage(null);

    if (trimmedEmail === '') {
      setErrorMessage('メールアドレスを入力してください。');
      return;
    }

    setChecking(true);
    try {
      const check = await checkAccount(trimmedEmail);
      setStep({ kind: 'checked', check });
    } catch (error) {
      if (error instanceof ApiError && error.code === 'stageart_account_check_invalid_email') {
        setErrorMessage('メールアドレスの形式が正しくありません。');
      } else if (error instanceof ApiError && error.statusCode === 429) {
        setErrorMessage('確認の回数が多いため、アカウントを確認できませんでした。しばらく待ってからもう一度お試しいただくか、このままログインしてください。');
        setStep({ kind: 'fallback' });
      } else {
        setErrorMessage('アカウントを確認できませんでした。もう一度お試しいただくか、このままログインしてください。');
        setStep({ kind: 'fallback' });
      }
    } finally {
      setChecking(false);
    }
  }

  /** 「新規登録へ」 / the auxiliary register link: the address goes along (memory only), still editable there. */
  function goToRegister() {
    setEmailHandoff('register', email);
    router.push('/register');
  }

  async function handleEmailSubmit() {
    setSubmittingEmail(true);
    setErrorMessage(null);

    const trimmedEmail = email.trim();
    const result = await loginWithEmail(trimmedEmail, password);

    setSubmittingEmail(false);
    if (!result.ok) {
      setErrorMessage(result.message);
      return;
    }

    if (!result.emailVerified) {
      router.replace(`/registration-pending?email=${encodeURIComponent(trimmedEmail)}`);
      return;
    }

    router.replace(resolvePostLoginRoute(result.hasName, result.familyNameHint, result.givenNameHint, returnTo));
  }

  /**
   * The shared "we already have a Google ID Token, finish logging in"
   * path - identical regardless of how that token was obtained (native
   * GoogleSignin.signIn() vs Web's GoogleSignInButtonWeb callback, see
   * that component's own docblock for why Web cannot share the same
   * button-press flow native uses). Callers own submittingGoogle's
   * lifecycle around this call, since native's diagnostic path has an
   * earlier failure branch (module/config errors) this function never
   * needs to know about.
   */
  async function completeGoogleLogin(idToken: string) {
    try {
      const result = await loginWithGoogle(idToken);

      if (!result.ok) {
        setErrorMessage(result.message);
        return;
      }

      router.replace(resolvePostLoginRoute(result.hasName, result.familyNameHint, result.givenNameHint, returnTo));
    } catch (error) {
      const message = `StageArt認証への到達に失敗しました。${error instanceof Error ? `message="${error.message}"` : String(error)}`;
      Alert.alert('Googleサインイン診断', message);
      setErrorMessage(message);
    }
  }

  async function handleGoogleSubmit() {
    setSubmittingGoogle(true);
    setErrorMessage(null);

    const diagnostic = await signInWithGoogleDiagnostic();
    console.log(formatGoogleSignInSteps(diagnostic.steps));

    if (!diagnostic.ok) {
      setSubmittingGoogle(false);

      if (diagnostic.cancelled) {
        // The user backed out of the native Google picker - not an error
        // worth surfacing as one.
        return;
      }

      const message = formatGoogleSignInSteps(diagnostic.steps);
      // Alert.alert is an imperative native call, not dependent on this
      // screen's own re-render cycle - shown alongside (not instead of)
      // setErrorMessage below, as a diagnostic aid while the exact
      // failure stage (module registration vs OAuth config vs the
      // native SDK call itself) is still being narrowed down. Every
      // stage's real outcome is included verbatim, never collapsed into
      // a single generic message.
      Alert.alert('Googleサインイン診断', message);
      setErrorMessage(message);
      return;
    }

    try {
      await completeGoogleLogin(diagnostic.idToken);
    } finally {
      setSubmittingGoogle(false);
    }
  }

  /**
   * Web entry point: called from GoogleSignInButtonWeb's own callback,
   * which Google Identity Services invokes once the user completes its
   * popup - not from a press on this screen's own button (see that
   * component's docblock for why).
   */
  async function handleGoogleWebIdToken(idToken: string) {
    setSubmittingGoogle(true);
    setErrorMessage(null);

    try {
      await completeGoogleLogin(idToken);
    } finally {
      setSubmittingGoogle(false);
    }
  }

  return (
    <AuthLayout logoTestID="login-brand-logo">
      {/* Step 1: the address only. It stays editable on every step. */}
      <ThemedTextInput
        testID="login-email"
        placeholder="メールアドレス"
        value={email}
        onChangeText={handleEmailChange}
        autoCapitalize="none"
        autoCorrect={false}
        keyboardType="email-address"
        textContentType="emailAddress"
        autoComplete="email"
        placeholderTextColor={STAGE.placeholder}
        style={authStyles.input}
      />

      {notice && (
        <ThemedText testID="login-account-notice" type="small" style={authStyles.description}>
          {notice}
        </ThemedText>
      )}

      {step.kind === 'email' && (
        <TouchableOpacity
          testID="login-next"
          onPress={handleNext}
          disabled={submitting}
          accessibilityState={{ disabled: submitting, busy: checking }}
          style={[authStyles.button, submitting && authStyles.buttonDisabled]}
        >
          {checking ? <ActivityIndicator color="#fff" /> : <ThemedText style={authStyles.buttonText}>次へ</ThemedText>}
        </TouchableOpacity>
      )}

      {showPassword && (
        <>
          <ThemedTextInput
            testID="login-password"
            placeholder="パスワード"
            value={password}
            onChangeText={setPassword}
            autoCapitalize="none"
            autoCorrect={false}
            secureTextEntry
            textContentType="password"
            autoComplete="current-password"
            placeholderTextColor={STAGE.placeholder}
            style={authStyles.input}
          />
          <TouchableOpacity
            testID="login-submit"
            onPress={handleEmailSubmit}
            disabled={submitting}
            style={[authStyles.button, submitting && authStyles.buttonDisabled]}
          >
            {submittingEmail ? <ActivityIndicator color="#fff" /> : <ThemedText style={authStyles.buttonText}>ログイン</ThemedText>}
          </TouchableOpacity>
        </>
      )}

      {showPassword && showGoogle && (
        <ThemedView style={styles.dividerRow}>
          <ThemedView style={styles.dividerLine} />
          <ThemedText type="small" style={styles.dividerText}>
            または
          </ThemedText>
          <ThemedView style={styles.dividerLine} />
        </ThemedView>
      )}

      {/*
       * StageArt Google Web Sign-In (2026-09-04): isGoogleSignInAvailable()
       * now returns true on Web once a Web Client ID is configured
       * (see googleSignIn.web.ts) - Web renders GoogleSignInButtonWeb
       * (Google Identity Services) instead of this custom button,
       * since GIS can only issue an ID Token through its own
       * rendered button's callback (see that component's docblock
       * for why a differently-styled proxy button cannot trigger
       * it). Native's button below is unchanged.
       */}
      {showGoogle &&
        (Platform.OS === 'web' ? (
          <GoogleSignInButtonWeb onIdToken={handleGoogleWebIdToken} disabled={submitting} />
        ) : (
          <TouchableOpacity
            testID="login-google-button"
            onPress={handleGoogleSubmit}
            disabled={submitting}
            style={[styles.googleButton, submitting && authStyles.buttonDisabled]}
          >
            {submittingGoogle ? (
              <ActivityIndicator color={STAGE.link} />
            ) : (
              <>
                {/* The official Google "G" mark, rendered by
                    @react-native-google-signin/google-signin's own
                    GoogleSigninButton widget (already a dependency of
                    this app for the sign-in flow itself - no new
                    dependency added here) - Google provides this exact
                    component so apps render their brand mark correctly,
                    rather than an approximation drawn by hand. Absent
                    entirely (see useGoogleSigninButtonComponent's
                    docblock) rather than crashing when the native
                    module isn't available yet.
                    `pointerEvents="none"` makes it purely decorative so
                    taps still go to this TouchableOpacity, which keeps
                    StageArt's own Japanese label/testID/loading-state
                    handling unchanged. */}
                {GoogleIcon && (
                  // react-hooks/static-components flags any component
                  // reference read from a hook/state and used as a JSX
                  // tag, since its identity could in general change
                  // every render (causing unwanted remounts). Here it
                  // genuinely cannot: useGoogleSigninButtonComponent's
                  // state only ever transitions null -> the one loaded
                  // module export, exactly once, and then stays
                  // referentially stable for the rest of this screen's
                  // lifetime - this is the dynamic-import-for-an-old-
                  // binary/test-environment pattern (see that hook's
                  // own docblock), not a same-render component factory.
                  // eslint-disable-next-line react-hooks/static-components
                  <GoogleIcon
                    size={GoogleIcon.Size.Icon}
                    color={GoogleIcon.Color.Light}
                    style={styles.googleIcon}
                    pointerEvents="none"
                  />
                )}
                <ThemedText type="default" style={styles.googleButtonText}>
                  Googleで続ける
                </ThemedText>
              </>
            )}
          </TouchableOpacity>
        ))}

      {step.kind === 'checked' && step.check.status === 'NOT_FOUND' && (
        <TouchableOpacity testID="login-go-register" onPress={goToRegister} disabled={submitting} style={authStyles.button}>
          <ThemedText style={authStyles.buttonText}>新規登録へ</ThemedText>
        </TouchableOpacity>
      )}

      {errorMessage && (
        <ThemedText testID="login-error" style={authStyles.error}>
          {errorMessage}
        </ThemedText>
      )}

      {step.kind === 'fallback' && (
        <TouchableOpacity testID="login-retry-check" onPress={handleNext} disabled={submitting}>
          <ThemedText type="link" style={authStyles.linkCentered}>
            アカウントをもう一度確認する
          </ThemedText>
        </TouchableOpacity>
      )}

      {step.kind !== 'email' && (
        <TouchableOpacity testID="login-back" onPress={backToEmailStep} disabled={submitting}>
          <ThemedText type="link" style={authStyles.linkCentered}>
            メールアドレスを入力し直す
          </ThemedText>
        </TouchableOpacity>
      )}

      {/* Auxiliary - the main path is 「次へ」. */}
      {!(step.kind === 'checked' && step.check.status === 'NOT_FOUND') && (
        <TouchableOpacity testID="login-register-link" onPress={goToRegister} disabled={submitting}>
          <ThemedText type="linkPrimary" style={authStyles.linkCentered}>
            アカウントを新規登録
          </ThemedText>
        </TouchableOpacity>
      )}

      {showPassword && (
        <TouchableOpacity testID="login-forgot-password-link" onPress={() => router.push('/forgot-password')} disabled={submitting}>
          <ThemedText type="link" style={authStyles.linkCentered}>
            パスワードを忘れた
          </ThemedText>
        </TouchableOpacity>
      )}
    </AuthLayout>
  );
}

const styles = StyleSheet.create({
  // Explicit dark fill (not just an outline) so the button reads as a
  // deliberate, filled button rather than a ghost/outline one - matching
  // the weight of the email-login button beside it, per the redesign's
  // "Googleログインだけ小さく見えない" requirement. Same paddingVertical as
  // authStyles.input/button so all three sit on one visual rhythm.
  googleButton: {
    flexDirection: 'row',
    backgroundColor: STAGE.inputBackground,
    borderWidth: 1,
    borderColor: STAGE.inputBorder,
    borderRadius: 8,
    paddingVertical: 12,
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.two,
  },
  googleIcon: { width: 20, height: 20 },
  googleButtonText: { fontWeight: '600', color: STAGE.inputText },
  dividerRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two, backgroundColor: 'transparent' },
  dividerLine: { flex: 1, height: StyleSheet.hairlineWidth, backgroundColor: STAGE.divider },
  dividerText: { color: STAGE.placeholder },
});

/**
 * The public site (public-site build, or a public host) never shows a
 * login form - its visitors log in on the application host instead (see
 * PublicLandingPage's login links). A direct /login visit there, or the
 * (app) group's unauthenticated redirect, goes back to the public top
 * page. Native and the application hosts keep the login screen unchanged.
 */
export default appOnlyRoute(LoginScreen);
