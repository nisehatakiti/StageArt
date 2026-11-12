export const REHEARSAL_ACTIVITY_TYPES = [
  { value: 'REHEARSAL', label: '稽古' },
  { value: 'PRACTICE', label: '練習' },
  { value: 'SELF_PRACTICE', label: '自主練' },
  { value: 'WORKSHOP', label: 'ワークショップ' },
  { value: 'MEETING', label: '会議' },
  { value: 'DISCUSSION', label: '打ち合わせ' },
  { value: 'EVENT', label: 'イベント' },
  { value: 'OTHER', label: 'その他' },
] as const;

export type RehearsalActivityType = (typeof REHEARSAL_ACTIVITY_TYPES)[number]['value'];

export function rehearsalActivityTypeLabel(value: string): string {
  return REHEARSAL_ACTIVITY_TYPES.find((option) => option.value === value)?.label ?? value;
}
