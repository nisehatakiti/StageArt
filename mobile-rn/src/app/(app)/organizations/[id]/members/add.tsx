import { useLocalSearchParams } from 'expo-router';

import { OrganizationPlaceholderScreen } from '@/components/organization-placeholder-screen';

/**
 * StageArt Organization Context Menu仕様整合: メンバー管理 > 追加
 * (docs/03-PublicPageURLAndPublicationSchedule.md「メンバー管理の操作
 * 入口」) - メンバーを直接追加する画面。入力項目・保存処理・Backend連携は
 * 今回未実装（see OrganizationPlaceholderScreen's own docblock - no
 * Backend Use Case to add a Member directly exists yet either, see
 * ../members.tsx's own docblock for the confirmed gap this mirrors）。
 */
export default function OrganizationMembersAddScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  return (
    <OrganizationPlaceholderScreen
      id={id}
      testIdPrefix="organization-members-add"
      title="メンバーを追加"
      description="メンバーを直接追加する機能は準備中です。"
    />
  );
}
