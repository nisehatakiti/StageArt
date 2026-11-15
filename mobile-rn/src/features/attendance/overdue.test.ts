import { canManageRehearsals, isOverdueScheduled } from './overdue';

const NOW = new Date('2026-11-11T10:00:00+09:00');

describe('isOverdueScheduled (PR #7 R10)', () => {
  it('調整中 and the start time has passed (any offset, same instant)', () => {
    expect(isOverdueScheduled({ status: 'SCHEDULED', start_date_time: '2026-11-11T09:59:00+09:00' }, NOW)).toBe(true);
    expect(isOverdueScheduled({ status: 'SCHEDULED', start_date_time: '2026-11-11T00:59:00Z' }, NOW)).toBe(true);
    expect(isOverdueScheduled({ status: 'SCHEDULED', start_date_time: '2026-11-11T10:00:00+09:00' }, NOW)).toBe(true);
  });

  it('調整中 before its start time is not overdue', () => {
    expect(isOverdueScheduled({ status: 'SCHEDULED', start_date_time: '2026-11-11T10:01:00+09:00' }, NOW)).toBe(false);
    // 20:00 -05:00 the day before is 10:00 JST: exactly the start, so overdue; one minute later is not.
    expect(isOverdueScheduled({ status: 'SCHEDULED', start_date_time: '2026-11-10T20:01:00-05:00' }, NOW)).toBe(false);
  });

  it('never for confirmed, active, completed or cancelled, or without a valid start', () => {
    for (const status of ['CONFIRMED', 'ACTIVE', 'COMPLETED', 'CANCELLED', 'DRAFT']) {
      expect(isOverdueScheduled({ status, start_date_time: '2020-01-01T10:00:00+09:00' }, NOW)).toBe(false);
    }
    expect(isOverdueScheduled({ status: 'SCHEDULED', start_date_time: null }, NOW)).toBe(false);
    expect(isOverdueScheduled({ status: 'SCHEDULED', start_date_time: 'not a date' }, NOW)).toBe(false);
  });
});

describe('canManageRehearsals', () => {
  it('PrimaryManager or REHEARSAL_MANAGER only', () => {
    expect(canManageRehearsals({ is_primary_manager: true, delegate_roles: [] })).toBe(true);
    expect(canManageRehearsals({ is_primary_manager: false, delegate_roles: ['REHEARSAL_MANAGER'] })).toBe(true);
    expect(canManageRehearsals({ is_primary_manager: false, delegate_roles: ['TICKET_MANAGER'] })).toBe(false);
    expect(canManageRehearsals(undefined)).toBe(false);
  });
});
