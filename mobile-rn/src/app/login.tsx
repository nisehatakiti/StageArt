import { useRouter } from 'expo-router';
import { useEffect, useState, type ComponentType } from 'react';
import { ActivityIndicator, Alert, KeyboardAvoidingView, Platform, StyleSheet, TouchableOpacity, type ViewProps } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { isGoogleSignInAvailable, signInWithGoogleDiagnostic, type GoogleSignInDiagnosticStep } from '@/auth/googleSignIn';
import { useAuth } from '@/auth/AuthContext';
import { StageArtLogo } from '@/components/brand/StageArtLogo';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { BrandColors, Spacing } from '@/constants/theme';

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
 */
function resolvePostLoginRoute(
  hasName: boolean,
  familyNameHint: string | null,
  givenNameHint: string | null
): '/home' | '/set-name' | { pathname: '/set-name'; params: { family_name_hint: string; given_name_hint: string } } {
  if (hasName) {
    return '/home';
  }
  if (familyNameHint && givenNameHint) {
    return { pathname: '/set-name', params: { family_name_hint: familyNameHint, given_name_hint: givenNameHint } };
  }
  return '/set-name';
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

export default function LoginScreen() {
  const { loginWithEmail, loginWithGoogle } = useAuth();
  const router = useRouter();
  const GoogleIcon = useGoogleSigninButtonComponent();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submittingEmail, setSubmittingEmail] = useState(false);
  const [submittingGoogle, setSubmittingGoogle] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const submitting = submittingEmail || submittingGoogle;

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

    router.replace(resolvePostLoginRoute(result.hasName, result.familyNameHint, result.givenNameHint));
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
      const result = await loginWithGoogle(diagnostic.idToken);

      if (!result.ok) {
        setErrorMessage(result.message);
        return;
      }

      router.replace(resolvePostLoginRoute(result.hasName, result.familyNameHint, result.givenNameHint));
    } catch (error) {
      const message = `StageArt認証への到達に失敗しました。${error instanceof Error ? `message="${error.message}"` : String(error)}`;
      Alert.alert('Googleサインイン診断', message);
      setErrorMessage(message);
    } finally {
      setSubmittingGoogle(false);
    }
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
        <ThemedView style={styles.container}>
          <ThemedView style={styles.brand}>
            <StageArtLogo width={200} height={60} />
          </ThemedView>

          {/*
           * StageArt mobile-rn 修正指示書 §6: a Web deploy (this app's
           * current public environment) can never satisfy
           * isGoogleSignInAvailable() - @react-native-google-signin/
           * google-signin is native-only, so the button was previously
           * always shown and always failed the moment it was tapped, with
           * only a diagnostic Alert to show for it. Hiding it entirely
           * until a real Web-capable implementation exists (see
           * googleSignIn.ts's own docblock) avoids the "何も起きない"
           * dead-end the instruction explicitly called out, at the cost
           * of the Web login screen offering Email+Password only, for now.
           */}
          {isGoogleSignInAvailable() && (
            <>
              <TouchableOpacity
                testID="login-google-button"
                onPress={handleGoogleSubmit}
                disabled={submitting}
                style={[styles.googleButton, submitting && styles.buttonDisabled]}
              >
                {submittingGoogle ? (
                  <ActivityIndicator />
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

              <ThemedView style={styles.dividerRow}>
                <ThemedView style={styles.dividerLine} />
                <ThemedText type="small" themeColor="textSecondary">
                  または
                </ThemedText>
                <ThemedView style={styles.dividerLine} />
              </ThemedView>
            </>
          )}

          <ThemedTextInput
            testID="login-email"
            placeholder="メールアドレス"
            value={email}
            onChangeText={setEmail}
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="email-address"
            style={styles.input}
          />
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
            style={styles.input}
          />

          {errorMessage && (
            <ThemedText testID="login-error" themeColor="text" style={styles.error}>
              {errorMessage}
            </ThemedText>
          )}

          <TouchableOpacity
            testID="login-submit"
            onPress={handleEmailSubmit}
            disabled={submitting}
            style={[styles.button, submitting && styles.buttonDisabled]}
          >
            {submittingEmail ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>ログイン</ThemedText>}
          </TouchableOpacity>

          <TouchableOpacity testID="login-register-link" onPress={() => router.push('/register')} disabled={submitting}>
            <ThemedText type="linkPrimary" style={styles.linkCentered}>
              アカウントを新規登録
            </ThemedText>
          </TouchableOpacity>

          <TouchableOpacity testID="login-forgot-password-link" onPress={() => router.push('/forgot-password')} disabled={submitting}>
            <ThemedText type="link" style={styles.linkCentered}>
              パスワードを忘れた
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
  brand: { alignItems: 'center', marginBottom: Spacing.two },
  googleButton: {
    flexDirection: 'row',
    borderWidth: 1,
    borderColor: '#dadce0',
    borderRadius: 8,
    paddingVertical: Spacing.three,
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.two,
  },
  googleIcon: { width: 20, height: 20 },
  googleButtonText: { fontWeight: '600' },
  dividerRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two },
  dividerLine: { flex: 1, height: StyleSheet.hairlineWidth, backgroundColor: '#ccc' },
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
    // StageArt Web First Phase 1 (docs/03-BrandIdentity.md): warm amber,
    // not the earlier unrelated purple.
    backgroundColor: BrandColors.warmAmber,
    borderRadius: 8,
    paddingVertical: Spacing.three,
    alignItems: 'center',
    marginTop: Spacing.two,
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  linkCentered: { textAlign: 'center' },
});
