import {
  clearEmailHandoff,
  clearLoginEmailHandoff,
  peekEmailHandoff,
  peekLoginEmailHandoff,
  setEmailHandoff,
  setLoginEmailHandoff,
} from './loginEmailHandoff';

describe('loginEmailHandoff', () => {
  afterEach(() => {
    clearLoginEmailHandoff();
    clearEmailHandoff('register');
  });

  it('keeps the login and register slots apart', () => {
    setEmailHandoff('register', 'new@example.com');
    expect(peekLoginEmailHandoff()).toBeNull();
    expect(peekEmailHandoff('register')).toBe('new@example.com');

    setLoginEmailHandoff('member@example.com');
    clearEmailHandoff('register');
    expect(peekEmailHandoff('register')).toBeNull();
    expect(peekLoginEmailHandoff()).toBe('member@example.com');
  });

  it('starts empty', () => {
    expect(peekLoginEmailHandoff()).toBeNull();
  });

  it('keeps a trimmed address until cleared, and peeking does not clear it', () => {
    setLoginEmailHandoff('  member@example.com ');
    expect(peekLoginEmailHandoff()).toBe('member@example.com');
    expect(peekLoginEmailHandoff()).toBe('member@example.com');

    clearLoginEmailHandoff();
    expect(peekLoginEmailHandoff()).toBeNull();
  });

  it('treats an empty or missing address as nothing to hand over', () => {
    setLoginEmailHandoff('member@example.com');
    setLoginEmailHandoff('   ');
    expect(peekLoginEmailHandoff()).toBeNull();

    setLoginEmailHandoff(undefined);
    expect(peekLoginEmailHandoff()).toBeNull();
  });
});
