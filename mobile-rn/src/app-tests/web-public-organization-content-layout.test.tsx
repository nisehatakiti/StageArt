import { StyleSheet } from 'react-native';
import { renderRouter, screen, waitFor, within } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const publicOrganization = {
  id: 'org-1',
  name: 'StageArt劇団',
  slug: 'stageart-theatre',
  description: '舞台芸術を探求する劇団です。',
  logo_url: null,
  published_at: '2026-01-01T00:00:00+09:00',
  productions: [
    { id: 'prod-1', name: '春の公演', slug: 'spring', status: 'ACTIVE', published_at: '2026-01-01T00:00:00+09:00' },
    { id: 'prod-2', name: '去年の公演', slug: 'last-year', status: 'COMPLETED', published_at: '2025-01-01T00:00:00+09:00' },
  ],
  sns: [],
  external_links: [],
  // As the API resolves it: a hidden (draft) content is already an empty slot.
  content_layout: [
    {
      block_id: 'b1',
      sections: [
        {
          section_id: 's1',
          heading: 'お知らせ',
          columns: 3,
          layout: 'horizontal',
          slots: [
            { type: 'free_content', ref: 'c1', indent: 0, content: { id: 'c1', title: '団員募集', body: '新メンバーを募集しています。\n見学歓迎。' } },
            { type: 'none', ref: '', indent: 0 },
            { type: 'url', ref: 'https://example.com/join', indent: 1 },
          ],
        },
      ],
    },
    {
      block_id: 'b2',
      sections: [
        {
          section_id: 's2',
          heading: '',
          columns: 1,
          layout: 'vertical',
          slots: [
            { type: 'heading', ref: '注目の公演', indent: 0 },
            { type: 'production', ref: 'prod-1', indent: 2, production: { id: 'prod-1', name: '春の公演', slug: 'spring', status: 'ACTIVE' } },
            { type: 'system', ref: 'about', indent: 0 },
            { type: 'member', ref: '', indent: 1, member: { name: '山田 太郎' } },
          ],
        },
      ],
    },
  ],
};

/**
 * コンテンツ配置 on the public organization page: only the placed items, in
 * the saved order (blocks, sections, slots), with the section heading, the
 * column count of a horizontal section and the indent; the fixed 公演
 * lists are not shown because the 公演一覧 is not placed.
 */
describe('Web 団体公開ページ: コンテンツ配置の反映', () => {
  it('shows the placed contents in their saved order and columns', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/organizations/by-slug/stageart-theatre'), status: 200, body: publicOrganization }]);

    renderRouter('src/app', { initialUrl: '/stageart-theatre' });

    await waitFor(() => expect(screen.getByTestId('public-content-layout')).toBeVisible());

    const blocks = screen.getAllByTestId(/^public-content-block-/);
    expect(blocks.map((b) => b.props.testID)).toEqual(['public-content-block-b1', 'public-content-block-b2']);

    expect(screen.getByTestId('public-content-section-s1-heading')).toHaveTextContent('お知らせ');
    expect(screen.queryByTestId('public-content-section-s2-heading')).toBeNull();

    // Horizontal, 3 columns (the jest window is 750px wide: not a phone).
    const column = StyleSheet.flatten(screen.getByTestId('public-content-section-s1-slot-0').props.style);
    expect(column.width).toBe(`${100 / 3}%`);
    expect(screen.getByTestId('public-content-section-s1-slot-0-content')).toHaveTextContent(/団員募集/);
    // The body keeps its line break (plain text, shown as entered).
    expect(
      within(screen.getByTestId('public-content-section-s1-slot-0-content')).getByText('新メンバーを募集しています。\n見学歓迎。', { normalizer: (text: string) => text })
    ).toBeVisible();
    expect(screen.getByTestId('public-content-section-s1-slot-1')).toBeEmptyElement();
    expect(screen.getByTestId('public-content-section-s1-slot-2-link')).toHaveTextContent('詳しく見る');
    expect(StyleSheet.flatten(screen.getByTestId('public-content-section-s1-slot-2').props.style).paddingLeft).toBe(8 + 24);

    // Vertical section: heading, production card (indent 2), 団体について, a member (indent 1).
    expect(screen.getByTestId('public-content-section-s2-slot-0-heading')).toHaveTextContent('注目の公演');
    expect(screen.getByTestId('public-content-section-s2-slot-1-production-spring')).toHaveTextContent(/春の公演/);
    expect(StyleSheet.flatten(screen.getByTestId('public-content-section-s2-slot-1').props.style).paddingLeft).toBe(48);
    expect(screen.getByTestId('public-organization-about')).toHaveTextContent('舞台芸術を探求する劇団です。');
    // A member from メンバー管理: the name only.
    expect(screen.getByTestId('public-content-section-s2-slot-3-member')).toHaveTextContent(/メンバー.*山田 太郎/);
    expect(StyleSheet.flatten(screen.getByTestId('public-content-section-s2-slot-3').props.style).paddingLeft).toBe(24);

    // The 公演一覧 is not placed: the fixed lists are not shown.
    expect(screen.queryByTestId('public-organization-production-last-year')).toBeNull();
    // The page header is unchanged.
    expect(screen.getByTestId('public-organization-name')).toHaveTextContent('StageArt劇団');
  });
});
