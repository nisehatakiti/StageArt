import { useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Radius, Spacing } from '@/constants/theme';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { useMyParticipatingProductions } from '@/features/participant/useParticipant';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Blueprint再構成 Phase 1d §2: `/profile`'s single Web/Native-
 * shared implementation - Person information only (表示名/Person ID/
 * 所属団体一覧/参加公演一覧/参加コード入力). Authentication/security
 * (メールアドレス確認/パスワード/Google連携/Push通知/ログアウト) moved out
 * to `/account` (features/account/AccountContent.tsx) - see this Phase's
 * report for the exact before/after split.
 *
 * 参加している公演一覧は GET /me/participating-productions
 * (useMyParticipatingProductions) が正式なデータソース - 稽古予定
 * (upcoming_rehearsals) ベースの旧プロキシは ProductionParticipant 自体を
 * 見ていなかったため、ACTIVEなPERSON Participantが存在しても一覧に出ない
 * 問題があった (participating-productions.tsx と同じ根本原因・同じ修正)。
 *
 * 行タップ時の遷移先は `/production/{id}/overview`（参加者向け公演概要
 * ダッシュボード）- `/production/{id}/schedule` はProduction管理Context
 * の「稽古管理」導線も兼ねており、管理操作を参加者に見せてしまうため
 * (participating-productions.tsx と同じ修正、overview.tsx 参照)。
 */
export function ProfileContent() {
  const router = useRouter();
  const currentPersonQuery = useCurrentPerson();
  const organizationsQuery = useOrganizations();
  const participatingProductionsQuery = useMyParticipatingProductions();
  const [detailsOpen, setDetailsOpen] = useState(false);

  const person = currentPersonQuery.data;
  const displayName = person ? [person.family_name, person.given_name].filter(Boolean).join(' ') : '';
  const participatingProductions = participatingProductionsQuery.data ?? [];

  return (
    <View style={styles.container}>
      <View style={styles.headerRow}>
        <View>
          <ThemedText type="title" testID="profile-display-name">
            {displayName || 'プロフィール'}
          </ThemedText>
          {currentPersonQuery.isLoading && <ActivityIndicator testID="profile-loading" />}
          {currentPersonQuery.isError && <ThemedText testID="profile-error">{getErrorMessage(currentPersonQuery.error)}</ThemedText>}
        </View>
        <TouchableOpacity
          testID="profile-edit-link"
          onPress={() =>
            router.push({
              pathname: '/set-name',
              params: { family_name_hint: person?.family_name ?? '', given_name_hint: person?.given_name ?? '', return_to: '/profile' },
            })
          }
          style={styles.secondaryButton}
        >
          <ThemedText type="linkPrimary">プロフィールを編集</ThemedText>
        </TouchableOpacity>
      </View>

      {/* 基本情報 */}
      <SectionCard title="基本情報" testID="profile-basic-info">
        <TouchableOpacity testID="profile-details-toggle" onPress={() => setDetailsOpen((open) => !open)}>
          <ThemedText type="small" themeColor="textSecondary">
            {detailsOpen ? '詳細情報を隠す' : '詳細情報を表示'}
          </ThemedText>
        </TouchableOpacity>
        {detailsOpen && person && (
          <View style={styles.row}>
            <ThemedText type="small" themeColor="textSecondary">
              Person ID
            </ThemedText>
            <ThemedText type="small" themeColor="textSecondary" testID="profile-person-id">
              {person.id}
            </ThemedText>
          </View>
        )}
      </SectionCard>

      {/* 参加 */}
      <SectionCard title="参加" testID="profile-join">
        <TouchableOpacity testID="profile-join-link" onPress={() => router.push('/join')} style={styles.linkRow}>
          <ThemedText type="linkPrimary">参加コードを入力する</ThemedText>
        </TouchableOpacity>
      </SectionCard>

      {/* 所属団体 */}
      <SectionCard title="所属団体" testID="profile-organizations">
        {organizationsQuery.isLoading && <ActivityIndicator testID="profile-organizations-loading" />}
        {organizationsQuery.isError && <ThemedText testID="profile-organizations-error">{getErrorMessage(organizationsQuery.error)}</ThemedText>}
        {!organizationsQuery.isLoading && !organizationsQuery.isError && (organizationsQuery.data?.length ?? 0) === 0 && (
          <ThemedText type="small" themeColor="textSecondary" testID="profile-organizations-empty">
            まだ所属している団体がありません。
          </ThemedText>
        )}
        {(organizationsQuery.data?.length ?? 0) > 0 && (
          <View style={styles.list} testID="profile-organizations-list">
            {organizationsQuery.data?.map((organization) => (
              <TouchableOpacity
                key={organization.id}
                testID={`profile-organization-${organization.id}`}
                style={styles.itemRow}
                onPress={() => router.push(`/organizations/${organization.id}` as Href)}
              >
                <ThemedText type="smallBold">{organization.name}</ThemedText>
                <ThemedText type="small" themeColor="textSecondary">
                  {organization.current_person_role === 'OWNER' ? 'オーナー' : 'メンバー'}
                </ThemedText>
              </TouchableOpacity>
            ))}
          </View>
        )}
        <TouchableOpacity testID="profile-organizations-view-all" onPress={() => router.push('/organizations')}>
          <ThemedText type="link">団体一覧を見る</ThemedText>
        </TouchableOpacity>
      </SectionCard>

      {/* 参加している公演 */}
      <SectionCard title="参加している公演" testID="profile-productions">
        {participatingProductionsQuery.isLoading && <ActivityIndicator testID="profile-productions-loading" />}
        {!participatingProductionsQuery.isLoading && participatingProductions.length === 0 && (
          <ThemedText type="small" themeColor="textSecondary" testID="profile-productions-empty">
            参加している公演・活動はありません。
          </ThemedText>
        )}
        {participatingProductions.length > 0 && (
          <View style={styles.list} testID="profile-productions-list">
            {participatingProductions.map((production) => (
              <TouchableOpacity
                key={production.production_id}
                testID={`profile-production-${production.production_id}`}
                style={styles.itemRow}
                onPress={() => router.push(`/production/${production.production_id}/overview` as Href)}
              >
                <ThemedText type="smallBold">{production.production_name}</ThemedText>
              </TouchableOpacity>
            ))}
          </View>
        )}
        <TouchableOpacity testID="profile-productions-view-all" onPress={() => router.push('/participating-productions')}>
          <ThemedText type="link">すべて見る</ThemedText>
        </TouchableOpacity>
      </SectionCard>
    </View>
  );
}

function SectionCard({ title, testID, children }: { title: string; testID?: string; children: React.ReactNode }) {
  return (
    <View style={styles.card} testID={testID}>
      <ThemedText type="subtitle" style={styles.cardTitle}>
        {title}
      </ThemedText>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.four, maxWidth: 640, width: '100%' },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between' },
  secondaryButton: {
    borderWidth: 1,
    borderColor: '#C6892B',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
  },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.two,
    backgroundColor: '#fff',
  },
  cardTitle: { marginBottom: Spacing.half },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  linkRow: { paddingVertical: Spacing.half },
  list: { gap: Spacing.two },
  itemRow: {
    borderWidth: 1,
    borderColor: '#eee',
    borderRadius: Radius.medium,
    padding: Spacing.two,
    gap: Spacing.half,
  },
});
