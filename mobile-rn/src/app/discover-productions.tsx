import { useQuery } from '@tanstack/react-query';
import { useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Radius, Spacing } from '@/constants/theme';
import { searchPublicProductions } from '@/features/production/api';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Web β版: real search (公演・活動検索), backed by the now-real
 * GET /productions/search (public, unauthenticated - published
 * Productions only). Previously a "準備中" placeholder.
 */
export default function DiscoverProductionsScreen() {
  const router = useRouter();
  const [text, setText] = useState('');
  const [submitted, setSubmitted] = useState('');

  const query = useQuery({
    queryKey: ['search-productions', submitted],
    queryFn: () => searchPublicProductions(submitted),
    enabled: submitted.length > 0,
  });

  return (
    <AppShell scroll>
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText type="title" style={styles.title}>
          公演・活動を探す
        </ThemedText>

        <ThemedTextInput
          testID="discover-productions-input"
          placeholder="公演・活動名で検索"
          value={text}
          onChangeText={setText}
          onSubmitEditing={() => setSubmitted(text.trim())}
          returnKeyType="search"
          style={styles.input}
        />
        <TouchableOpacity testID="discover-productions-search" onPress={() => setSubmitted(text.trim())} style={styles.searchButton}>
          <ThemedText style={styles.searchButtonText}>検索する</ThemedText>
        </TouchableOpacity>

        {query.isFetching && <ActivityIndicator testID="discover-productions-loading" />}
        {query.isError && <ThemedText testID="discover-productions-error">{getErrorMessage(query.error)}</ThemedText>}

        {submitted.length > 0 && !query.isFetching && !query.isError && (query.data?.length ?? 0) === 0 && (
          <ThemedText testID="discover-productions-empty" themeColor="textSecondary">
            公演・活動が見つかりませんでした。
          </ThemedText>
        )}

        {(query.data?.length ?? 0) > 0 && (
          <ThemedView testID="discover-productions-results" style={styles.list}>
            {query.data?.map((production) => (
              <TouchableOpacity
                key={production.id}
                testID={`discover-production-${production.id}`}
                style={styles.resultCard}
                onPress={() => router.push(`/${production.organization.slug}/${production.slug}` as Href)}
              >
                {production.title_heading && (
                  <ThemedText type="small" themeColor="textSecondary">
                    {production.title_heading}
                  </ThemedText>
                )}
                <ThemedText type="smallBold">{production.name}</ThemedText>
                <ThemedText type="small" themeColor="textSecondary">
                  {production.organization.name}
                </ThemedText>
              </TouchableOpacity>
            ))}
          </ThemedView>
        )}
      </ScrollView>
    </AppShell>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.three },
  title: { fontSize: 22, lineHeight: 28 },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
  },
  searchButton: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    alignItems: 'center',
  },
  searchButtonText: { fontWeight: '600' },
  list: { gap: Spacing.two },
  resultCard: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.half,
  },
});
