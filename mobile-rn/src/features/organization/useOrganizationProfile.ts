import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';
import type { OrganizationExternalLink, OrganizationSNS } from '@/types/api';

import {
  createOrganizationExternalLink,
  createOrganizationSns,
  deleteOrganizationExternalLink,
  deleteOrganizationSns,
  fetchOrganizationExternalLinks,
  fetchOrganizationSns,
  updateOrganizationExternalLink,
  updateOrganizationSns,
} from './api';

type SnsFields = { service: string; account_identifier: string; profile_url?: string | null; display_name?: string | null; display_order?: number; visibility?: boolean };
type LinkFields = { label: string; url: string; display_order?: number; visibility?: boolean };

/** StageArt 団体管理UI整理 "団体情報": SNS CRUD for the management screen
 * (shows hidden links too, unlike the Public Page). */
export function useOrganizationSns(organizationId: string | undefined) {
  const { apiClient, status } = useAuth();
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['organization-sns', organizationId],
    queryFn: () => fetchOrganizationSns(apiClient, organizationId as string),
    enabled: status === 'authenticated' && !!organizationId,
  });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['organization-sns', organizationId] });
  }

  const create = useMutation({
    mutationFn: (fields: SnsFields) => createOrganizationSns(apiClient, organizationId as string, fields),
    onSuccess: invalidate,
  });

  const update = useMutation({
    mutationFn: ({ id, fields }: { id: string; fields: SnsFields }) => updateOrganizationSns(apiClient, id, fields),
    onSuccess: invalidate,
  });

  const remove = useMutation({
    mutationFn: (id: string) => deleteOrganizationSns(apiClient, id),
    onSuccess: invalidate,
  });

  return { query, create, update, remove };
}

/** StageArt 団体管理UI整理 "団体情報": 外部リンク CRUD. */
export function useOrganizationExternalLinks(organizationId: string | undefined) {
  const { apiClient, status } = useAuth();
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['organization-external-links', organizationId],
    queryFn: () => fetchOrganizationExternalLinks(apiClient, organizationId as string),
    enabled: status === 'authenticated' && !!organizationId,
  });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['organization-external-links', organizationId] });
  }

  const create = useMutation({
    mutationFn: (fields: LinkFields) => createOrganizationExternalLink(apiClient, organizationId as string, fields),
    onSuccess: invalidate,
  });

  const update = useMutation({
    mutationFn: ({ id, fields }: { id: string; fields: LinkFields }) => updateOrganizationExternalLink(apiClient, id, fields),
    onSuccess: invalidate,
  });

  const remove = useMutation({
    mutationFn: (id: string) => deleteOrganizationExternalLink(apiClient, id),
    onSuccess: invalidate,
  });

  return { query, create, update, remove };
}

export type { OrganizationExternalLink, OrganizationSNS };
