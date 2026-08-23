import type { ProductionAccountingSummary } from '@/types/api';

import { formatCurrency } from './currency';

export type AccountingRowViewModel = {
  /** Human-readable value, already currency-formatted or a status label. */
  display: string;
  /** True when `display` is a real amount (not a status label like "未設定"). */
  hasValue: boolean;
};

export type ProductionAccountingViewModel = {
  budget: AccountingRowViewModel;
  actual: AccountingRowViewModel;
  variance: AccountingRowViewModel;
};

/**
 * §4/§11: Budget "not set" (has_budget=false) and Budget "¥0" (has_budget
 * =true, total_budget=0) are different states and must render
 * differently - this is the entire point of the has_budget/has_actual
 * flags existing on the API at all. Never collapse them into a single
 * "未実装"/"¥0" label the way the Phase 5.4 placeholder did.
 *
 * "実績なし" for has_actual=false is a disclosed UI-copy judgment call:
 * no Blueprint document defines a canonical label for "no POSTED Journal
 * Entry yet" (AccountingConsistencyPolicy.md and JournalEntryConsistency
 * Policy.md describe the underlying rule but not client-facing copy).
 * "未発生" was considered but rejected as overclaiming - it would assert
 * no financial activity has occurred, when the API can only tell us no
 * POSTED entry exists yet (a DRAFT entry may already be sitting there).
 * "実績なし" ("no Actual recorded") makes the weaker, accurate claim.
 *
 * Variance is never recomputed client-side (§7: "MobileではVarianceを
 * 再計算しない") - `total_variance` is displayed exactly as the API
 * returns it, including its null-when-no-budget case.
 */
export function buildAccountingViewModel(summary: ProductionAccountingSummary): ProductionAccountingViewModel {
  return {
    budget: summary.has_budget
      ? { display: formatCurrency(summary.total_budget as number, summary.currency), hasValue: true }
      : { display: '未設定', hasValue: false },
    actual: summary.has_actual
      ? { display: formatCurrency(summary.total_actual, summary.currency), hasValue: true }
      : { display: '実績なし', hasValue: false },
    variance:
      summary.total_variance !== null
        ? { display: formatCurrency(summary.total_variance, summary.currency), hasValue: true }
        : { display: '算出できません', hasValue: false },
  };
}
