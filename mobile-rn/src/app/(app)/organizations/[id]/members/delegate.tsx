import { useLocalSearchParams } from 'expo-router';

import { OrganizationPlaceholderScreen } from '@/components/organization-placeholder-screen';

/**
 * StageArt Organization Context Menu仕様整合: メンバー管理 > 代理人を設定
 * (docs/03-PublicPageURLAndPublicationSchedule.md「代理人設定」) - 代表者が
 * メンバー管理／公演管理／会計管理の各領域ごとに代理権限を委任する画面。
 * No Organization-level delegate Backend concept exists yet (confirmed -
 * unlike ProductionDelegate, there is no OrganizationDelegate Domain/
 * UseCase anywhere in plugin/src), so入力項目・保存処理・Backend連携は
 * 今回未実装（see OrganizationPlaceholderScreen's own docblock）。
 */
export default function OrganizationMembersDelegateScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  return (
    <OrganizationPlaceholderScreen
      id={id}
      testIdPrefix="organization-members-delegate"
      title="代理人を設定"
      description="メンバー管理・公演管理・会計管理の代理権限を設定する機能は準備中です。"
    />
  );
}
