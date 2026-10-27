import { useLocalSearchParams } from 'expo-router';

import { BudgetEditor } from '@/features/budget/BudgetEditor';

/** 予算の編集: always loaded from the server, so a reload shows what was saved. */
export default function BudgetScreen() {
  const { id, budgetId, saved } = useLocalSearchParams<{ id: string; budgetId: string; saved?: string }>();

  return <BudgetEditor key={budgetId} productionId={id} budgetId={budgetId} justCreated={saved === '1'} />;
}
