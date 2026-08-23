import { formatCurrency } from './currency';

describe('formatCurrency', () => {
  it('formats a positive JPY amount with the plain yen sign and grouping', () => {
    expect(formatCurrency(300000, 'JPY')).toBe('¥300,000');
  });

  it('formats a negative amount with a leading minus before the symbol', () => {
    expect(formatCurrency(-480000, 'JPY')).toBe('-¥480,000');
  });

  it('formats zero without a sign', () => {
    expect(formatCurrency(0, 'JPY')).toBe('¥0');
  });

  it('formats a known non-JPY currency using its own symbol', () => {
    expect(formatCurrency(1000, 'USD')).toBe('$1,000');
  });

  it('falls back to the raw currency code for an unrecognized currency', () => {
    expect(formatCurrency(1000, 'EUR')).toBe('1,000 EUR');
  });
});
