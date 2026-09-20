import { useLocalSearchParams } from 'expo-router';

import { OrganizationPlaceholderScreen } from '@/components/organization-placeholder-screen';

/**
 * StageArt Organization Context Menu仕様整合: 公演管理 > 公演を編集する
 * (docs/03-PublicPageURLAndPublicationSchedule.md「Organization Context
 * Menu」) - Organization Contextから対象公演を選んで編集する画面。既存の
 * Production Context側には`/productions/{id}/edit`が既に存在するが、
 * Organization Context側から「どの公演を編集するか」選ぶ導線・業務フロー
 * は仕様確認できていないため、今回は勝手に組み立てず骨格のみ用意する
 * （see OrganizationPlaceholderScreen's own docblock）。既存の
 * ../productions.tsx（公演一覧）は削除せずそのまま維持している。
 */
export default function OrganizationProductionsEditScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  return (
    <OrganizationPlaceholderScreen
      id={id}
      testIdPrefix="organization-productions-edit"
      title="公演を編集する"
      description="ここから公演を選んで編集する機能は準備中です。個別の公演の編集は「公演一覧」から行えます。"
    />
  );
}
