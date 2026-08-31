import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgOne } from './__fixtures__/homeFixtures';
import OrganizationEditScreen from '../app/organizations/[id]/edit';

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
 */
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: mockReplace }),
  useLocalSearchParams: () => ({ id: 'org-1' }),
}));

function renderEdit() {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations/org-1'), status: 200, body: { ...orgOne, name: '新しい団体名', description: '更新後の説明' } },
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
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

  it('pre-fills the form, sends every required field on save, and redirects to the management top with a saved confirmation', async () => {
    renderEdit();

    await waitFor(() => expect(screen.getByTestId('organization-edit-name')).toBeVisible());
    expect(screen.getByTestId('organization-edit-name').props.value).toBe('○○演劇団');

    fireEvent.changeText(screen.getByTestId('organization-edit-name'), '新しい団体名');
    fireEvent.changeText(screen.getByTestId('organization-edit-description'), '更新後の説明');
    await waitFor(() => expect(screen.getByTestId('organization-edit-name').props.value).toBe('新しい団体名'));

    fireEvent.press(screen.getByTestId('organization-edit-submit'));

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith({ pathname: '/organizations/org-1', params: { saved: '1' } }));

    const putCall = (global.fetch as jest.Mock).mock.calls.find(
      ([url, options]: [string, RequestInit]) => url.includes('/organizations/org-1') && options?.method === 'PUT'
    );
    expect(putCall).toBeDefined();
    const sentBody = JSON.parse(putCall[1].body as string);
    expect(sentBody).toMatchObject({ name: '新しい団体名', description: '更新後の説明', status: 'ACTIVE', type: null });
  });
});
