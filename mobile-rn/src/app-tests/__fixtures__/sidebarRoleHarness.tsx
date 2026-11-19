import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react-native';
import { Text } from 'react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { WebSidebarNav } from '@/components/chrome/WebSidebarNav';
import { useOrganizations } from '@/features/organization/useOrganizations';

import { mockFetchRoutes } from './homeFixtures';

/**
 * Shared harness for the web-sidebar-organization-menu-* tests (one it()
 * per file, this codebase's convention for query-driven screen tests).
 * Each test file still declares its own jest.mock()s, since those are
 * hoisted per file.
 */
export const ORG_MENU_ITEMS = [
  'organization-info',
  'organization-members',
  'organization-members-owner-transfer',
  'organization-productions',
  'organization-accounting',
];
export const ORG_MENU_LABELS = ['団体情報', 'メンバー管理', '代表者交代', '公演・活動一覧', '会計管理'];

export const shownOrganizationMenuItems = () =>
  ORG_MENU_ITEMS.filter((key) => screen.queryByTestId(`web-chrome-nav-${key}`) !== null);

/** Rendered once the Person's Organizations have loaded, so a test can tell "nothing listed" from "not loaded yet". */
function OrganizationsLoaded() {
  const { data } = useOrganizations();
  return data ? <Text testID="organizations-loaded">loaded</Text> : null;
}

export function renderSidebarAs(organizations: unknown[]) {
  mockFetchRoutes([
    { test: (url) => url.endsWith('/organizations'), status: 200, body: organizations },
    { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
    { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
  ]);
  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <WebSidebarNav>
          <Text>content</Text>
          <OrganizationsLoaded />
        </WebSidebarNav>
      </AuthProvider>
    </QueryClientProvider>
  );
}
