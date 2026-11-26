import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiClient } from '@/api/client';
import CreateRehearsalScreen from '../../../app/(app)/production/[id]/schedule/attendance/create';

const mockApiClient = new ApiClient(() => 'mock-access-token');

jest.mock('@/auth/AuthContext', () => ({
  useAuth: () => ({ apiClient: mockApiClient, status: 'authenticated' }),
}));

const mockReplace = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ replace: mockReplace }),
  useLocalSearchParams: () => ({ id: 'prod-1' }),
}));

const newRehearsal = {
  id: 'rehearsal-new',
  production_id: 'prod-1',
  title: null,
  description: '通し',
  start_date_time: null,
  end_date_time: null,
  timezone: 'Asia/Tokyo',
  location: null,
  status: 'SCHEDULED',
  response_deadline: null,
  created_at: '',
  updated_at: '',
};

const reply = (status: number, body: unknown) =>
  ({ ok: status < 400, status, text: async () => JSON.stringify(body), json: async () => body }) as Response;

/** The Backend's envelope for each answer (RehearsalRestController). */
const answers = {
  conflict: () =>
    reply(409, {
      code: 'stageart_rehearsal_idempotency_conflict',
      message: 'This idempotency key was already used for a different Rehearsal create request.',
      data: { status: 409 },
    }),
  closed: () => reply(409, { code: 'stageart_production_closed', message: 'closed', data: { status: 409 } }),
  network: () => {
    throw new TypeError('Network request failed');
  },
  created: () => reply(201, newRehearsal),
};

/**
 * PR #7 R12 follow-up (docs/20-RehearsalManagementScreen.md §26.4): the
 * create screen tells a 409 for an idempotency key reused with different
 * content apart from a network error and from other 409s, and sends the
 * next 作成 with a new key (the old one can never succeed). After a
 * network error or another 409 the same content keeps its key.
 */
describe('稽古作成: 冪等性キーの409', () => {
  it('shows the idempotency conflict, apart from a network error and another 409, and then uses a new key', async () => {
    const sequence: (keyof typeof answers)[] = ['network', 'closed', 'conflict', 'created'];
    const posts: Record<string, unknown>[] = [];
    global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
      const url = String(input);
      if (url.endsWith('/productions/prod-1/participants')) {
        return reply(200, []);
      }
      if (url.endsWith('/productions/prod-1/rehearsals') && init?.method === 'POST') {
        posts.push(JSON.parse(String(init.body)));
        return answers[sequence[posts.length - 1]]();
      }
      throw new Error(`Unmocked fetch: ${url}`);
    }) as jest.Mock;

    render(
      <QueryClientProvider client={new QueryClient()}>
        <CreateRehearsalScreen />
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-description')).toBeVisible());
    fireEvent.changeText(screen.getByTestId('rehearsal-create-description'), '通し');
    await waitFor(() => expect(screen.getByTestId('rehearsal-create-description').props.value).toBe('通し'));

    // 1: network error.
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));
    await waitFor(() =>
      expect(screen.getByTestId('rehearsal-create-error')).toHaveTextContent('サーバーへ接続できませんでした。通信環境を確認してください。')
    );

    // 2: another 409 - its own message, same key.
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));
    await waitFor(() =>
      expect(screen.getByTestId('rehearsal-create-error')).toHaveTextContent('この公演は中止または決算完了済みのため、この操作はできません。')
    );

    // 3: the idempotency conflict - explained, and the key is dropped.
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));
    await waitFor(() =>
      expect(screen.getByTestId('rehearsal-create-error')).toHaveTextContent(
        '同じ送信キーで異なる内容の稽古を登録しようとしました。画面を再読み込みして、もう一度登録してください。'
      )
    );
    expect(mockReplace).not.toHaveBeenCalled();

    // 4: the same content again goes with a new key and is created.
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));
    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-new'));

    const keys = posts.map((body) => body.idempotency_key as string);
    expect(keys).toHaveLength(4);
    expect(keys[1]).toBe(keys[0]);
    expect(keys[2]).toBe(keys[0]);
    expect(keys[3]).not.toBe(keys[0]);
    expect(keys[3]).toMatch(/^[A-Za-z0-9._:-]{1,100}$/);
    expect(posts.map((body) => body.description)).toEqual(['通し', '通し', '通し', '通し']);
  });
});
