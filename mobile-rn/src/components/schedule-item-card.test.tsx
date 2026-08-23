import { fireEvent, render, screen } from '@testing-library/react-native';

import type { TimetableItem } from '@/types/api';

import { ScheduleItemCard } from './schedule-item-card';

const baseItem: TimetableItem = {
  id: 'item-1',
  timetable_id: 'tt-1',
  title: 'シュート',
  description: null,
  start_date_time: '2026-08-18T10:00:00+09:00',
  end_date_time: '2026-08-18T12:00:00+09:00',
  display_order: null,
  category: null,
  venue: null,
  participant_type: null,
  target_person_ids: [],
  notes: null,
  created_at: '',
  updated_at: '',
};

/**
 * Rendered directly via @testing-library/react-native's plain render()
 * rather than expo-router's renderRouter() - this is a standalone
 * component with no routing dependency, and plain render() does not
 * force Jest fake timers the way renderRouter() does (see Phase 5.2's
 * documented renderRouter()-specific test environment limitation).
 * render()/rerender() are async in this installed RNTL version and are
 * always awaited (same v14 API-change class documented in Phase 5.1).
 */
describe('ScheduleItemCard', () => {
  it('§14/§15: shows the Category as a Badge, displayed as-is (not enum-mapped)', async () => {
    await render(<ScheduleItemCard item={{ ...baseItem, category: '独自カテゴリ名' }} highlighted={false} onPress={jest.fn()} />);
    expect(screen.getByTestId('category-badge-item-1')).toHaveTextContent('独自カテゴリ名');
  });

  it('§16: shows the Stage Usage badge only when Venue is exactly 舞台', async () => {
    const { rerender } = await render(
      <ScheduleItemCard item={{ ...baseItem, venue: '舞台' }} highlighted={false} onPress={jest.fn()} />
    );
    expect(screen.getByTestId('stage-usage-badge-item-1')).toBeVisible();
    expect(screen.queryByTestId('venue-badge-item-1')).toBeNull();

    await rerender(<ScheduleItemCard item={{ ...baseItem, venue: '楽屋' }} highlighted={false} onPress={jest.fn()} />);
    expect(screen.queryByTestId('stage-usage-badge-item-1')).toBeNull();
    expect(screen.getByTestId('venue-badge-item-1')).toHaveTextContent('楽屋');
  });

  it('§17: the Stage Usage badge renders regardless of viewer - no Role parameter exists on this component', async () => {
    await render(<ScheduleItemCard item={{ ...baseItem, venue: '舞台' }} highlighted={false} onPress={jest.fn()} />);
    expect(screen.getByTestId('stage-usage-badge-item-1')).toBeVisible();
  });

  it('§18 Pattern A (Participant Type only)', async () => {
    await render(<ScheduleItemCard item={{ ...baseItem, participant_type: 'STAFF' }} highlighted={false} onPress={jest.fn()} />);
    expect(screen.getByTestId('participant-type-badge-item-1')).toHaveTextContent('Role: STAFF');
    expect(screen.queryByTestId('target-person-badge-item-1')).toBeNull();
  });

  it('§18 Pattern B (Person only)', async () => {
    await render(
      <ScheduleItemCard item={{ ...baseItem, target_person_ids: ['p1', 'p2'] }} highlighted={false} onPress={jest.fn()} />
    );
    expect(screen.getByTestId('target-person-badge-item-1')).toHaveTextContent('対象: 2名');
    expect(screen.queryByTestId('participant-type-badge-item-1')).toBeNull();
  });

  it('§18 Pattern C (Participant Type + Person)', async () => {
    await render(
      <ScheduleItemCard
        item={{ ...baseItem, participant_type: 'LIGHTING', target_person_ids: ['p1'] }}
        highlighted={false}
        onPress={jest.fn()}
      />
    );
    expect(screen.getByTestId('participant-type-badge-item-1')).toHaveTextContent('Role: LIGHTING');
    expect(screen.getByTestId('target-person-badge-item-1')).toHaveTextContent('対象: 1名');
  });

  it('§5/§6: shows the Highlight badge only when highlighted=true, without ever hiding the Item itself', async () => {
    const { rerender } = await render(<ScheduleItemCard item={baseItem} highlighted={false} onPress={jest.fn()} />);
    expect(screen.queryByTestId('highlight-badge-item-1')).toBeNull();
    expect(screen.getByTestId('schedule-item-item-1')).toBeVisible();

    await rerender(<ScheduleItemCard item={baseItem} highlighted onPress={jest.fn()} />);
    expect(screen.getByTestId('highlight-badge-item-1')).toBeVisible();
    expect(screen.getByTestId('schedule-item-item-1')).toBeVisible();
  });

  it('calls onPress when tapped', async () => {
    const onPress = jest.fn();
    await render(<ScheduleItemCard item={baseItem} highlighted={false} onPress={onPress} />);

    await fireEvent.press(screen.getByTestId('schedule-item-item-1'));

    expect(onPress).toHaveBeenCalledTimes(1);
  });
});
