import { resolveSiteBuild, SITE_BUILD_APP, SITE_BUILD_PUBLIC } from './site-build-env';

/**
 * Public-site / application build separation: an unrecognized value must
 * fail loudly at config time, and the public-site build must never be
 * used for a native (EAS) build.
 */
describe('resolveSiteBuild', () => {
  it.each([undefined, '', 'app'])('%p is the application build (existing behavior)', (value) => {
    expect(resolveSiteBuild(value)).toBe(SITE_BUILD_APP);
  });

  it('"public" is the public-site build', () => {
    expect(resolveSiteBuild('public')).toBe(SITE_BUILD_PUBLIC);
  });

  it.each(['Public', 'pubic', 'true', 'dummy', 'production'])('rejects an unknown value %p', (value) => {
    expect(() => resolveSiteBuild(value)).toThrow(/Unsupported EXPO_PUBLIC_STAGEART_SITE/);
  });

  it.each(['ios', 'android'])('refuses the public-site build for an EAS %s build', (platform) => {
    expect(() => resolveSiteBuild('public', { easBuildPlatform: platform })).toThrow(/Web-only/);
  });

  it('allows the application build for an EAS build', () => {
    expect(resolveSiteBuild(undefined, { easBuildPlatform: 'ios' })).toBe(SITE_BUILD_APP);
  });
});
