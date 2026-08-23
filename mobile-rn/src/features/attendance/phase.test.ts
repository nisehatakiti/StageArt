import { phaseForRehearsalStatus } from './phase';

describe('phaseForRehearsalStatus', () => {
  it.each(['DRAFT', 'SCHEDULED'])('maps %s to SCHEDULE_ADJUSTMENT', (status) => {
    expect(phaseForRehearsalStatus(status)).toBe('SCHEDULE_ADJUSTMENT');
  });

  it.each(['CONFIRMED', 'ACTIVE', 'COMPLETED', 'CANCELLED'])('maps %s to ATTENDANCE_CONFIRMATION', (status) => {
    expect(phaseForRehearsalStatus(status)).toBe('ATTENDANCE_CONFIRMATION');
  });
});
