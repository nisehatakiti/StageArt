import { QueryClientProvider } from '@tanstack/react-query';
import { DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';
import { Platform, useColorScheme } from 'react-native';

import { createQueryClient } from '@/api/queryClient';
import { AuthProvider } from '@/auth/AuthContext';
import { BrandColors } from '@/constants/theme';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

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
              <Stack.Screen name="login" options={{ headerShown: false }} />
              <Stack.Screen name="register" options={{ title: 'アカウント登録' }} />
              <Stack.Screen name="forgot-password" options={{ title: 'パスワードを忘れた' }} />
              <Stack.Screen name="reset-password" options={{ title: 'パスワード再設定' }} />
              <Stack.Screen name="registration-pending" options={{ headerShown: false }} />
              <Stack.Screen name="verify-email" options={{ headerShown: false }} />
              <Stack.Screen name="set-name" options={{ title: '姓名を設定' }} />
              {/* Bottom Navigation's own 4 destinations
                  (04-CommonNavigationDesign.md §2/§3.2): reached via the
                  nav bar itself, not a back chain, so their own header
                  stays off on every platform - the Web nav shell (added
                  to AppShell) is their identification/navigation
                  affordance instead. */}
              <Stack.Screen name="home" options={{ headerShown: false }} />
              <Stack.Screen name="discover" options={{ headerShown: false }} />
              <Stack.Screen name="favorites" options={{ headerShown: false }} />
              <Stack.Screen name="profile" options={{ headerShown: false, title: 'マイページ' }} />
              <Stack.Screen name="discover-organizations" options={{ title: '団体を探す' }} />
              <Stack.Screen name="discover-productions" options={{ title: '公演・活動を探す' }} />
              <Stack.Screen name="viewing-history" options={{ title: '観劇履歴' }} />
              <Stack.Screen name="participating-productions" options={{ title: '参加している公演・活動' }} />
              <Stack.Screen name="production/[id]" options={{ headerShown: false }} />
              {/* StageArt Web First Phase 2: AppShell already renders its
                  own branded logo header on every one of these screens
                  (create/publish flows and the public /o/* pages), so
                  the Stack's own header stays off here too, matching
                  home/discover/favorites/profile above. */}
              <Stack.Screen name="organizations/create" options={{ headerShown: false }} />
              <Stack.Screen name="organizations/[id]/productions/create" options={{ headerShown: false }} />
              {/* Public Page Architecture phase
                  (docs/03-PublicPageURLAndPublicationSchedule.md): moved
                  to the URL root, matching stageart.top's intended path
                  shape (`/{organization-slug}`,
                  `/{organization-slug}/{production-slug}`) - see
                  OrganizationSlug.php's RESERVED list for why this is
                  safe (every real top-level route name below is
                  reserved and can never collide with a real slug). */}
              <Stack.Screen name="[organizationSlug]/index" options={{ headerShown: false }} />
              <Stack.Screen name="[organizationSlug]/[productionSlug]" options={{ headerShown: false }} />
              {/* /o/{slug} kept as a redirect-only route for backward
                  compatibility with any pre-existing link. */}
              <Stack.Screen name="o/[organizationSlug]/index" options={{ headerShown: false }} />
              <Stack.Screen name="o/[organizationSlug]/[productionSlug]" options={{ headerShown: false }} />
              {/* StageArt Web β版: Join Key発行 + 参加申請承認 (団体・公演
                  管理者向け) と、参加コード入力・検索経由の所属申請 (一般
                  利用者向け) - AppShell自身のヘッダーを使うため、ここでも
                  headerShownはfalseのまま。 */}
              <Stack.Screen name="organizations/[id]/invite" options={{ headerShown: false }} />
              <Stack.Screen name="production-invite/[id]" options={{ headerShown: false }} />
              <Stack.Screen name="join" options={{ headerShown: false }} />
            </Stack>
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    </ThemeProvider>
  );
}
