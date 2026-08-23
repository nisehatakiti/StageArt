import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** §20's required check: "予定 / 会計 / マイページ が存在すること" for
 * the Production Shell (Phase 5.0/5.1's Navigation Map). Starts already
 * "authenticated" (mocked SecureStore returns stored credentials) since
 * this Shell is only reachable post-login. */
describe('Production Shell', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
  });

  it('shows all 3 fixed Blueprint tabs and none other', async () => {
    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    await waitFor(() => expect(screen.getAllByText('予定').length).toBeGreaterThan(0));
    expect(screen.getAllByText('会計').length).toBeGreaterThan(0);
    expect(screen.getAllByText('マイページ').length).toBeGreaterThan(0);

    // "and none other": the fixed 3-tab set must not gain a 4th tab
    // (e.g. an "Organization"/"Production" tab - explicitly prohibited
    // by FrontendArchitecture.md, see Phase 5.0/5.1's Navigation Map).
    expect(screen.queryByText('団体')).toBeNull();
    expect(screen.queryByText('公演')).toBeNull();
  });
});
