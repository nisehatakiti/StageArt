import type { NotificationFact } from '@/types/api';

import { buildNotificationViewModel } from './viewModel';

const base: NotificationFact = {
  id: 'notif-1',
  type: 'timetable_version_published',
  production_id: 'prod-1',
  rehearsal_id: 'rehearsal-1',
  timetable_id: 'timetable-1',
  version: 2,
  published_by: 'person-1',
  published_at: '2026-08-15T09:30:00+09:00',
  change_summary: '集合時間を30分繰り上げました。',
  created_at: '2026-08-15T09:30:00+09:00',
  is_read: false,
};

describe('buildNotificationViewModel', () => {
  it('maps the timetable_version_published type to its Japanese title', () => {
    expect(buildNotificationViewModel(base).title).toBe('タイムテーブルが更新されました');
  });

  it('falls back to the raw type string for an unrecognized type', () => {
    const viewModel = buildNotificationViewModel({ ...base, type: 'some_future_type' });
    expect(viewModel.title).toBe('some_future_type');
  });

  it('passes change_summary through as summary, null when absent', () => {
    expect(buildNotificationViewModel(base).summary).toBe('集合時間を30分繰り上げました。');
    expect(buildNotificationViewModel({ ...base, change_summary: null }).summary).toBeNull();
  });

  it('formats published_at as YYYY/M/D HH:mm', () => {
    expect(buildNotificationViewModel(base).publishedAtDisplay).toBe('2026/8/15 09:30');
  });

  it('does not surface published_by anywhere in the view model', () => {
    const viewModel = buildNotificationViewModel(base);
    expect(Object.values(viewModel)).not.toContain('person-1');
  });

  it('passes is_read through as isRead, never derived client-side', () => {
    expect(buildNotificationViewModel(base).isRead).toBe(false);
    expect(buildNotificationViewModel({ ...base, is_read: true }).isRead).toBe(true);
  });
});
