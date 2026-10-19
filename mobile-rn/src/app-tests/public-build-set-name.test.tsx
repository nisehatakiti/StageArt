import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('@/config/site-build', () => ({ IS_PUBLIC_SITE_BUILD: true }));

/** Public-site build: /set-name (an account screen) renders no form. */
describe('Public-site build: /set-name', () => {
  it('shows the public landing page, never the name form', async () => {
    global.fetch = jest.fn();
    renderRouter('src/app', { initialUrl: '/set-name' });

    await waitFor(() => expect(screen.getByTestId('stageart-landing-headline')).toBeVisible());
    expect(screen.queryByTestId('set-name-family-name')).toBeNull();
    expect(global.fetch).not.toHaveBeenCalled();
  });
});
