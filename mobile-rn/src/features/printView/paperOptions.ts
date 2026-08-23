import type { Orientation, PaperSize } from './api';

export type PaperOption = { key: string; label: string; paperSize: PaperSize; orientation: Orientation };

/**
 * Mirrors the Flutter app's existing Print View screen (mobile/lib/src/
 * features/print/print_view_screen.dart), which exposes exactly these 4
 * named choices against the same Backend endpoint/params - orientation
 * is baked into each choice rather than a separate toggle, matching
 * that reference's `canChangeOrientation: false`. No option is added
 * here that the Backend does not already accept (paperSize: A4/A3,
 * orientation: portrait/landscape - see PrintLayoutOptions.php).
 */
export const PAPER_OPTIONS: PaperOption[] = [
  { key: 'a4-portrait', label: 'A4 縦', paperSize: 'A4', orientation: 'portrait' },
  { key: 'a4-landscape', label: 'A4 横', paperSize: 'A4', orientation: 'landscape' },
  { key: 'a3-portrait', label: 'A3 縦', paperSize: 'A3', orientation: 'portrait' },
  { key: 'a3-landscape', label: 'A3 横', paperSize: 'A3', orientation: 'landscape' },
];
