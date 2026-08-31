import { Platform } from 'react-native';

import { WebProfileContent } from '@/components/web/WebProfileContent';
import { MyPageContent } from '@/features/mypage/MyPageContent';

/**
 * Top-level, Production-independent "プロフィール" entry point
 * (BusinessFlowUXClarifications.md §02: プロフィール is one of Person
 * Home's 5 required entry points, available regardless of Organization/
 * Production affiliation). Renders the same MyPageContent as the
 * pre-existing production/[id]/mypage.tsx (that route is kept for the
 * in-Production Tabs navigation; this route is reached from Home
 * directly, with no Production context required).
 *
 * StageArt Web版 プロフィール Phase: this is also the exact URL
 * WebLayout's own sidebar "マイページ" item already points to (both
 * platforms share the one `/profile` route - only the presentation
 * differs), so the split happens here rather than as a second route:
 * native keeps this screen exactly as it always was; web renders
 * WebProfileContent instead, a genuinely new WebLayout-based screen
 * (see its own docblock for what is/isn't reused from MyPageContent).
 */
export default function ProfileScreen() {
  if (Platform.OS === 'web') {
    return <WebProfileContent />;
  }

  return <MyPageContent />;
}
