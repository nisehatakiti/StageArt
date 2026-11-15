import { Redirect, useLocalSearchParams } from 'expo-router';

/**
 * StageArt Organization Context Menu仕様整合 + UI再構成 instruction (this
 * round): 公演管理 > 公演を編集する
 * (docs/03-PublicPageURLAndPublicationSchedule.md「Organization Context
 * Menu」) - no confirmed spec exists for a distinct "pick which Production
 * to edit" flow at this route (see the previous round's own placeholder
 * text here, which already told users "個別の公演の編集は「公演一覧」から
 * 行えます"), so rather than leave this a dead-end "準備中" screen, it
 * redirects to the real, already-implemented 公演一覧 screen
 * (`../productions.tsx`) that placeholder text already pointed users to -
 * not a new business flow, just making the promised destination real.
 * Individual editing itself still happens via that list's existing
 * "管理する" link into the Production Context, unchanged.
 */
export default function OrganizationProductionsEditScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  return <Redirect href={`/organizations/${id}/productions`} />;
}
