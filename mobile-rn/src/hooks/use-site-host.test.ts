import { renderHook } from '@testing-library/react-native';
import { Platform } from 'react-native';

import { useIsPublicSite, useSiteHostname } from './use-site-host';

describe('useIsPublicSite', () => {
  const originalWindow = (global as { window?: unknown }).window;

  function setHostname(hostname: string) {
    (global as { window?: unknown }).window = { ...(originalWindow as object), location: { hostname } };
  }

  afterEach(() => {
    (global as { window?: unknown }).window = originalWindow;
    jest.restoreAllMocks();
  });

  it.each(['dummy.stageart.top', 'stageart.top', 'www.stageart.top'])('is true on Web served from %s', async (hostname) => {
    jest.replaceProperty(Platform, 'OS', 'web');
    setHostname(hostname);

    const { result } = await renderHook(() => ({ hostname: useSiteHostname(), isPublicSite: useIsPublicSite() }));

    expect(result.current).toEqual({ hostname, isPublicSite: true });
  });

  it.each(['dev.stageart.top', 'app.stageart.top', 'localhost'])('is false on Web served from %s', async (hostname) => {
    jest.replaceProperty(Platform, 'OS', 'web');
    setHostname(hostname);

    const { result } = await renderHook(() => useIsPublicSite());

    expect(result.current).toBe(false);
  });

  it('is never true on native, whatever the environment reports', async () => {
    jest.replaceProperty(Platform, 'OS', 'ios');
    setHostname('dummy.stageart.top');

    const { result } = await renderHook(() => ({ hostname: useSiteHostname(), isPublicSite: useIsPublicSite() }));

    expect(result.current).toEqual({ hostname: '', isPublicSite: false });
  });
});
