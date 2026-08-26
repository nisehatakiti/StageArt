import { Redirect, useLocalSearchParams, type Href } from 'expo-router';

/**
 * StageArt Public Page Architecture phase - see the sibling
 * `index.tsx` in this directory for the full rationale. Production
 * Public Pages moved from `/o/{organization-slug}/{production-slug}`
 * to `/{organization-slug}/{production-slug}`.
 */
export default function LegacyProductionPublicPageRedirect() {
  const { organizationSlug, productionSlug } = useLocalSearchParams<{
    organizationSlug: string;
    productionSlug: string;
  }>();

  return <Redirect href={`/${organizationSlug}/${productionSlug}` as Href} />;
}
