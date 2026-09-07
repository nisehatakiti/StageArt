import { QueryClientProvider } from '@tanstack/react-query';
import { DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';
import { Platform, useColorScheme } from 'react-native';

import { createQueryClient } from '@/api/queryClient';
import { AuthProvider } from '@/auth/AuthContext';
import { BrandColors } from '@/constants/theme';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';
import { ConfirmAlertHost } from '@/utils/confirmAlert';

/**
 * StageArt Web First Phase 1 (docs/04-CommonNavigationDesign.md §3): Web
 * must not depend on swipe-only navigation - every non-Bottom-Nav route
 * gets an explicit header with Expo Router's own automatic back button
 * (shown whenever there's a prior screen in the stack, hidden on the
 * root of a chain - no per-screen wiring needed for this). Native's
 * existing `headerShown: false` behavior is completely unchanged; this
 * only branches for web, per this Phase's explicit "no native behavior
 * changes" scope. The four Bottom-Nav destinations themselves
 * (home/discover/favorites/profile) turn their own header back off
 * individually below, since they're reached via the nav bar, not a back
 * chain.
 */
const webHeaderOptions =
  Platform.OS === 'web'
    ? {
        headerShown: true,
        headerStyle: { backgroundColor: BrandColors.blackoutBlack },
        headerTintColor: BrandColors.stageBeige,
        headerTitleStyle: { color: BrandColors.stageBeige },
      }
    : { headerShown: false };

/** Phase 5.0's State Management Recommendation: one shared QueryClient
 * for the whole app; per-query cache/retry behavior lives in each
 * feature's hook, not here (retry policy itself lives in
 * src/api/queryClient.ts since it is API-error-shape-aware, not
 * feature-specific). */
const queryClient = createQueryClient();

export default function RootLayout() {
  const colorScheme = useColorScheme();

  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          {/* Must be inside QueryClientProvider (invalidates Production/
           * Project queries on Organization switch) and inside AuthProvider
           * (resets to null on logout). */}
          <OrganizationProvider>
            <Stack screenOptions={webHeaderOptions}>
              <Stack.Screen name="index" options={{ headerShown: false }} />
              {/* StageArt 認証画面デザイン統一 (2026-09-07): every
                  authentication screen now renders its own dark
                  AuthLayout shell (background/spotlight/logo) plus an
                  in-content "← ログイン画面へ戻る" link, so the Stack's own
                  black webHeaderOptions bar (title + browser back button)
                  is no longer needed here - it used to be the only thing
                  distinguishing register/forgot-password/reset-password
                  from login (which already had headerShown: false), which
                  is exactly why those three looked like a different app. */}
              <Stack.Screen name="login" options={{ headerShown: false }} />
              <Stack.Screen name="register" options={{ headerShown: false }} />
              <Stack.Screen name="forgot-password" options={{ headerShown: false }} />
              <Stack.Screen name="reset-password" options={{ headerShown: false }} />
              <Stack.Screen name="registration-pending" options={{ headerShown: false }} />
              <Stack.Screen name="verify-email" options={{ headerShown: false }} />
              <Stack.Screen name="set-name" options={{ title: '姓名を設定' }} />
              {/* StageArt Blueprint再構成 Phase 1b: every authenticated
                  screen now lives under the (app) route group (a route
                  group segment is invisible in the actual URL - none of
                  these screens' paths changed), wrapped once by AppChrome
                  (see app/(app)/_layout.tsx) instead of this Stack's own
                  header. headerShown stays false here so AppChrome's
                  header is the only one rendered. */}
              <Stack.Screen name="(app)" options={{ headerShown: false }} />
              {/* Public Page Architecture phase
                  (docs/03-PublicPageURLAndPublicationSchedule.md): moved
                  to the URL root, matching stageart.top's intended path
                  shape (`/{organization-slug}`,
                  `/{organization-slug}/{production-slug}`) - see
                  OrganizationSlug.php's RESERVED list for why this is
                  safe (every real top-level route name below is
                  reserved and can never collide with a real slug).
                  Deliberately stays outside (app) - viewable while
                  unauthenticated (StageArt Blueprint再構成 §26/Audience). */}
              <Stack.Screen name="[organizationSlug]/index" options={{ headerShown: false }} />
              <Stack.Screen name="[organizationSlug]/[productionSlug]" options={{ headerShown: false }} />
              {/* /o/{slug} kept as a redirect-only route for backward
                  compatibility with any pre-existing link. */}
              <Stack.Screen name="o/[organizationSlug]/index" options={{ headerShown: false }} />
              <Stack.Screen name="o/[organizationSlug]/[productionSlug]" options={{ headerShown: false }} />
            </Stack>
            {/* Renders nothing on Native (Alert.alert already works there
                natively) - only mounts the Web confirm-dialog Modal
                target. See src/utils/confirmAlert.web.tsx's docblock. */}
            <ConfirmAlertHost />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    </ThemeProvider>
  );
}
