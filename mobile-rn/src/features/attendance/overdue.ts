/**
 * PR #7 R10 (docs/20-RehearsalManagementScreen.md §23.2): a 調整中
 * (SCHEDULED) Rehearsal whose start time has passed. It is never moved
 * on by time - only CONFIRMED is (RehearsalStatusTimeSync) - so a manager
 * has to confirm or cancel it. This only identifies it for the
 * manager-facing screens; nothing is changed automatically.
 * A CANCELLED (or any other status) Rehearsal is never overdue.
 */
export const OVERDUE_SCHEDULED_MESSAGE = '開始予定時刻を過ぎています。確定または中止を選択してください。';

export function isOverdueScheduled(
  rehearsal: { status: string; start_date_time: string | null },
  now: Date = new Date()
): boolean {
  if (rehearsal.status !== 'SCHEDULED' || !rehearsal.start_date_time) {
    return false;
  }

  const start = new Date(rehearsal.start_date_time);

  return !Number.isNaN(start.getTime()) && now.getTime() >= start.getTime();
}

/** The same rule the Rehearsal screens use to offer management controls
 * (the Backend still enforces it): PrimaryManager or a REHEARSAL_MANAGER
 * delegate. */
export function canManageRehearsals(
  production: { is_primary_manager?: boolean; delegate_roles?: string[] } | undefined
): boolean {
  return production?.is_primary_manager === true || production?.delegate_roles?.includes('REHEARSAL_MANAGER') === true;
}
