import { StyleSheet, TouchableOpacity } from 'react-native';

import { Spacing } from '@/constants/theme';
import type { Organization } from '@/types/api';

import { ThemedText } from './themed-text';

/**
 * Organization Scope role label (Membership.RoleKey: OWNER | MEMBER -
 * Authorization.md's "Organization Scope"). Deliberately NOT the
 * instruction's illustrative "Primary Manager / Delegate" labels: those
 * are Production Scope concepts (PrimaryManager / ProductionDelegate)
 * and would misrepresent an Organization Membership role if shown here
 * - a disclosed correction, see the Phase 5.2 report.
 */
const ROLE_LABEL: Record<string, string> = {
  OWNER: 'オーナー',
  MEMBER: 'メンバー',
};

export function OrganizationTile({ organization, onPress }: { organization: Organization; onPress: () => void }) {
  return (
    <TouchableOpacity style={styles.tile} onPress={onPress} testID={`organization-tile-${organization.id}`}>
      <ThemedText type="default">{organization.name}</ThemedText>
      <ThemedText type="small" themeColor="textSecondary">
        所属：{ROLE_LABEL[organization.current_person_role] ?? organization.current_person_role}
      </ThemedText>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  tile: {
    padding: Spacing.three,
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    marginBottom: Spacing.two,
    gap: Spacing.half,
  },
});
