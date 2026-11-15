import { CreateRequestKey, generateIdempotencyKey } from './createRequestKey';

/** PR #7 R12 (docs/20-RehearsalManagementScreen.md §26). */
describe('CreateRequestKey', () => {
  function keys() {
    let n = 0;
    return new CreateRequestKey(() => `k${++n}`);
  }

  it('reuses the key for the same content (a resend after a network error)', () => {
    const key = keys();
    expect(key.keyFor({ title: 'a', persons: ['p1'] })).toBe('k1');
    expect(key.keyFor({ title: 'a', persons: ['p1'] })).toBe('k1');
  });

  it('issues a new key when the content changes', () => {
    const key = keys();
    expect(key.keyFor({ title: 'a' })).toBe('k1');
    expect(key.keyFor({ title: 'b' })).toBe('k2');
    expect(key.keyFor({ title: 'a' })).toBe('k3');
  });

  it('issues a new key for the next create after a success', () => {
    const key = keys();
    expect(key.keyFor({ title: 'a' })).toBe('k1');
    key.reset();
    expect(key.keyFor({ title: 'a' })).toBe('k2');
  });

  it('generates keys the Backend accepts (1-100 of letters, digits, ._:-)', () => {
    const a = generateIdempotencyKey();
    const b = generateIdempotencyKey();
    expect(a).toMatch(/^[A-Za-z0-9._:-]{1,100}$/);
    expect(a).not.toBe(b);
  });
});
