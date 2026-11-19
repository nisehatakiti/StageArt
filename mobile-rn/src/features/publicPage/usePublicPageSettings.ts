import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchPublicPageSettings, updatePublicPageSettings, uploadPublicPageHeroImage } from './api';
import type { HeroSettings, PublicPageSettings, PublicPageSite, ThemeSettings } from './types';

export function publicPageSettingsKey(site: PublicPageSite, ownerId: string | undefined) {
  return ['public-page-settings', site, ownerId] as const;
}

/**
 * Settings of one page (organization or production). Saving writes the
 * response straight into this cache, and drops the cached public page so
 * it is fetched again with the new look the next time it is opened.
 */
export function usePublicPageSettings(site: PublicPageSite, ownerId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();
  const key = publicPageSettingsKey(site, ownerId);

  const query = useQuery({
    queryKey: key,
    queryFn: () => fetchPublicPageSettings(apiClient, site, ownerId as string),
    enabled: !!ownerId,
  });

  function applySaved(saved: PublicPageSettings) {
    queryClient.setQueryData(key, saved);
    void queryClient.invalidateQueries({ queryKey: [site === 'organization' ? 'public-organization' : 'public-production'] });
  }

  const save = useMutation({
    mutationFn: (fields: { theme: ThemeSettings; hero: Omit<HeroSettings, 'custom_image_url'> }) =>
      updatePublicPageSettings(apiClient, site, ownerId as string, fields),
    onSuccess: applySaved,
  });

  const uploadHeroImage = useMutation({
    mutationFn: ({ file, fileName }: { file: Blob; fileName: string }) =>
      uploadPublicPageHeroImage(apiClient, site, ownerId as string, file, fileName),
    onSuccess: applySaved,
  });

  return { query, save, uploadHeroImage };
}
