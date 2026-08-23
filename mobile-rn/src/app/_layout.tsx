import { QueryClientProvider } from '@tanstack/react-query';
import { DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';
import { useColorScheme } from 'react-native';

import { createQueryClient } from '@/api/queryClient';
import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

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
            <Stack screenOptions={{ headerShown: false }}>
              <Stack.Screen name="index" />
              <Stack.Screen name="login" />
              <Stack.Screen name="register" />
              <Stack.Screen name="forgot-password" />
              <Stack.Screen name="reset-password" />
              <Stack.Screen name="registration-pending" />
              <Stack.Screen name="verify-email" />
              <Stack.Screen name="set-name" />
              <Stack.Screen name="home" />
              <Stack.Screen name="profile" />
              <Stack.Screen name="discover-organizations" />
              <Stack.Screen name="discover-productions" />
              <Stack.Screen name="viewing-history" />
              <Stack.Screen name="participating-productions" />
              <Stack.Screen name="production/[id]" />
            </Stack>
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    </ThemeProvider>
  );
}
