import type { ProductionAccountingSummary } from '@/types/api';

import { buildAccountingViewModel } from './viewModel';

function summary(overrides: Partial<ProductionAccountingSummary> = {}): ProductionAccountingSummary {
  return {
    production_id: 'prod-1',
    has_budget: false,
    active_budget_id: null,
    total_budget: null,
    has_actual: false,
    total_actual: 0,
    total_variance: null,
    currency: 'JPY',
    ...overrides,
  };
}

describe('buildAccountingViewModel', () => {
  it('shows 未設定 (not "¥0") when has_budget is false', () => {
    const vm = buildAccountingViewModel(summary({ has_budget: false, total_budget: null }));

    expect(vm.budget.display).toBe('未設定');
    expect(vm.budget.hasValue).toBe(false);
  });

  it('shows ¥0 (not 未設定) when has_budget is true and the amount is zero', () => {
    const vm = buildAccountingViewModel(summary({ has_budget: true, total_budget: 0 }));

    expect(vm.budget.display).toBe('¥0');
    expect(vm.budget.hasValue).toBe(true);
  });

  it('shows the formatted amount when has_budget is true with a nonzero value', () => {
    const vm = buildAccountingViewModel(summary({ has_budget: true, total_budget: 300000 }));

    expect(vm.budget.display).toBe('¥300,000');
    expect(vm.budget.hasValue).toBe(true);
  });

  it('shows 実績なし (not "未実装" or "¥0") when has_actual is false', () => {
    const vm = buildAccountingViewModel(summary({ has_actual: false, total_actual: 0 }));

    expect(vm.actual.display).toBe('実績なし');
    expect(vm.actual.hasValue).toBe(false);
  });

  it('shows the formatted amount, including negative, when has_actual is true', () => {
    const positive = buildAccountingViewModel(summary({ has_actual: true, total_actual: 180000 }));
    expect(positive.actual.display).toBe('¥180,000');
    expect(positive.actual.hasValue).toBe(true);

    const negative = buildAccountingViewModel(summary({ has_actual: true, total_actual: -180000 }));
    expect(negative.actual.display).toBe('-¥180,000');
    expect(negative.actual.hasValue).toBe(true);
  });

  it('shows 算出できません when total_variance is null (no Budget to compare against)', () => {
    const vm = buildAccountingViewModel(summary({ has_budget: false, total_variance: null }));

    expect(vm.variance.display).toBe('算出できません');
    expect(vm.variance.hasValue).toBe(false);
  });

  it('shows the exact server-computed Variance without recomputing Actual - Budget client-side', () => {
    // Deliberately inconsistent with total_actual/total_budget to prove
    // this is not recalculated locally - the view model must render
    // whatever total_variance the API returned, verbatim.
    const vm = buildAccountingViewModel(
      summary({ has_budget: true, total_budget: 300000, has_actual: true, total_actual: -180000, total_variance: -480000 })
    );

    expect(vm.variance.display).toBe('-¥480,000');
    expect(vm.variance.hasValue).toBe(true);
  });

  it('formats using the currency the API returns, not a hardcoded JPY', () => {
    const vm = buildAccountingViewModel(
      summary({ has_budget: true, total_budget: 1000, currency: 'USD' })
    );

    expect(vm.budget.display).toBe('$1,000');
  });

  it('full normal fetch: budget set, actual set, variance computed', () => {
    const vm = buildAccountingViewModel(
      summary({
        has_budget: true,
        active_budget_id: 'budget-1',
        total_budget: 300000,
        has_actual: true,
        total_actual: -180000,
        total_variance: -480000,
        currency: 'JPY',
      })
    );

    expect(vm).toEqual({
      budget: { display: '¥300,000', hasValue: true },
      actual: { display: '-¥180,000', hasValue: true },
      variance: { display: '-¥480,000', hasValue: true },
    });
  });
});
