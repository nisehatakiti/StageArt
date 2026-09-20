import { useLocalSearchParams } from 'expo-router';

import { OrganizationPlaceholderScreen } from '@/components/organization-placeholder-screen';

/**
 * StageArt Organization Context Menu仕様整合: 会計管理
 * (docs/03-PublicPageURLAndPublicationSchedule.md「Organization Context
 * Menu」- 会計入力/仕訳一覧/貸借対照表/損益計算書/予算作成/会計締め処理)。
 * `production/[id]/accounting.tsx`という既存のProduction Context側の
 * 会計画面は存在するが、それをOrganization会計として流用・統合しない
 * （指示により明示的に禁止）。Organization Context独自の会計管理画面と
 * して新規に骨格のみ用意し、入力項目・集計処理・Backend連携は今回未実装
 * （see OrganizationPlaceholderScreen's own docblock）。
 */
export default function OrganizationAccountingScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  return (
    <OrganizationPlaceholderScreen
      id={id}
      testIdPrefix="organization-accounting"
      title="会計管理"
      description="団体の会計管理機能は準備中です。"
    />
  );
}
