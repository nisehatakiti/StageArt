import { useLocalSearchParams } from 'expo-router';

import { OrganizationPlaceholderScreen } from '@/components/organization-placeholder-screen';

/**
 * StageArt Organization Context Menu仕様整合: 公開ページ管理 > ABOUT
 * (docs/03-PublicPageURLAndPublicationSchedule.md「ABOUT」) - Organization
 * Public Pageに表示する団体紹介文の編集画面。入力項目・保存処理は今回
 * 未実装（see OrganizationPlaceholderScreen's own docblock）。
 */
export default function OrganizationAboutScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  return (
    <OrganizationPlaceholderScreen
      id={id}
      testIdPrefix="organization-about"
      title="ABOUT"
      description="団体紹介文（ABOUT）の編集機能は準備中です。"
    />
  );
}
