import type { ApiClient } from '@/api/client';

import type { HeroSettings, PublicPageSettings, PublicPageSite, ThemeSettings } from './types';

function basePath(site: PublicPageSite, ownerId: string): string {
  return site === 'organization' ? `/organizations/${ownerId}/public-page` : `/productions/${ownerId}/public-page`;
}

export function fetchPublicPageSettings(client: ApiClient, site: PublicPageSite, ownerId: string): Promise<PublicPageSettings> {
  return client.get<PublicPageSettings>(basePath(site, ownerId));
}

/** The own hero image is not sent here - it is set by uploadPublicPageHeroImage(). */
export function updatePublicPageSettings(
  client: ApiClient,
  site: PublicPageSite,
  ownerId: string,
  fields: { theme: ThemeSettings; hero: Omit<HeroSettings, 'custom_image_url'> }
): Promise<PublicPageSettings> {
  return client.put<PublicPageSettings>(basePath(site, ownerId), fields);
}

/** POST .../public-page/hero-image (multipart), stored via WordPress's wp_handle_upload() like the logo. */
export function uploadPublicPageHeroImage(
  client: ApiClient,
  site: PublicPageSite,
  ownerId: string,
  file: Blob,
  fileName: string
): Promise<PublicPageSettings> {
  return client.postFile<PublicPageSettings>(`${basePath(site, ownerId)}/hero-image`, 'file', file, fileName);
}
