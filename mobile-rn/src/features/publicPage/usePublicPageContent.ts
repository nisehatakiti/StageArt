import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import type { ApiClient } from '@/api/client';
import { useAuth } from '@/auth/AuthContext';

import type { ContentBlock, PublicPageContent, PublicPageContentAdmin } from './contentTypes';

function basePath(organizationId: string): string {
  return `/organizations/${organizationId}/public-page`;
}

export function fetchPublicPageContent(client: ApiClient, organizationId: string): Promise<PublicPageContentAdmin> {
  return client.get<PublicPageContentAdmin>(`${basePath(organizationId)}/content`);
}

/** The whole layout is replaced (Core SiteStructureAdmin::saveHomepage()). */
export function updatePublicPageContentLayout(client: ApiClient, organizationId: string, blocks: ContentBlock[]): Promise<PublicPageContentAdmin> {
  return client.put<PublicPageContentAdmin>(`${basePath(organizationId)}/content-layout`, { blocks });
}

type ContentFields = Pick<PublicPageContent, 'title' | 'body' | 'status'>;

export function createPublicPageContent(client: ApiClient, organizationId: string, fields: ContentFields): Promise<PublicPageContent> {
  return client.post<PublicPageContent>(`${basePath(organizationId)}/contents`, fields);
}

export function updatePublicPageContent(client: ApiClient, organizationId: string, contentId: string, fields: ContentFields): Promise<PublicPageContent> {
  return client.put<PublicPageContent>(`${basePath(organizationId)}/contents/${contentId}`, fields);
}

export function deletePublicPageContent(client: ApiClient, organizationId: string, contentId: string): Promise<void> {
  return client.delete<void>(`${basePath(organizationId)}/contents/${contentId}`);
}

export function publicPageContentKey(organizationId: string | undefined) {
  return ['public-page-content', 'organization', organizationId] as const;
}

/**
 * コンテンツ tab of one Organization. Every change refetches the tab's data
 * (the layout shows a deleted 自由コンテンツ as an empty column) and drops
 * the cached public Organization page, so it shows the change when opened.
 */
export function usePublicPageContent(organizationId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();
  const key = publicPageContentKey(organizationId);
  const id = organizationId as string;

  const query = useQuery({
    queryKey: key,
    queryFn: () => fetchPublicPageContent(apiClient, id),
    enabled: !!organizationId,
  });

  function refresh() {
    void queryClient.invalidateQueries({ queryKey: key });
    void queryClient.invalidateQueries({ queryKey: ['public-organization'] });
  }

  const saveLayout = useMutation({
    mutationFn: (blocks: ContentBlock[]) => updatePublicPageContentLayout(apiClient, id, blocks),
    onSuccess: (saved) => {
      queryClient.setQueryData(key, saved);
      void queryClient.invalidateQueries({ queryKey: ['public-organization'] });
    },
  });

  const saveContent = useMutation({
    mutationFn: ({ contentId, fields }: { contentId: string | null; fields: ContentFields }) =>
      contentId === null ? createPublicPageContent(apiClient, id, fields) : updatePublicPageContent(apiClient, id, contentId, fields),
    onSuccess: refresh,
  });

  const removeContent = useMutation({
    mutationFn: (contentId: string) => deletePublicPageContent(apiClient, id, contentId),
    onSuccess: refresh,
  });

  return { query, saveLayout, saveContent, removeContent };
}
