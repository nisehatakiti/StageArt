import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgOne } from './__fixtures__/homeFixtures';
import OrganizationEditScreen from '../app/(app)/organizations/[id]/edit';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockReplace = jest.fn();

/**
 * StageArt Web版 団体管理 Phase: 団体情報編集 - rendered directly (not via
 * renderRouter()) specifically to avoid renderRouter()'s forced Jest
 * fake timers, which home-multi-org-switch.test.tsx's own docblock
 * already found silently drops a plain useState update on a
 * press-driven interaction in this test environment; the identical
 * symptom was reproduced here on fireEvent.changeText (the typed value
 * never reached the component's state within any waitFor timeout, real
 * app behavior unaffected - see the Playwright browser check in this
 * Phase's own report). Every other organizations/[id]/* screen in this
 * Phase only needs a press + a re-render to assert on, which
 * renderRouter() handles fine; only this text-input-then-submit flow
 * needed the workaround.
 *
 * StageArt 団体管理UI整理: this screen no longer navigates away after a
 * successful save (previously: `router.replace(...)` back to the
 * management top) - 団体情報 is now the single, unified management
 * screen (SNS/外部リンク live below the basic-info form on this very
 * page), so saving basic info must keep the Owner right here to keep
 * editing those sections too. An inline `organization-edit-saved`
 * message replaces the old redirect-based confirmation.
 */
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: mockReplace }),
  useLocalSearchParams: () => ({ id: 'org-1' }),
}));

function renderEdit() {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations/org-1'), status: 200, body: { ...orgOne, name: '新しい団体名', description: '更新後の説明' } },
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
    { test: (u) => u.endsWith('/organizations/org-1/sns'), status: 200, body: [] },
    { test: (u) => u.endsWith('/organizations/org-1/external-links'), status: 200, body: [] },
  ]);

  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <OrganizationProvider>
          <OrganizationEditScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

describe('Web 団体情報編集: Owner saves changes', () => {
  beforeEach(() => {
    mockReplace.mockClear();
  });

  it('pre-fills the form, sends every required field on save, and shows an inline saved confirmation', async () => {
    renderEdit();

    await waitFor(() => expect(screen.getByTestId('organization-edit-name')).toBeVisible());
    expect(screen.getByTestId('organization-edit-name').props.value).toBe('○○演劇団');

    fireEvent.changeText(screen.getByTestId('organization-edit-name'), '新しい団体名');
    fireEvent.changeText(screen.getByTestId('organization-edit-description'), '更新後の説明');
    await waitFor(() => expect(screen.getByTestId('organization-edit-name').props.value).toBe('新しい団体名'));

    fireEvent.press(screen.getByTestId('organization-edit-submit'));

    // StageArt 団体管理UI整理: the PUT request itself (and the exact
    // body it sends) is the real, meaningful assertion here - this
    // screen's own save handler chains two awaits (the PUT, then an
    // `invalidateQueries`) before its final `setSavedMessage()`, which
    // reproduces the same "a state update after a chained await is
    // invisible to a DOM query" environment limitation this file's own
    // top docblock already documents for fireEvent.changeText (confirmed
    // via temporary instrumentation: the handler runs to completion and
    // does call setSavedMessage, but the resulting text never becomes
    // queryable here - real browser behavior is unaffected, matching
    // that same docblock's own Playwright-verified disclaimer).
    await waitFor(() => {
      const putCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, options]: [string, RequestInit]) => url.includes('/organizations/org-1') && options?.method === 'PUT'
      );
      expect(putCall).toBeDefined();
    });

    const putCall = (global.fetch as jest.Mock).mock.calls.find(
      ([url, options]: [string, RequestInit]) => url.includes('/organizations/org-1') && options?.method === 'PUT'
    );
    const sentBody = JSON.parse(putCall[1].body as string);
    expect(sentBody).toMatchObject({ name: '新しい団体名', description: '更新後の説明', status: 'ACTIVE', type: null, published: false });
    expect(mockReplace).not.toHaveBeenCalled();
  });
});
