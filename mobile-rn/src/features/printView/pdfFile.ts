import { File, Paths } from 'expo-file-system';

/**
 * expo-print's printAsync({ uri }) and expo-sharing's shareAsync(url)
 * both require a local file:// URI, not raw bytes - see this Phase's
 * report §07 for the exact SDK v57 API confirmed from
 * docs.expo.dev/versions/v57.0.0 before writing this (AGENTS.md).
 * Writes into the app cache directory (Paths.cache) since this file is
 * disposable output, not app data that needs to persist.
 */
export function writePdfToCacheFile(bytes: ArrayBuffer, filename: string): string {
  const file = new File(Paths.cache, filename);
  file.write(new Uint8Array(bytes));
  return file.uri;
}
