import { ActivityIndicator, StyleSheet } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Organization Context Menu仕様整合 (docs/03-
 * PublicPageURLAndPublicationSchedule.md「Organization Context Menu」):
 * shared shell for every confirmed Context Menu item that has no
 * screen yet (ABOUT/SNS/リンク, メンバー管理の追加/代理人を設定/代表者交代,
 * 公演管理の過去公演を登録する/公演を編集する, 会計管理). Mirrors
 * viewing-history.tsx's own established precedent for this exact
 * situation ("a real, navigable entry point with a 準備中 state, not an
 * invented API response") - each of these routes is real and reachable
 * from the Organization Context left sidebar, but deliberately renders
 * no business logic, input fields, or API calls of its own; only the
 * Organization existence/membership check every other
 * `organizations/[id]/*` screen already performs.
 */
export function OrganizationPlaceholderScreen({
  id,
  testIdPrefix,
  title,
  description,
}: {
  id: string | undefined;
  testIdPrefix: string;
  title: string;
  description: string;
}) {
  const organizationsQuery = useOrganizations();
  const organization = organizationsQuery.data?.find((candidate) => candidate.id === id) ?? null;

  if (organizationsQuery.isLoading) {
    return (
      <>
        <ActivityIndicator testID={`${testIdPrefix}-loading`} />
      </>
    );
  }

  if (organizationsQuery.isError) {
    return (
      <>
        <ThemedText testID={`${testIdPrefix}-error`}>{getErrorMessage(organizationsQuery.error)}</ThemedText>
      </>
    );
  }

  if (!organization) {
    return (
      <>
        <ThemedText testID={`${testIdPrefix}-not-found`}>この団体が見つからないか、参加していません。</ThemedText>
      </>
    );
  }

  return (
    <>
      <ThemedView style={styles.container}>
        <ThemedText type="title" style={styles.title} testID={`${testIdPrefix}-title`}>
          {title}
        </ThemedText>
        <ThemedText themeColor="textSecondary" testID={`${testIdPrefix}-placeholder`} style={styles.body}>
          {description}
        </ThemedText>
      </ThemedView>
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  title: { fontSize: 22, lineHeight: 28 },
  body: { textAlign: 'center' },
});
