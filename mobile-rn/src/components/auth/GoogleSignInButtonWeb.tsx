import { useEffect, useRef } from 'react';
import { View } from 'react-native';

import { getGoogleWebClientId } from '@/api/config';

type GoogleCredentialResponse = { credential: string };

type GoogleIdConfiguration = {
  client_id: string;
  callback: (response: GoogleCredentialResponse) => void;
  nonce?: string;
  ux_mode?: 'popup' | 'redirect';
};

type GoogleButtonConfiguration = {
  type?: 'standard' | 'icon';
  theme?: 'outline' | 'filled_blue' | 'filled_black';
  size?: 'large' | 'medium' | 'small';
  shape?: 'rectangular' | 'pill' | 'circle' | 'square';
  text?: 'signin_with' | 'signup_with' | 'continue_with' | 'signin';
  logo_alignment?: 'left' | 'center';
  locale?: string;
  width?: number;
};

declare global {
  interface Window {
    google?: {
      accounts: {
        id: {
          initialize: (config: GoogleIdConfiguration) => void;
          renderButton: (parent: HTMLElement, options: GoogleButtonConfiguration) => void;
        };
      };
    };
  }
}

const GIS_SCRIPT_SRC = 'https://accounts.google.com/gsi/client';
let gisScriptPromise: Promise<void> | null = null;

function loadGisScript(): Promise<void> {
  if (window.google?.accounts?.id) {
    return Promise.resolve();
  }
  if (gisScriptPromise) {
    return gisScriptPromise;
  }

  gisScriptPromise = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = GIS_SCRIPT_SRC;
    script.async = true;
    script.defer = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('Failed to load Google Identity Services script.'));
    document.head.appendChild(script);
  });

  return gisScriptPromise;
}

function generateNonce(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return crypto.randomUUID();
  }
  return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

/**
 * StageArt Google Web Sign-In (2026-09-04): the Web-only "Googleで続ける"
 * entry point, rendered in place of login.tsx's native custom
 * TouchableOpacity+GoogleIcon button (see login.tsx's Platform.OS
 * branch). Google Identity Services (GIS) only ever issues a credential
 * (ID Token) through its OWN rendered button's click handler - a
 * differently-styled proxy button cannot trigger it (GIS's button is a
 * cross-origin iframe; synthetic clicks on it are rejected by design,
 * for anti-clickjacking reasons - this is why Web cannot reuse the same
 * custom-styled button native does).
 *
 * `text: 'continue_with', locale: 'ja'` renders Google's own official
 * Japanese translation for that button variant, which is expected to
 * read "Googleで続ける" - the same wording this screen already uses for
 * native, preserving the "Googleで続ける" UI/copy requirement as closely
 * as Google's button customization API allows. Visual confirmation is
 * still worth doing after this is deployed, since Google's own button
 * cannot be pixel-styled to match the app's other buttons.
 *
 * StageArt 認証画面デザイン統一 (2026-09-07): `theme: 'filled_black'` is
 * Google's own official dark GIS button theme (the `GoogleButtonConfiguration`
 * type below already declared this as a valid value) - used instead of
 * `outline` so this button no longer reads as a bright white rectangle on
 * the dark auth background, without touching GIS's own rendered markup/
 * CSS in any unsupported way (see this screen's own docblock on why a
 * differently-styled proxy button cannot be substituted). `width: 400`
 * matches the unified auth content max-width other auth screens now use.
 *
 * The ID Token this callback receives is audienced (`aud` claim) to the
 * same Web-type OAuth Client ID native's GoogleSignin.configure({
 * webClientId }) already uses - StageArt's Backend
 * (GoogleIdTokenVerifier.php) was already built expecting exactly that
 * Client ID regardless of platform, so it accepts a Web-issued token
 * with no Backend changes.
 */
export function GoogleSignInButtonWeb({ onIdToken, disabled }: { onIdToken: (idToken: string) => void; disabled?: boolean }) {
  const containerRef = useRef<View>(null);

  useEffect(() => {
    let cancelled = false;
    const clientId = getGoogleWebClientId();
    if (!clientId) return;

    loadGisScript()
      .then(() => {
        if (cancelled || !window.google) return;

        window.google.accounts.id.initialize({
          client_id: clientId,
          callback: (response) => {
            if (!cancelled) onIdToken(response.credential);
          },
          nonce: generateNonce(),
          ux_mode: 'popup',
        });

        const node = containerRef.current as unknown as HTMLElement | null;
        if (node) {
          window.google.accounts.id.renderButton(node, {
            type: 'standard',
            theme: 'filled_black',
            size: 'large',
            shape: 'rectangular',
            text: 'continue_with',
            logo_alignment: 'left',
            locale: 'ja',
            width: 400,
          });
        }
      })
      .catch(() => {
        // GIS script failed to load (offline, ad-blocker, etc.) - the
        // button simply never appears; Email/Password remains available,
        // matching this screen's existing "absent rather than crashing"
        // pattern for the native Google icon (see login.tsx's
        // useGoogleSigninButtonComponent docblock).
      });

    return () => {
      cancelled = true;
    };
  }, [onIdToken]);

  return <View ref={containerRef} testID="login-google-button-web" pointerEvents={disabled ? 'none' : 'auto'} />;
}
