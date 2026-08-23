/**
 * §12: Currency is never hardcoded to JPY - it comes from the API
 * response (`ProductionAccountingSummary.currency`, Budget.md "Version
 * 1.0ではOrganizationの会計通貨を基本とする"). This formatter is keyed off
 * that value so a future non-JPY Organization renders correctly without
 * any change to the screen itself.
 *
 * Deliberately does not delegate the symbol to Intl.NumberFormat's
 * `style: 'currency'`: `Intl.NumberFormat('ja-JP', { currency: 'JPY' })`
 * renders the fullwidth yen sign U+FFE5 ("￥"), not the plain yen sign
 * U+00A5 ("¥") every StageArt Blueprint document itself uses in its own
 * worked examples (Budget.md, AccountingConsistencyPolicy.md). The
 * symbol map below is the explicit, disclosed choice to keep that
 * consistent instead of inheriting whatever a given locale's ICU data
 * happens to pick.
 */
const SYMBOL_BY_CURRENCY: Record<string, string> = {
  JPY: '¥',
  USD: '$',
};

export function formatCurrency(amount: number, currencyCode: string): string {
  const symbol = SYMBOL_BY_CURRENCY[currencyCode];
  const sign = amount < 0 ? '-' : '';
  const magnitude = Math.abs(amount).toLocaleString('en-US');

  if (symbol) {
    return `${sign}${symbol}${magnitude}`;
  }

  // Unrecognized ISO 4217 code: fall back to a plain, still-honest
  // number with the currency code spelled out, rather than guessing a
  // symbol.
  return `${sign}${magnitude} ${currencyCode}`;
}
