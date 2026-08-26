import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useMemo, useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { ApiError } from '@/api/errors';
import { useAuth } from '@/auth/AuthContext';
import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { fetchProjects } from '@/features/organization/api';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import { createProduction, updateProduction } from '@/features/production/api';
import { useQuery } from '@tanstack/react-query';
import { getErrorMessage } from '@/utils/errorMessage';
import { isValidSlug, suggestSlug } from '@/utils/slug';

type CreatedProduction = { id: string; name: string; slug: string; publishedAt: string | null };

/**
 * StageArt Web First Phase 2: name+slug only this Phase (venue/cast/
 * ticket/rehearsal scheduling are explicitly deferred). `project_id` is
 * resolved client-side from the Organization id in the route, the same
 * way useOrganizationProductions() already does (Production has no
 * direct organization_id - see src/types/api.ts's Project docblock) -
 * this screen only ever created that Project transparently one step
 * earlier (organizations/create.tsx), so exactly one should exist here.
 */
export default function CreateProductionScreen() {
  const { id: organizationId } = useLocalSearchParams<{ id: string }>();
  const { apiClient } = useAuth();
  const router = useRouter();

  const organizationsQuery = useOrganizations();
  const currentPersonQuery = useCurrentPerson();
  const projectsQuery = useQuery({
    queryKey: ['projects'],
    queryFn: () => fetchProjects(apiClient),
  });

  const organization = organizationsQuery.data?.find((candidate) => candidate.id === organizationId) ?? null;
  const project = useMemo(
    () => projectsQuery.data?.find((candidate) => candidate.organization_id === organizationId) ?? null,
    [projectsQuery.data, organizationId]
  );

  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [slugEditedManually, setSlugEditedManually] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [created, setCreated] = useState<CreatedProduction | null>(null);
  const [publishing, setPublishing] = useState(false);

  function handleNameChange(value: string) {
    setName(value);
    if (!slugEditedManually) {
      setSlug(suggestSlug(value, 'show'));
    }
  }

  function handleSlugChange(value: string) {
    setSlugEditedManually(true);
    setSlug(value);
  }

  const slugValid = isValidSlug(slug);
  const primaryManagerPersonId = currentPersonQuery.data?.id;
  const canSubmit = !!name.trim() && slugValid && !!project && !!primaryManagerPersonId;

  async function handleSubmit() {
    if (!canSubmit || !project || !primaryManagerPersonId) {
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);

    try {
      const production = await createProduction(apiClient, project.id, name.trim(), slug, primaryManagerPersonId);
      setCreated({ id: production.id, name: production.name, slug: production.slug ?? slug, publishedAt: production.published_at });
    } catch (error) {
      if (error instanceof ApiError && error.code === 'stageart_production_slug_taken') {
        setErrorMessage('このSlugは既に使用されています。別のSlugを入力してください。');
      } else {
        setErrorMessage(getErrorMessage(error));
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function handlePublish() {
    if (!created) {
      return;
    }

    setPublishing(true);
    setErrorMessage(null);

    try {
      const production = await updateProduction(apiClient, created.id, { name: created.name, published: true });
      setCreated({ ...created, publishedAt: production.published_at });
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    } finally {
      setPublishing(false);
    }
  }

  if (created) {
    const publicPath = organization?.slug ? `/${organization.slug}/${created.slug}` : null;

    return (
      <AppShell scroll>
        <ScrollView contentContainerStyle={styles.container}>
          <ThemedText type="title" style={styles.title}>
            公演・活動を作成しました
          </ThemedText>
          <ThemedText style={styles.body}>「{created.name}」を作成しました。まだ非公開です。</ThemedText>

          {errorMessage && (
            <ThemedText testID="create-production-publish-error" style={styles.error}>
              {errorMessage}
            </ThemedText>
          )}

          {!created.publishedAt ? (
            <TouchableOpacity
              testID="create-production-publish"
              onPress={handlePublish}
              disabled={publishing}
              style={[styles.button, publishing && styles.buttonDisabled]}
            >
              {publishing ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>公演・活動を公開する</ThemedText>}
            </TouchableOpacity>
          ) : (
            publicPath && (
              <TouchableOpacity
                testID="create-production-view-public-page"
                onPress={() => router.push(publicPath as Href)}
                style={styles.buttonSecondary}
              >
                <ThemedText style={styles.buttonSecondaryText}>公開ページを見る（{publicPath}）</ThemedText>
              </TouchableOpacity>
            )
          )}

          <TouchableOpacity testID="create-production-done" onPress={() => router.replace('/home')}>
            <ThemedText type="link" style={styles.linkCentered}>
              Homeへ戻る
            </ThemedText>
          </TouchableOpacity>
        </ScrollView>
      </AppShell>
    );
  }

  return (
    <AppShell scroll>
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText type="title" style={styles.title}>
          公演・活動を作る
        </ThemedText>
        <ThemedText themeColor="textSecondary" style={styles.body}>
          公演・活動名と公開URL用のSlugを入力してください。Slugはあとから変更できます。
        </ThemedText>

        <ThemedText type="small" themeColor="textSecondary">
          公演・活動名
        </ThemedText>
        <ThemedTextInput
          testID="create-production-name"
          placeholder="公演・活動名"
          value={name}
          onChangeText={handleNameChange}
          style={styles.input}
        />

        <ThemedText type="small" themeColor="textSecondary">
          Slug（公開URL: /o/{organization?.slug ?? '...'}/{slug || '...'}）
        </ThemedText>
        <ThemedTextInput
          testID="create-production-slug"
          placeholder="slug"
          value={slug}
          onChangeText={handleSlugChange}
          autoCapitalize="none"
          autoCorrect={false}
          style={styles.input}
        />
        {!slugValid && slug.length > 0 && (
          <ThemedText type="small" style={styles.error}>
            Slugは半角英数字とハイフンのみ、3〜64文字で入力してください。
          </ThemedText>
        )}

        {errorMessage && (
          <ThemedText testID="create-production-error" style={styles.error}>
            {errorMessage}
          </ThemedText>
        )}

        <TouchableOpacity
          testID="create-production-submit"
          onPress={handleSubmit}
          disabled={submitting || !canSubmit}
          style={[styles.button, (submitting || !canSubmit) && styles.buttonDisabled]}
        >
          {submitting ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>公演・活動を作成する</ThemedText>}
        </TouchableOpacity>
      </ScrollView>
    </AppShell>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.two },
  title: { fontSize: 22, lineHeight: 28 },
  body: { marginBottom: Spacing.two },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
    marginBottom: Spacing.two,
  },
  error: { color: '#a6483a' },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.three,
    alignItems: 'center',
    marginTop: Spacing.two,
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  buttonSecondary: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.three,
    alignItems: 'center',
    marginTop: Spacing.two,
  },
  buttonSecondaryText: { color: BrandColors.warmAmber, fontWeight: '600' },
  linkCentered: { textAlign: 'center', marginTop: Spacing.three },
});
