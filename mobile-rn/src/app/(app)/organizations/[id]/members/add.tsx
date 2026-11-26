import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect } from 'react';

/** Legacy route: member addition is now inline in Organization メンバー管理,
 * matching Production's participant management flow. */
export default function OrganizationMembersAddLegacyRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  useEffect(() => { if (id) router.replace(`/organizations/${id}/members`); }, [id, router]);
  return null;
}
