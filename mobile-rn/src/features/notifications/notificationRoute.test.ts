import { resolveNotificationRoute } from './notificationRoute';

describe('resolveNotificationRoute', () => {
  it('opens the Rehearsal attendance screen for a Rehearsal notification', () => {
    expect(resolveNotificationRoute({ rehearsal_id: 'r-1', production_id: 'p-1', target_type: null, target_id: null })).toBe(
      '/production/p-1/schedule/attendance/r-1'
    );
  });

  it('opens the Expense detail for an Expense approval notification (修正8)', () => {
    expect(resolveNotificationRoute({ rehearsal_id: null, production_id: 'p-1', target_type: 'expense', target_id: 'e-1' })).toBe('/expenses/e-1');
  });

  it('points nowhere for a notification without a known target', () => {
    expect(resolveNotificationRoute({ rehearsal_id: null, production_id: 'p-1', target_type: null, target_id: null })).toBeNull();
    expect(resolveNotificationRoute({ rehearsal_id: null, production_id: 'p-1', target_type: 'unknown', target_id: 'x' })).toBeNull();
    expect(resolveNotificationRoute({ rehearsal_id: null, production_id: null })).toBeNull();
  });
});
