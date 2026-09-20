import { useLocalSearchParams } from 'expo-router';

import { OrganizationPlaceholderScreen } from '@/components/organization-placeholder-screen';

/**
 * StageArt Organization Context Menu仕様整合: メンバー管理 > 代表者交代
 * (docs/03-PublicPageURLAndPublicationSchedule.md「代表者交代」) - Backend
 * には OwnerTransferUseCase が既に存在するが（plugin/src/Application/
 * Organization/OwnerTransferUseCase.php）、今回の目的はメニューから画面へ
 * 到達できる骨格を用意することに限定されており、既存Backendを使った実際
 * の交代フロー（対象メンバー選択・確認ダイアログ・API呼び出し・エラー
 * 処理）は業務ロジックのため今回未実装（see
 * OrganizationPlaceholderScreen's own docblock）。
 */
export default function OrganizationMembersOwnerTransferScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  return (
    <OrganizationPlaceholderScreen
      id={id}
      testIdPrefix="organization-members-owner-transfer"
      title="代表者交代"
      description="代表者を交代する機能は準備中です。"
    />
  );
}
