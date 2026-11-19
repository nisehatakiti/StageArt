import {
  canEditProductionInfo,
  canManageProductionParticipants,
  canManageProductionRehearsals,
  canManageProductionSettlement,
  canManageProductionTickets,
  canOpenProductionInvite,
  canOpenProductionSchedule,
  canPublishProduction,
} from './permissions';

const pm = { is_primary_manager: true, delegate_roles: [] };
const role = (...roles: string[]) => ({ is_primary_manager: false, delegate_roles: roles });

/**
 * 予定管理 is participant-facing; every management rule stays on the
 * Production's own Roles, so being a participant opens nothing else.
 * 予定の作成 follows CreateRehearsalUseCase (PrimaryManager or REHEARSAL_MANAGER).
 */
describe('features/production/permissions', () => {
  it('opens 予定管理 for the PrimaryManager, a delegate and a participant, and for no one else', () => {
    expect(canOpenProductionSchedule(pm, false)).toBe(true);
    expect(canOpenProductionSchedule(role('TICKET_MANAGER'), false)).toBe(true);
    // a plain participant cannot read the Production (undefined) but is in /me/participating-productions
    expect(canOpenProductionSchedule(undefined, true)).toBe(true);
    // outside the Production: the server refuses the Production and no participation row exists
    expect(canOpenProductionSchedule(undefined, false)).toBe(false);
    expect(canOpenProductionSchedule(null, false)).toBe(false);
  });

  it('gives a participant no management permission', () => {
    for (const can of [canEditProductionInfo, canManageProductionParticipants, canManageProductionTickets, canManageProductionSettlement, canPublishProduction, canOpenProductionInvite, canManageProductionRehearsals]) {
      expect(can(undefined)).toBe(false);
      expect(can(role())).toBe(false);
    }
  });

  it('offers 予定の作成 to the PrimaryManager and a REHEARSAL_MANAGER only', () => {
    expect(canManageProductionRehearsals(pm)).toBe(true);
    expect(canManageProductionRehearsals(role('REHEARSAL_MANAGER'))).toBe(true);
    expect(canManageProductionRehearsals(role('REHEARSAL_MANAGER', 'TICKET_MANAGER'))).toBe(true);
    for (const other of ['PARTICIPANT_MANAGER', 'PERFORMANCE_MANAGER', 'TICKET_MANAGER', 'CHECKIN_MANAGER', 'ACCOUNTING_MANAGER', 'QUESTIONNAIRE_MANAGER']) {
      expect(canManageProductionRehearsals(role(other))).toBe(false);
    }
  });
});
