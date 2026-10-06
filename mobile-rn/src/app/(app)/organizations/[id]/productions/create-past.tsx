import { useEffect } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';

/**
 * Legacy compatibility route only. Past productions use the same Production
 * creation form as every other Production/Activity; there is intentionally no
 * separate "past production" entry point.
 */
export default function OrganizationProductionsCreatePastScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();

  useEffect(() => {
    if (id) {
      router.replace(`/organizations/${id}/productions/create`);
    }
  }, [id, router]);

  return null;
}
