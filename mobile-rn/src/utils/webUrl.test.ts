import { isHttpUrl } from './webUrl';

/**
 * PR #7 R14-A (F9): the same cases as the Backend's WebUrlTest.
 */
describe('isHttpUrl', () => {
  it.each([
    'https://example.com',
    'http://example.com/path?a=1&b=2#top',
    'HTTPS://EXAMPLE.COM/',
    'https://example.com:8443/x',
    'https://example.com/%E5%85%AC%E6%BC%94',
  ])('accepts %s', (url) => {
    expect(isHttpUrl(url)).toBe(true);
  });

  it.each([
    'javascript://x/%0Aalert(document.domain)',
    'javascript:alert(1)',
    'JAVASCRIPT://x/%0Aalert(1)',
    'data://text/html,abc',
    'data:text/html,<script>alert(1)</script>',
    'vbscript://x/msgbox(1)',
    'file:///etc/passwd',
    'intent://x#Intent;end',
    'ftp://example.com/file',
    'mailto:someone@example.com',
    '//example.com/x',
    '/wp-content/uploads/logo.png',
    'java\tscript://x/%0Aalert(1)',
    'https://exa\tmple.com',
    ' https://example.com',
    'https://example .com',
    '%6Aavascript://x/%0Aalert(1)',
    'https://',
    'http:example.com',
    '',
  ])('refuses %j', (url) => {
    expect(isHttpUrl(url)).toBe(false);
  });

  it('refuses null and undefined', () => {
    expect(isHttpUrl(null)).toBe(false);
    expect(isHttpUrl(undefined)).toBe(false);
  });
});
