import { MyPageContent } from '@/features/mypage/MyPageContent';

/**
 * Top-level, Production-independent "プロフィール" entry point
 * (BusinessFlowUXClarifications.md §02: プロフィール is one of Person
 * Home's 5 required entry points, available regardless of Organization/
 * Production affiliation). Renders the same MyPageContent as the
 * pre-existing production/[id]/mypage.tsx (that route is kept for the
 * in-Production Tabs navigation; this route is reached from Home
 * directly, with no Production context required).
 */
export default function ProfileScreen() {
  return <MyPageContent />;
}
