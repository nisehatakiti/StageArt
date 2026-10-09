import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';
import ProductionEditScreen from '../app/(app)/productions/[id]/edit';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockReplace = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: mockReplace }),
  useLocalSearchParams: () => ({ id: 'prod-1' }),
}));

/**
 * StageArt 公開ページ Phase: split out of web-production-edit-save.test.tsx
 * - a later it() in that same describe block was found to fail to find
 * even baseline elements (`production-edit-name`), reproduced in
 * isolation (that file alone), matching this codebase's own documented
 * per-file test-isolation issue (see home-multi-org-switch.test.tsx's
 * own docblock) rather than a real product bug.
 *
 * Regression coverage for a real bug found this Phase: edit.tsx's
 * handleSubmit always sent `published` but never `publishedAt`, so
 * UpdateProductionUseCase's `$production->publish($at ?? now())` reset
 * published_at to the current time on every unrelated resave of an
 * already-published Production - breaking the same "preserve the
 * existing timestamp" invariant every sibling *PublishedAt field in this
 * same handleSubmit already follows.
 */
describe('Web 公演情報編集: published_at保持', () => {
  it('resends the existing published_at unchanged when saving an already-published Production (does not bump it to "now")', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ ...orgOne, slug: 'kujira' }] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      {
        test: (u) => u.endsWith('/productions/prod-1'),
        status: 200,
        body: { ...productionOne, slug: 'kappa-homerun', published_at: '2026-01-01T00:00:00+09:00' },
      },
    ]);

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <OrganizationProvider>
            <ProductionEditScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('production-edit-name')).toBeVisible());
    fireEvent.changeText(screen.getByTestId('production-edit-name'), '別の公演名');
    await waitFor(() => expect(screen.getByTestId('production-edit-name').props.value).toBe('別の公演名'));

    fireEvent.press(screen.getByTestId('production-edit-submit'));

    await waitFor(() => expect(mockReplace).toHaveBeenCalled());

    const putCall = (global.fetch as jest.Mock).mock.calls.find(
      ([url, options]: [string, RequestInit]) => url.includes('/productions/prod-1') && options?.method === 'PUT'
    );
    const sentBody = JSON.parse(putCall[1].body as string);
    expect(sentBody.published).toBe(true);
    expect(sentBody.published_at).toBe('2026-01-01T00:00:00+09:00');
  });
});
