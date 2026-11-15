import { StyleSheet, View, type ViewProps } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Radius, Spacing } from '@/constants/theme';

/**
 * StageArt 団体情報画面/メンバー管理 UI/UX統一: a titled card wrapper so a
 * management screen reads as "カード/セクション" (一目で「何を設定する場所
 * か」が分かる) instead of a single long, undifferentiated form. Lives
 * here (not under organizations/[id]/*) specifically so Production's own
 * management screens can adopt the same card treatment later without a
 * second, duplicate component - this round only introduces it, it does
 * not retrofit Production (out of scope here, see this round's report).
 */
export function ManagementCard({ title, description, children, style, testID, ...rest }: ViewProps & { title: string; description?: string; children: React.ReactNode }) {
  return (
    <View style={[styles.card, style]} testID={testID} {...rest}>
      <ThemedText type="subtitle" style={styles.title}>
        {title}
      </ThemedText>
      {description && (
        <ThemedText type="small" themeColor="textSecondary" style={styles.description}>
          {description}
        </ThemedText>
      )}
      <View style={styles.body}>{children}</View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    backgroundColor: '#fff',
    padding: Spacing.four,
    marginBottom: Spacing.three,
    maxWidth: 720,
  },
  title: { fontSize: 20, lineHeight: 26, marginBottom: Spacing.half },
  description: { marginBottom: Spacing.two },
  body: { gap: Spacing.one },
});
