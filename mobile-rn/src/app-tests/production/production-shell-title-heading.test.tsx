import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, productionWithTitleHeading } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Phase 7.1 (ProductionTitleHeadingPolicy.md): the Shell header shows
 * `title_heading` above `name` as two separate Text nodes, never
 * concatenated - proven here by asserting both testIDs independently
 * render their own exact text.
 */
describe('Production Shell: title heading', () => {
  it('shows title_heading above the Production title when set', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionWithTitleHeading }]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    await waitFor(() => expect(screen.getByTestId('production-title')).toHaveTextContent('○○公演2026'));
    expect(screen.getByTestId('production-title-heading')).toHaveTextContent('旗揚げ公演');
  });
});
