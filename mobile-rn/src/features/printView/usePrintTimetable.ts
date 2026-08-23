import { useMutation } from '@tanstack/react-query';
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';

import { useAuth } from '@/auth/AuthContext';

import { fetchTimetablePrintPdf, type Orientation, type PaperSize } from './api';
import { writePdfToCacheFile } from './pdfFile';

export type PrintAction = 'share' | 'print';

export type PrintTimetableParams = {
  paperSize: PaperSize;
  orientation: Orientation;
  action: PrintAction;
};

/**
 * §15: implements OS標準共有 (expo-sharing) and 印刷 (expo-print), both
 * against the same downloaded PDF file - a `useMutation`, not a
 * `useQuery`, since this is a user-triggered action with a side effect
 * (opening a native Share/Print sheet), not passive display data to
 * keep cached (see this Phase's report §07 for why).
 */
export function usePrintTimetable(productionId: string | undefined) {
  const { apiClient } = useAuth();

  return useMutation({
    mutationFn: async (params: PrintTimetableParams) => {
      if (!productionId) {
        throw new Error('productionId is required');
      }

      const bytes = await fetchTimetablePrintPdf(apiClient, productionId, params);
      const uri = writePdfToCacheFile(bytes, `${productionId}-timetable-print.pdf`);

      if (params.action === 'print') {
        await Print.printAsync({ uri });
        return uri;
      }

      const isAvailable = await Sharing.isAvailableAsync();
      if (!isAvailable) {
        throw new Error('この端末では共有機能を利用できません。');
      }

      await Sharing.shareAsync(uri, { mimeType: 'application/pdf', dialogTitle: 'タイムテーブルを共有' });
      return uri;
    },
  });
}
