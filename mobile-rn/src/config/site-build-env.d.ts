export type StageArtSiteBuild = 'app' | 'public';

export const SITE_BUILD_APP: 'app';
export const SITE_BUILD_PUBLIC: 'public';

export function resolveSiteBuild(value: string | undefined, options?: { easBuildPlatform?: string }): StageArtSiteBuild;
