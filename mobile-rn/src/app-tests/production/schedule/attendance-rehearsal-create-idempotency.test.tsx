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

/**
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §26): when the first create
 * request fails on the network (the app cannot know whether it was
 * created), pressing 作成 again sends the same idempotency key, so the
 * Backend answers with the Rehearsal already created instead of making a
 * second one. Changing the content first makes it a new key.
 * (One submit-triggering test per file, as in this directory.)
 */
describe('稽古作成: 再送時の冪等性キー', () => {
  it('resends with the same key after a network error, and a changed form gets a new key', async () => {
    const posts: Record<string, unknown>[] = [];
    let postCount = 0;
    global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
      const url = String(input);
      if (url.endsWith('/productions/prod-1/participants')) {
        return { ok: true, status: 200, text: async () => '[]', json: async () => [] } as Response;
      }
      if (url.endsWith('/productions/prod-1/rehearsals') && init?.method === 'POST') {
        posts.push(JSON.parse(String(init.body)));
        postCount += 1;
        if (postCount <= 2) {
          throw new TypeError('Network request failed');
        }
        return { ok: true, status: 200, text: async () => JSON.stringify(newRehearsal), json: async () => newRehearsal } as Response;
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

    // 1st send: network error.
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));
    await waitFor(() => expect(posts).toHaveLength(1));
    await waitFor(() => expect(screen.getByTestId('rehearsal-create-submit')).toBeEnabled());

    // 2nd send of the same content: same key; network error again.
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));
    await waitFor(() => expect(posts).toHaveLength(2));
    await waitFor(() => expect(screen.getByTestId('rehearsal-create-submit')).toBeEnabled());

    // Changed content: a new create action, a new key; this one succeeds.
    fireEvent.changeText(screen.getByTestId('rehearsal-create-description'), '通し稽古');
    await waitFor(() => expect(screen.getByTestId('rehearsal-create-description').props.value).toBe('通し稽古'));
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));
    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-new'));

    const keys = posts.map((body) => body.idempotency_key as string);
    expect(keys[0]).toMatch(/^[A-Za-z0-9._:-]{1,100}$/);
    expect(keys[1]).toBe(keys[0]);
    expect(keys[2]).not.toBe(keys[0]);
    expect(posts.map((body) => body.description)).toEqual(['通し', '通し', '通し稽古']);
  });
});
