import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('@/hooks/use-site-host', () => ({
  useSiteHostname: () => 'dev.stageart.top',
  useIsPublicSite: () => false,
}));

/** 公開サイトのログイン導線指示書 §2.3: dev.stageart.top keeps the
 * application entry - an unauthenticated visitor to / reaches the login
 * form. */
describe('Application host (dev): /', () => {
  it('redirects an unauthenticated visitor to the login form', async () => {
    global.fetch = jest.fn();
    renderRouter('src/app', { initialUrl: '/' });

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
    expect(screen.getByTestId('login-submit')).toBeVisible();
    expect(screen.queryByTestId('stageart-landing-headline')).toBeNull();
  });
});
