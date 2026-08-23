/**
 * §11/§37: the Backend stores/compares TimetableItem start/end times as
 * offset-naive wall-clock values (see
 * ListProductionTimetableItemsUseCase::parseOptionalDateTime's own
 * comment) and PHP's date.timezone is Asia/Tokyo (confirmed live on the
 * Development server via `php -i`), so a plain, offset-free
 * "YYYY-MM-DD HH:mm:ss" string - built from the device's own local Date
 * components - is what the existing convention expects. No independent
 * UTC/JST conversion is introduced here.
 */
export function formatWallClock(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(
    date.getMinutes()
  )}:${pad(date.getSeconds())}`;
}

export type ScheduleRange = { from: string; to: string } | undefined;

/**
 * §9: "当日+翌日" default view, matching the Flutter reference
 * implementation's convention exactly (schedule_screen.dart: `from =
 * today 00:00`, `to = from + 2 days`) - a half-open window covering
 * today and tomorrow in full.
 */
export function defaultScheduleRange(now: Date = new Date()): { from: string; to: string } {
  const start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const end = new Date(start);
  end.setDate(end.getDate() + 2);

  return { from: formatWallClock(start), to: formatWallClock(end) };
}
