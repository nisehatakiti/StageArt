export declare const DEV_API_BASE_URL: string;
export declare const PROD_API_BASE_URL: string;
export declare const VERIFICATION_API_BASE_URL: string;
export declare const API_BASE_URLS: Readonly<Record<'development' | 'production' | 'verification', string>>;
export declare function resolveApiBaseUrl(env: string): string;
