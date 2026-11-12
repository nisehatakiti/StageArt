import { Platform } from 'react-native';

/**
 * True only in the Web public-site build (EXPO_PUBLIC_STAGEART_SITE=public,
 * inlined by Metro at build time and validated by app.config.ts - see
 * site-build-env.js). Native is never the public site, even if the
 * variable were set by mistake.
 */
export const IS_PUBLIC_SITE_BUILD = Platform.OS === 'web' && process.env.EXPO_PUBLIC_STAGEART_SITE === 'public';
