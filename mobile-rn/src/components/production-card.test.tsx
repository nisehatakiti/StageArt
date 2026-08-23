import { render, screen } from '@testing-library/react-native';

import type { Production } from '@/types/api';

import { ProductionCard } from './production-card';

const baseProduction: Production = {
  id: 'prod-1',
  project_id: 'proj-1',
  name: '○○公演2026',
  title_heading: null,
  status: 'ACTIVE',
  primary_manager_person_id: 'person-1',
  created_at: '',
  updated_at: '',
  is_primary_manager: true,
  delegate_role: null,
};

describe('ProductionCard: title heading (Phase 7.1)', () => {
  it('shows title_heading above the name when set', async () => {
    await render(<ProductionCard production={{ ...baseProduction, title_heading: '旗揚げ公演' }} onPress={() => {}} />);

    expect(screen.getByTestId('production-card-title-heading-prod-1')).toHaveTextContent('旗揚げ公演');
    expect(screen.getByText('○○公演2026')).toBeVisible();
  });

  it('shows no title_heading element when it is null', async () => {
    await render(<ProductionCard production={baseProduction} onPress={() => {}} />);

    expect(screen.queryByTestId('production-card-title-heading-prod-1')).toBeNull();
    expect(screen.getByText('○○公演2026')).toBeVisible();
  });
});
