import { useRouter, type Href } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { WebLayout } from '@/components/web/WebLayout';
import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useOrganizationContext } from '@/features/organization/OrganizationContext';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { useOrganizationProductions } from '@/features/production/useProductions';
import type { Organization } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Web版 再設計 Phase 2: 団体一覧 - the Web redesign's own §10
 * ("Webでは一覧を積極的に使う") applied to Organization, replacing the
 * card-picker embedded inside the old mobile Home. Each row's own
 * Production count comes from useOrganizationProductions() run once per
 * row (small N in practice - a Person's own Organization list - so no
 * new aggregate Backend endpoint was needed for this).
 */
export default function OrganizationsListScreen() {
  const organizationsQuery = useOrganizations();

  return (
    <WebLayout breadcrumbs={[{ label: 'StageArt', href: '/dashboard' }, { label: '団体' }]} activeTopLevel="organizations">
      <View style={styles.headerRow}>
        <ThemedText type="subtitle">団体一覧</ThemedText>
        <CreateOrganizationLink />
      </View>

      {organizationsQuery.isLoading && <ActivityIndicator testID="organizations-list-loading" />}
      {organizationsQuery.isError && (
        <ThemedText testID="organizations-list-error">{getErrorMessage(organizationsQuery.error)}</ThemedText>
      )}
      {!organizationsQuery.isLoading && !organizationsQuery.isError && (organizationsQuery.data?.length ?? 0) === 0 && (
        <ThemedText testID="organizations-list-empty" themeColor="textSecondary">
          まだ団体がありません。「団体を作る」から最初の団体を作成してください。
        </ThemedText>
      )}

      {(organizationsQuery.data?.length ?? 0) > 0 && (
        <ScrollView horizontal>
          <View testID="organizations-table">
            <View style={styles.tableHeaderRow}>
              <ThemedText type="smallBold" style={styles.colName}>
                団体名
              </ThemedText>
              <ThemedText type="smallBold" style={styles.colRole}>
                役割
              </ThemedText>
              <ThemedText type="smallBold" style={styles.colStatus}>
                公開状態
              </ThemedText>
              <ThemedText type="smallBold" style={styles.colAction}>
                操作
              </ThemedText>
            </View>
            {organizationsQuery.data?.map((org) => (
              <OrganizationRow key={org.id} organization={org} />
            ))}
          </View>
        </ScrollView>
      )}
    </WebLayout>
  );
}

function CreateOrganizationLink() {
  const router = useRouter();
  return (
    <TouchableOpacity testID="organizations-create-link" onPress={() => router.push('/organizations/create')} style={styles.createButton}>
      <ThemedText style={styles.createButtonText}>＋ 団体を作る</ThemedText>
    </TouchableOpacity>
  );
}

function OrganizationRow({ organization }: { organization: Organization }) {
  const router = useRouter();
  const { selectOrganization } = useOrganizationContext();
  const productionsQuery = useOrganizationProductions(organization.id);

  return (
    <TouchableOpacity
      testID={`organizations-row-${organization.id}`}
      style={styles.tableRow}
      onPress={() => {
        selectOrganization(organization.id);
        router.push(`/organizations/${organization.id}` as Href);
      }}
    >
      <ThemedText style={styles.colName} numberOfLines={1}>
        {organization.name}
      </ThemedText>
      <ThemedText type="small" themeColor="textSecondary" style={styles.colRole}>
        {organization.current_person_role === 'OWNER' ? 'オーナー' : 'メンバー'}
      </ThemedText>
      <ThemedText type="small" themeColor="textSecondary" style={styles.colStatus}>
        {organization.published_at ? '公開中' : '下書き'}
      </ThemedText>
      <ThemedText type="small" themeColor="textSecondary" style={styles.colAction}>
        {productionsQuery.data ? `公演 ${productionsQuery.data.length}件` : ''}
      </ThemedText>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  headerRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  createButton: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
  },
  createButtonText: { color: '#fff', fontWeight: '600' },
  tableHeaderRow: {
    flexDirection: 'row',
    borderBottomWidth: 1,
    borderBottomColor: '#e1dee6',
    paddingBottom: Spacing.two,
    marginBottom: Spacing.one,
  },
  tableRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
  },
  colName: { width: 260 },
  colRole: { width: 120 },
  colStatus: { width: 120 },
  colAction: { width: 140 },
});
