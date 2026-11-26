import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect } from 'react';

/** Legacy route: delegate assignment is now inline in Organization メンバー管理,
 * matching Production's participant/delegate table flow. */
export default function OrganizationMembersDelegateLegacyRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  useEffect(() => { if (id) router.replace(`/organizations/${id}/members`); }, [id, router]);
  return null;
}
