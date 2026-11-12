import { DEV_API_BASE_URL, PROD_API_BASE_URL, resolveApiBaseUrl } from './environment';

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

  it.each(['unknown', 'staging', 'test', '', 'preview', 'undefined'])(
    'throws for the unrecognized environment value %p, rather than silently falling back to Development or Production',
    (env) => {
      expect(() => resolveApiBaseUrl(env)).toThrow(`Unsupported StageArt environment: ${env}`);
    }
  );
});
