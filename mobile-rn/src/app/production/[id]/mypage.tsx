import { MyPageContent } from '@/features/mypage/MyPageContent';

/**
 * This screen's content is entirely Person-scoped (never reads the
 * Production [id] param) - the actual implementation lives in
 * MyPageContent, shared with the top-level /profile route (see that
 * route's own docblock for why both exist).
 */
export default function MyPageScreen() {
  return <MyPageContent />;
}
