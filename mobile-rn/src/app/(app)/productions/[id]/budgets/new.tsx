import { useLocalSearchParams } from 'expo-router';

import { BudgetEditor } from '@/features/budget/BudgetEditor';

/** 新しい予算: the first save creates the Budget and opens its own URL. */
export default function NewBudgetScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  return <BudgetEditor productionId={id} />;
}
