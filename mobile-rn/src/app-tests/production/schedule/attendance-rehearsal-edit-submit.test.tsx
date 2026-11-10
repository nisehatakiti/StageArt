import { QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiClient } from '@/api/client';
import { createQueryClient } from '@/api/queryClient';
import { mockFetchRoutes, productionOne, rehearsalConfirmed } from '@/features/attendance/__fixtures__/attendanceFixtures';

import EditRehearsalScreen from '../../../app/(app)/production/[id]/schedule/attendance/[rehearsalId]/edit';

const mockApiClient = new ApiClient(() => 'mock-access-token');

jest.mock('@/auth/AuthContext', () => ({
  useAuth: () => ({ apiClient: mockApiClient, status: 'authenticated' }),
}));

const mockReplace = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ replace: mockReplace }),
  useLocalSearchParams: () => ({ id: 'prod-1', rehearsalId: 'rehearsal-2' }),
}));

/**
 * The update request sends every field the edit form holds, description
 * included: the fetched description seeds 備考 (ec81f29) and, unedited,
 * goes back unchanged - it must never be sent as missing / null.
 *
 * R7: rendered in isolation (the create screen's own pattern), not through
 * renderRouter(): typing into a field several Stack/Tab layers deep never
 * reached the screen's state there (the renderRouter() limitation
 * documented in schedule-comment-post.test.tsx), so the field change this
 * test makes was never sent. The edit form is for the PrimaryManager /
 * REHEARSAL_MANAGER (f575bc5), decided from GET /productions/{id}.
 */
describe('Rehearsal edit screen: submit', () => {
  it('calls PUT /rehearsals/{id} with the changed field and the existing description carried through unchanged', async () => {
    const rehearsalWithDescription = { ...rehearsalConfirmed, description: '飲み物と着替えを持参してください' };

    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-2'), status: 200, body: rehearsalWithDescription },
    ]);

    render(
      <QueryClientProvider client={createQueryClient()}>
        <EditRehearsalScreen />
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('rehearsal-edit-location').props.value).toBe(rehearsalConfirmed.location));

    fireEvent.press(screen.getByTestId('rehearsal-edit-activity-type-SELF_PRACTICE'));
    await waitFor(() => expect(screen.getByTestId('rehearsal-edit-activity-type-SELF_PRACTICE').props.accessibilityState).toMatchObject({ selected: true }));

    fireEvent.changeText(screen.getByTestId('rehearsal-edit-location'), '稽古場B');
    await waitFor(() => expect(screen.getByTestId('rehearsal-edit-location').props.value).toBe('稽古場B'));
    fireEvent.press(screen.getByTestId('rehearsal-edit-submit'));

    await waitFor(() => {
      const updateCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]) => String(url).endsWith('/rehearsals/rehearsal-2') && init?.method === 'PUT'
      );
      expect(updateCall).toBeDefined();

      const body = JSON.parse(updateCall![1].body as string);
      expect(body.location).toBe('稽古場B');
      expect(body.activity_type).toBe('SELF_PRACTICE');
      expect(body.description).toBe('飲み物と着替えを持参してください');
    });
    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-2'));
  });
});
