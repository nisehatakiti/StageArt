import { getStageArtLoginUrl, getStageArtRootExperience, isPublicSiteHost } from './site-routing';

describe('StageArt root host routing', () => {
  it.each(['stageart.top', 'www.stageart.top', 'dummy.stageart.top'])('%s uses the public feature landing page', (hostname) => {
    expect(getStageArtRootExperience(hostname)).toBe('public-landing');
  });

  it.each(['app.stageart.top', 'dev.stageart.top', 'localhost', '127.0.0.1'])('%s uses the application entry flow', (hostname) => {
    expect(getStageArtRootExperience(hostname)).toBe('application');
  });

  it('sends production public-site visitors to the production application login', () => {
    expect(getStageArtLoginUrl('stageart.top')).toBe('https://app.stageart.top/login/');
  });

  it('sends development public-site visitors to the development application login', () => {
    expect(getStageArtLoginUrl('dummy.stageart.top')).toBe('https://dev.stageart.top/login/');
  });

  it('normalizes case and a trailing dot in hostnames', () => {
    expect(getStageArtRootExperience('DUMMY.STAGEART.TOP.')).toBe('public-landing');
    expect(getStageArtLoginUrl('DUMMY.STAGEART.TOP.')).toBe('https://dev.stageart.top/login/');
  });

  it.each(['stageart.top', 'www.stageart.top', 'dummy.stageart.top', 'DUMMY.STAGEART.TOP.'])('%s is a public-site host (no login screen)', (hostname) => {
    expect(isPublicSiteHost(hostname)).toBe(true);
  });

  it.each(['app.stageart.top', 'dev.stageart.top', 'localhost', ''])('%s is not a public-site host', (hostname) => {
    expect(isPublicSiteHost(hostname)).toBe(false);
  });
});
