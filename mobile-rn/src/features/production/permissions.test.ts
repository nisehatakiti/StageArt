import {
  canManageAccounting,
  canManageJoinKeys,
  canManageParticipants,
  canManageProductionDelegates,
  canManageProductionOperations,
  delegateRolesOf,
  isPrimaryManagerOf,
  isProxyManager,
  productionResponsibilities,
} from './permissions';

type Fields = {
  is_primary_manager: boolean;
  delegate_role?: string | null;
  delegate_roles?: string[];
  is_proxy_manager?: boolean;
  can_manage_production_operations?: boolean;
  can_manage_accounting?: boolean;
  can_manage_production_delegates?: boolean;
};

function as(fields: Partial<Fields>): Fields {
  return { is_primary_manager: false, delegate_role: null, ...fields };
}

/** Every decision for one caller, as the screens use them. */
function decisions(production: Fields) {
  return {
    operations: canManageProductionOperations(production),
    joinKeys: canManageJoinKeys(production),
    participants: canManageParticipants(production),
    accounting: canManageAccounting(production),
    delegates: canManageProductionDelegates(production),
    primaryOnly: isPrimaryManagerOf(production),
  };
}

/**
 * PR #8 統合の権限一覧 (2026-11-24). Each case is checked twice: with the
 * API's own flags and with only `is_primary_manager` + `delegate_roles`
 * (an older response), which must derive the same answer.
 */
const CASES: [string, Fields, ReturnType<typeof decisions>][] = [
  [
    'PrimaryManager',
    as({ is_primary_manager: true, delegate_roles: [] }),
    { operations: true, joinKeys: true, participants: true, accounting: true, delegates: true, primaryOnly: true },
  ],
  [
    '公演代理人',
    as({ delegate_roles: ['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER'] }),
    { operations: true, joinKeys: true, participants: true, accounting: true, delegates: true, primaryOnly: false },
  ],
  [
    '会計担当',
    as({ delegate_roles: ['ACCOUNTING_MANAGER'] }),
    { operations: false, joinKeys: false, participants: false, accounting: true, delegates: false, primaryOnly: false },
  ],
  [
    '稽古担当',
    as({ delegate_roles: ['REHEARSAL_MANAGER'] }),
    { operations: false, joinKeys: false, participants: false, accounting: false, delegates: false, primaryOnly: false },
  ],
  [
    '参加者担当のみ',
    as({ delegate_roles: ['PARTICIPANT_MANAGER'] }),
    { operations: false, joinKeys: false, participants: true, accounting: false, delegates: false, primaryOnly: false },
  ],
  [
    '受付担当',
    as({ delegate_roles: ['CHECKIN_MANAGER'] }),
    { operations: false, joinKeys: false, participants: false, accounting: false, delegates: false, primaryOnly: false },
  ],
  [
    '関係なし',
    as({ delegate_roles: [] }),
    { operations: false, joinKeys: false, participants: false, accounting: false, delegates: false, primaryOnly: false },
  ],
];

function withServerFlags(production: Fields): Fields {
  const roles = production.delegate_roles ?? [];
  const proxy = roles.includes('PARTICIPANT_MANAGER') && roles.includes('REHEARSAL_MANAGER');
  const operations = production.is_primary_manager || proxy;
  return {
    ...production,
    is_proxy_manager: proxy,
    can_manage_production_operations: operations,
    can_manage_accounting: operations || roles.includes('ACCOUNTING_MANAGER'),
    can_manage_production_delegates: operations,
  };
}

describe('Production 担当区分 helpers (PR #8 統合)', () => {
  it.each(CASES)('%s: derived from delegate_roles', (_label, production, expected) => {
    expect(decisions(production)).toEqual(expected);
  });

  it.each(CASES)('%s: with the API flags', (_label, production, expected) => {
    expect(decisions(withServerFlags(production))).toEqual(expected);
  });

  it('the API flags win over delegate_roles', () => {
    const production = as({
      delegate_roles: ['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER'],
      is_proxy_manager: false,
      can_manage_production_operations: false,
      can_manage_accounting: false,
      can_manage_production_delegates: false,
    });
    expect(isProxyManager(production)).toBe(false);
    expect(canManageProductionOperations(production)).toBe(false);
    expect(canManageAccounting(production)).toBe(false);
    expect(canManageProductionDelegates(production)).toBe(false);
  });

  it('role order does not matter, and several Roles add up', () => {
    const a = as({ delegate_roles: ['REHEARSAL_MANAGER', 'ACCOUNTING_MANAGER', 'PARTICIPANT_MANAGER'] });
    const b = as({ delegate_roles: ['PARTICIPANT_MANAGER', 'ACCOUNTING_MANAGER', 'REHEARSAL_MANAGER'] });
    expect(decisions(a)).toEqual(decisions(b));
    expect(isProxyManager(a)).toBe(true);
    expect(productionResponsibilities(a)).toEqual(['PROXY_MANAGER', 'ACCOUNTING_MANAGER']);
  });

  it('falls back to the legacy delegate_role when delegate_roles is missing', () => {
    const legacy = as({ delegate_role: 'ACCOUNTING_MANAGER', delegate_roles: undefined });
    expect(delegateRolesOf(legacy)).toEqual(['ACCOUNTING_MANAGER']);
    expect(canManageAccounting(legacy)).toBe(true);
    expect(canManageProductionOperations(legacy)).toBe(false);
  });

  it('no Production: nothing', () => {
    expect(canManageProductionOperations(undefined)).toBe(false);
    expect(canManageAccounting(null)).toBe(false);
    expect(productionResponsibilities(undefined)).toEqual([]);
  });

  it('labels each 担当区分', () => {
    expect(productionResponsibilities(as({ is_primary_manager: true }))).toEqual(['PRIMARY_MANAGER']);
    expect(productionResponsibilities(as({ delegate_roles: ['REHEARSAL_MANAGER'] }))).toEqual(['REHEARSAL_MANAGER']);
    expect(productionResponsibilities(as({ delegate_roles: ['ACCOUNTING_MANAGER'] }))).toEqual(['ACCOUNTING_MANAGER']);
  });
});
