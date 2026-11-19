import { canViewProductionAccounting } from './permissions';

const role = (...roles: string[]) => ({ is_primary_manager: false, delegate_roles: roles });

/** 会計 follows Accounting.Update on the server: PrimaryManager or ACCOUNTING_MANAGER only. */
describe('canViewProductionAccounting', () => {
  it('opens 会計 for the PrimaryManager and an ACCOUNTING_MANAGER', () => {
    expect(canViewProductionAccounting({ is_primary_manager: true, delegate_roles: [] })).toBe(true);
    expect(canViewProductionAccounting(role('ACCOUNTING_MANAGER'))).toBe(true);
    expect(canViewProductionAccounting(role('TICKET_MANAGER', 'ACCOUNTING_MANAGER'))).toBe(true);
  });

  it('keeps 会計 closed for every other Role, a participant and an unloaded Production', () => {
    for (const other of ['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER', 'PERFORMANCE_MANAGER', 'TICKET_MANAGER', 'CHECKIN_MANAGER', 'QUESTIONNAIRE_MANAGER']) {
      expect(canViewProductionAccounting(role(other))).toBe(false);
    }
    expect(canViewProductionAccounting(role())).toBe(false);
    expect(canViewProductionAccounting(undefined)).toBe(false);
    expect(canViewProductionAccounting(null)).toBe(false);
  });
});
