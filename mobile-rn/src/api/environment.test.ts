import {
  API_BASE_URLS,
  DEV_API_BASE_URL,
  PROD_API_BASE_URL,
  PUBLIC_WEB_BASE_URLS,
  VERIFICATION_API_BASE_URL,
  VERIFICATION_PUBLIC_WEB_BASE_URL,
  resolveApiBaseUrl,
  resolvePublicWebBaseUrl,
} from './environment';
import { DEV_PUBLIC_WEB_BASE_URL, PROD_PUBLIC_WEB_BASE_URL } from './config';

/**
 * StageArt Development/Production API separation: the one thing this
 * must guarantee is that a misconfigured/unrecognized environment
 * value can never silently resolve to either backend - especially
 * never to Development, which would be the most dangerous direction
 * (a broken Production build quietly talking to dev-api.stageart.top
 * instead of failing loudly).
 */
describe('resolveApiBaseUrl', () => {
  it('resolves "development" to the Development API', () => {
    expect(resolveApiBaseUrl('development')).toBe(DEV_API_BASE_URL);
    expect(DEV_API_BASE_URL).toBe('https://dev-api.stageart.top/wp-json/stageart/v1');
  });

  it('resolves "production" to the Production API', () => {
    expect(resolveApiBaseUrl('production')).toBe(PROD_API_BASE_URL);
    expect(PROD_API_BASE_URL).toBe('https://api.stageart.top/wp-json/stageart/v1');
  });

  it('resolves "verification" - and only that exact value - to the isolated verification API', () => {
    expect(resolveApiBaseUrl('verification')).toBe(VERIFICATION_API_BASE_URL);
    expect(VERIFICATION_API_BASE_URL).toBe('https://verify-api.stageart.top/wp-json/stageart/v1');
    expect(VERIFICATION_API_BASE_URL).not.toBe(DEV_API_BASE_URL);
    expect(VERIFICATION_API_BASE_URL).not.toBe(PROD_API_BASE_URL);
  });

  it('lists exactly the three environments with their URLs', () => {
    expect(API_BASE_URLS).toEqual({
      development: DEV_API_BASE_URL,
      production: PROD_API_BASE_URL,
      verification: VERIFICATION_API_BASE_URL,
    });
    expect(Object.isFrozen(API_BASE_URLS)).toBe(true);
  });

  it.each(['unknown', 'staging', 'test', '', 'preview', 'undefined', 'Verification', 'verify', ' verification'])(
    'throws for the unrecognized environment value %p, rather than silently falling back to Development or Production',
    (env) => {
      expect(() => resolveApiBaseUrl(env)).toThrow(`Unsupported StageArt environment: ${env}`);
    }
  );
});

describe('resolvePublicWebBaseUrl', () => {
  it('keeps the existing Development and Production public origins', () => {
    expect(resolvePublicWebBaseUrl('development')).toBe('https://dummy.stageart.top');
    expect(resolvePublicWebBaseUrl('production')).toBe('https://stageart.top');
    expect(resolvePublicWebBaseUrl('development')).toBe(DEV_PUBLIC_WEB_BASE_URL);
    expect(resolvePublicWebBaseUrl('production')).toBe(PROD_PUBLIC_WEB_BASE_URL);
  });

  it('resolves "verification" to verify.stageart.top', () => {
    expect(resolvePublicWebBaseUrl('verification')).toBe('https://verify.stageart.top');
    expect(VERIFICATION_PUBLIC_WEB_BASE_URL).toBe('https://verify.stageart.top');
    expect(PUBLIC_WEB_BASE_URLS).toEqual({
      development: 'https://dummy.stageart.top',
      production: 'https://stageart.top',
      verification: 'https://verify.stageart.top',
    });
    expect(Object.isFrozen(PUBLIC_WEB_BASE_URLS)).toBe(true);
  });

  it.each(['unknown', 'staging', '', 'preview', 'Verification', 'verify'])('throws for the unrecognized environment value %p', (env) => {
    expect(() => resolvePublicWebBaseUrl(env)).toThrow(`Unsupported StageArt environment: ${env}`);
  });
});
