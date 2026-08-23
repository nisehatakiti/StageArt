import { StyleSheet, TouchableOpacity } from 'react-native';

import { Spacing } from '@/constants/theme';
import type { Production } from '@/types/api';

import { ThemedText } from './themed-text';

/** ProductionLifecycle.md's DRAFT/PLANNING/ACTIVE/COMPLETED/ARCHIVED,
 * plus CANCELLED as a separate non-standard state. Display-only label -
 * no Status-editing affordance anywhere in this component (§4 forbids
 * any Status-change UI this Phase). */
const STATUS_LABEL: Record<string, string> = {
  DRAFT: '下書き',
  PLANNING: '準備中',
  ACTIVE: '進行中',
  COMPLETED: '終了',
  ARCHIVED: 'アーカイブ済み',
  CANCELLED: '中止',
};

/**
 * Only Name, Title Heading, and Status are shown (§10 of an earlier
 * Phase): Production.Venue / StartDate / EndDate / Description do not
 * exist on the Domain today (confirmed by reading Production.php /
 * ProductionResult.php - there is no field to read, not merely one
 * judged unsafe to expose).
 *
 * Phase 7.1: `title_heading` (ProductionTitleHeadingPolicy.md's "Search
 * / Listing" section - listings must treat it as an independent field,
 * never merged into the title) renders above the name when set, same
 * two-Text-node structure as the Production Shell header.
 */
export function ProductionCard({ production, onPress }: { production: Production; onPress: () => void }) {
  return (
    <TouchableOpacity style={styles.card} onPress={onPress} testID={`production-card-${production.id}`}>
      {production.title_heading && (
        <ThemedText type="small" themeColor="textSecondary" testID={`production-card-title-heading-${production.id}`}>
          {production.title_heading}
        </ThemedText>
      )}
      <ThemedText type="default">{production.name}</ThemedText>
      <ThemedText type="small" themeColor="textSecondary">
        {STATUS_LABEL[production.status] ?? production.status}
      </ThemedText>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    padding: Spacing.three,
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    marginBottom: Spacing.two,
    gap: Spacing.half,
  },
});
