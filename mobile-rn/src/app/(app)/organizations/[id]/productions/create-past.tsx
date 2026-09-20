import { useLocalSearchParams } from 'expo-router';

import { OrganizationPlaceholderScreen } from '@/components/organization-placeholder-screen';

/**
 * StageArt Organization Context Menu仕様整合: 公演管理 > 過去公演を登録
 * する (docs/03-PublicPageURLAndPublicationSchedule.md「Organization
 * Context Menu」) - StageArt導入前の過去公演を登録する画面
 * (docs/04-DomainModel/ProductionPublicPagePolicy.md「Production Setup
 * Wizard」の「過去公演登録の入口が別である場合でも...基本フローは共通と
 * する」と関連するが、専用の入力フロー・Backend区別は現時点で未確認)。
 * 既存の ../productions/create.tsx（公演を作る）とは別画面として用意し、
 * 入力項目・保存処理は今回未実装（see OrganizationPlaceholderScreen's
 * own docblock）。
 */
export default function OrganizationProductionsCreatePastScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  return (
    <OrganizationPlaceholderScreen
      id={id}
      testIdPrefix="organization-productions-create-past"
      title="過去公演を登録する"
      description="過去公演の登録機能は準備中です。"
    />
  );
}
