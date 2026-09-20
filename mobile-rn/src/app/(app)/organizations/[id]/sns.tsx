import { useLocalSearchParams } from 'expo-router';

import { OrganizationPlaceholderScreen } from '@/components/organization-placeholder-screen';

/**
 * StageArt Organization Context Menu仕様整合: 公開ページ管理 > SNS
 * (docs/03-PublicPageURLAndPublicationSchedule.md「SNS」) - X/Instagram/
 * Facebookの公式アカウント登録画面。入力項目・保存処理は今回未実装
 * (see OrganizationPlaceholderScreen's own docblock)。
 */
export default function OrganizationSnsScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  return (
    <OrganizationPlaceholderScreen
      id={id}
      testIdPrefix="organization-sns"
      title="SNS"
      description="公式SNSアカウント（X／Instagram／Facebook）の登録機能は準備中です。"
    />
  );
}
