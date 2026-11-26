import { returnToForUnauthenticated, safeReturnTo } from './returnTo';

/** PR #7 R14-B stage 9 (design doc §10 「ログイン」, §14.2, R13 Medium). */
describe('safeReturnTo', () => {
  it('accepts an in-app path', () => {
    expect(safeReturnTo('/participant-invitations')).toBe('/participant-invitations');
    expect(safeReturnTo('/participant-invitations/abc')).toBe('/participant-invitations/abc');
    expect(safeReturnTo('/home?tab=1')).toBe('/home?tab=1');
  });

  it.each([
    ['another site', 'https://evil.example.com/x'],
    ['protocol-relative', '//evil.example.com/x'],
    ['backslash trick', '/\\evil.example.com'],
    ['scheme without slashes', 'javascript:alert(1)'],
    ['a colon in the path', '/x:y'],
    ['relative', 'home'],
    ['control character', '/home\n'],
    ['empty', ''],
    ['too long', '/' + 'a'.repeat(600)],
    ['not a string', 42],
    ['undefined', undefined],
    ['an auth screen', '/login'],
    ['registration', '/register?token=x'],
  ])('rejects %s', (_label, value) => {
    expect(safeReturnTo(value)).toBeNull();
  });
});

describe('returnToForUnauthenticated', () => {
  it('only returns the invitation paths', () => {
    expect(returnToForUnauthenticated('/participant-invitations')).toBe('/participant-invitations');
    expect(returnToForUnauthenticated('/participant-invitations/abc')).toBe('/participant-invitations/abc');
    expect(returnToForUnauthenticated('/home')).toBeNull();
    expect(returnToForUnauthenticated('/participant-invitationsX')).toBeNull();
  });
});
