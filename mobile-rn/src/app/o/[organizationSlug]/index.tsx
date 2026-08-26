import { Redirect, useLocalSearchParams, type Href } from 'expo-router';

/**
 * StageArt Public Page Architecture phase (docs/03-PublicPageURLAndPublicationSchedule.md,
 * §URL互換性): the Organization Public Page moved from this `/o/{slug}`
 * prefix to the app's URL root (`/{organization-slug}`), matching
 * `stageart.top`'s intended path shape. This route is kept as a
 * redirect only, so any existing `/o/{slug}` link (shared before this
 * change, bookmarked, etc.) keeps working rather than breaking.
 */
export default function LegacyOrganizationPublicPageRedirect() {
  const { organizationSlug } = useLocalSearchParams<{ organizationSlug: string }>();

  return <Redirect href={`/${organizationSlug}` as Href} />;
}
