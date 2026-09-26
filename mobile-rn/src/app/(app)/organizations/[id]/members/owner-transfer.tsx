import { useLocalSearchParams } from 'expo-router';

import { OrganizationPlaceholderScreen } from '@/components/organization-placeholder-screen';

/**
 * StageArt Organization Context Menu仕様整合 + UI再構成 instruction (this
 * round, backend調査): メンバー管理 > 代表者交代
 * (docs/03-PublicPageURLAndPublicationSchedule.md「代表者交代」§画面構成) -
 * the confirmed screen design requires a dropdown listing the
 * Organization's existing members ("プルダウンには、Organizationに登録さ
 * れているメンバーを表示する") to pick the new Owner from. Backend DOES
 * already implement the actual transfer
 * (plugin/src/Application/Organization/OwnerTransferUseCase.php,
 * `POST /organizations/{id}/owner-transfer`), but no UseCase/REST route
 * exists anywhere to list an Organization's members with names to
 * populate that dropdown (confirmed this round by reading
 * MembershipRestController.php and every Application/Membership/*
 * UseCase - the same gap ../members.tsx's own docblock already discloses
 * for the same reason). Building a substitute UI (e.g. a raw Person ID
 * text field) instead of the confirmed dropdown would be inventing a
 * different screen design than what is actually confirmed, so this stays
 * a placeholder rather than working around the gap - see this round's
 * report for the 要確認.
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
