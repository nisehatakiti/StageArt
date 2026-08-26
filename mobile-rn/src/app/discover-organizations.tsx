import { useQuery } from '@tanstack/react-query';
import { useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Radius, Spacing } from '@/constants/theme';
import { searchPublicOrganizations } from '@/features/organization/api';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Web β版: real search (団体検索), backed by the now-real
 * GET /organizations/search (public, unauthenticated - published
 * Organizations only). Previously a "準備中" placeholder, from before
 * this endpoint existed.
 */
export default function DiscoverOrganizationsScreen() {
  const router = useRouter();
  const [text, setText] = useState('');
  const [submitted, setSubmitted] = useState('');

  const query = useQuery({
    queryKey: ['search-organizations', submitted],
    queryFn: () => searchPublicOrganizations(submitted),
    enabled: submitted.length > 0,
  });

  return (
    <AppShell scroll>
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText type="title" style={styles.title}>
          団体を探す
        </ThemedText>

        <ThemedTextInput
          testID="discover-organizations-input"
          placeholder="団体名で検索"
          value={text}
          onChangeText={setText}
          onSubmitEditing={() => setSubmitted(text.trim())}
          returnKeyType="search"
          style={styles.input}
        />
        <TouchableOpacity testID="discover-organizations-search" onPress={() => setSubmitted(text.trim())} style={styles.searchButton}>
          <ThemedText style={styles.searchButtonText}>検索する</ThemedText>
        </TouchableOpacity>

        {query.isFetching && <ActivityIndicator testID="discover-organizations-loading" />}
        {query.isError && <ThemedText testID="discover-organizations-error">{getErrorMessage(query.error)}</ThemedText>}

        {submitted.length > 0 && !query.isFetching && !query.isError && (query.data?.length ?? 0) === 0 && (
          <ThemedText testID="discover-organizations-empty" themeColor="textSecondary">
            団体が見つかりませんでした。
          </ThemedText>
        )}

        {(query.data?.length ?? 0) > 0 && (
          <ThemedView testID="discover-organizations-results" style={styles.list}>
            {query.data?.map((organization) => (
              <TouchableOpacity
                key={organization.id}
                testID={`discover-organization-${organization.id}`}
                style={styles.resultCard}
                onPress={() => router.push(`/${organization.slug}` as Href)}
              >
                <ThemedText type="smallBold">{organization.name}</ThemedText>
                {organization.description && (
                  <ThemedText type="small" themeColor="textSecondary" numberOfLines={2}>
                    {organization.description}
                  </ThemedText>
                )}
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
