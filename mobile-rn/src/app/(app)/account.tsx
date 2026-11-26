import { AccountContent } from '@/features/account/AccountContent';

/**
 * StageArt Blueprint再構成 Phase 1d §4: `/account` - the canonical
 * Account Management route on both Web and Native (AppChrome already
 * provides the surrounding shell; this screen is content-only). Reached
 * from the common menu's "アカウント管理" item (useNavMenu.ts) on every
 * authenticated screen, never from a Home-specific UI.
 */
export default function AccountScreen() {
  return <AccountContent />;
}
