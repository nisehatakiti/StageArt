import type { ContentBlock, PublicPageContent, PublicPageContentAdmin } from '@/features/publicPage/contentTypes';

/** GET /organizations/{id}/public-page/content of an organization with no saved layout. */
export function contentAdminFixture(overrides: Partial<PublicPageContentAdmin> = {}): PublicPageContentAdmin {
  return {
    layout: null,
    contents: [],
    choices: {
      system: [
        { key: 'productions', label: '公演一覧' },
        { key: 'about', label: '団体について' },
      ],
      productions: [
        { id: 'prod-1', name: '春の公演', is_published: true },
        { id: 'prod-2', name: '準備中の公演', is_published: false },
      ],
      max_columns: 6,
      max_indent: 3,
    },
    ...overrides,
  };
}

export function contentFixture(overrides: Partial<PublicPageContent> = {}): PublicPageContent {
  return {
    id: 'content-1',
    title: 'お知らせ',
    body: '本文',
    status: 'publish',
    created_at: '2026-10-21T00:00:00+00:00',
    updated_at: '2026-10-21T00:00:00+00:00',
    ...overrides,
  };
}

/**
 * Wraps the installed route-matcher fetch with a small in-memory version
 * of the コンテンツ endpoints of `/organizations/{organizationId}/public-page`:
 * GET .../content, PUT .../content-layout, POST .../contents,
 * PUT/DELETE .../contents/{id}. Every write is recorded in `requests`.
 * `layoutError`, when set, is answered to the layout PUT instead (422).
 */
export function mockContentEndpoints(organizationId: string, initial: PublicPageContentAdmin, options: { layoutError?: { code: string; message: string } } = {}) {
  const base = `/organizations/${organizationId}/public-page`;
  const state = { current: initial, requests: [] as { method: string; path: string; body: unknown }[] };
  const installedFetch = global.fetch;
  let seq = 0;

  global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
    const url = String(input);
    const method = init?.method ?? 'GET';
    const body = init?.body ? JSON.parse(String(init.body)) : undefined;
    const respond = (status: number, payload: unknown) =>
      ({ ok: status >= 200 && status < 300, status, text: async () => (payload === null ? '' : JSON.stringify(payload)), json: async () => payload }) as unknown as Response;
    const path = url.includes(base) ? url.slice(url.indexOf(base) + base.length) : null;

    if (path === null) {
      return installedFetch(input as RequestInfo, init);
    }

    if (method !== 'GET') {
      state.requests.push({ method, path, body });
    }

    if (path === '/content' && method === 'GET') {
      return respond(200, state.current);
    }

    if (path === '/content-layout' && method === 'PUT') {
      if (options.layoutError) {
        return respond(422, { ...options.layoutError, data: { status: 422 } });
      }
      state.current = { ...state.current, layout: (body as { blocks: ContentBlock[] }).blocks };
      return respond(200, state.current);
    }

    if (path === '/contents' && method === 'POST') {
      seq += 1;
      const created = { ...contentFixture({ id: `new-content-${seq}` }), ...(body as object) } as PublicPageContent;
      state.current = { ...state.current, contents: [created, ...state.current.contents] };
      return respond(201, created);
    }

    const match = path.match(/^\/contents\/([^/]+)$/);
    if (match && method === 'PUT') {
      const updated = { ...state.current.contents.find((c) => c.id === match[1])!, ...(body as object) } as PublicPageContent;
      state.current = { ...state.current, contents: state.current.contents.map((c) => (c.id === match[1] ? updated : c)) };
      return respond(200, updated);
    }
    if (match && method === 'DELETE') {
      state.current = { ...state.current, contents: state.current.contents.filter((c) => c.id !== match[1]) };
      return respond(204, null);
    }

    return installedFetch(input as RequestInfo, init);
  }) as unknown as typeof fetch;

  return state;
}
