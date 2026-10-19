import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const publicOrganizationWithProfile = {
  id: 'org-1',
  name: 'StageArt劇団',
  slug: 'stageart-theatre',
  description: '舞台芸術を探求する劇団です。',
  logo_url: 'https://example.com/logo.png',
  published_at: '2026-01-01T00:00:00+09:00',
  productions: [],
  sns: [
    {
      id: 'sns-1',
      organization_id: 'org-1',
      service: 'X',
      account_identifier: 'stageart_theatre',
      profile_url: 'https://x.com/stageart_theatre',
      display_name: null,
      display_order: 0,
      visibility: true,
      created_at: '2026-01-01T00:00:00+09:00',
      updated_at: '2026-01-01T00:00:00+09:00',
    },
  ],
  external_links: [
    {
      id: 'link-1',
      organization_id: 'org-1',
      label: 'YouTube',
      url: 'https://youtube.com/@stageart',
      display_order: 0,
      created_at: '2026-01-01T00:00:00+09:00',
      updated_at: '2026-01-01T00:00:00+09:00',
    },
  ],
};

/**
 * StageArt 団体管理UI整理 §7: logo/SNS/external links saved via 団体情報
 * must actually render on the Public Organization Page, not just be
 * saved server-side ("保存するだけで表示されない項目がないようにする").
 * See web-public-organization-sns-and-links-hidden.test.tsx for the
 * "none of these exist" half - split per this codebase's one-it()-per-
 * file convention for renderRouter-based tests.
 */
describe('Web 団体公開ページ: ロゴ/SNS/外部リンクの反映（ON）', () => {
  it('renders the logo, SNS links, and external links the Public API returns', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations/by-slug/stageart-theatre'), status: 200, body: publicOrganizationWithProfile },
    ]);

    renderRouter('src/app', { initialUrl: '/stageart-theatre' });

    await waitFor(() => expect(screen.getByTestId('public-organization-content')).toBeVisible());

    expect(screen.getByTestId('public-organization-logo')).toBeVisible();
    // The whole logo is shown at its own aspect ratio - never cropped by cover.
    expect(screen.getByTestId('public-organization-logo').props.resizeMode).toBe('contain');
    expect(screen.getByTestId('public-organization-sns')).toBeVisible();
    expect(screen.getByTestId('public-organization-sns-sns-1')).toHaveTextContent(/stageart_theatre/);
    expect(screen.getByTestId('public-organization-external-links')).toBeVisible();
    expect(screen.getByTestId('public-organization-external-link-link-1')).toHaveTextContent(/YouTube/);
  });
});
