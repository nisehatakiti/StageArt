import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('Production Shell: no title heading', () => {
  it('shows only the Production title when title_heading is null, with no placeholder', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne }]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    await waitFor(() => expect(screen.getByTestId('production-title')).toHaveTextContent('○○公演2026'));
    expect(screen.queryByTestId('production-title-heading')).toBeNull();
  });
});
