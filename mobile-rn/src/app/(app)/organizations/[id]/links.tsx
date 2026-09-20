import { useLocalSearchParams } from 'expo-router';

import { OrganizationPlaceholderScreen } from '@/components/organization-placeholder-screen';

/**
 * StageArt Organization Context Menu仕様整合: 公開ページ管理 > リンク
 * (docs/03-PublicPageURLAndPublicationSchedule.md「リンク」) - Organization
 * Public PageのOTHER LINKSに表示する外部リンクの管理画面。入力項目・保存
 * 処理は今回未実装（see OrganizationPlaceholderScreen's own docblock）。
 */
export default function OrganizationLinksScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  return (
    <OrganizationPlaceholderScreen
      id={id}
      testIdPrefix="organization-links"
      title="リンク"
      description="外部リンク（公式YouTube等）の管理機能は準備中です。"
    />
  );
}
