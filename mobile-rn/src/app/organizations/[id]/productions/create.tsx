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
import { useProduction } from '@/features/production/useProductions';
import { useQuery, useQueryClient } from '@tanstack/react-query';
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
 *
 * StageArt mobile-rn 受け入れテスト3で発見: `created` was purely local
 * `useState`, tied to nothing in the URL - a real browser reload of this
 * exact screen (via Playwright, not just code review) re-mounted the
 * component from scratch and silently dropped back to the blank "作る"
 * form, discarding all confirmation that the Production had just been
 * created and published (the server-side data was always correct - only
 * this screen's own memory of it was not). Fixed by writing the created
 * Production's id into this screen's own `createdId` route param
 * (`router.setParams`, no navigation/history entry added) the moment
 * creation succeeds, and deriving the "created" view from a real
 * `useProduction(createdId)` fetch whenever local `created` state itself
 * is absent (i.e. exactly the reload case) - a reload now reconstructs
 * the identical confirmation screen from the same source of truth the
 * public page itself reads, instead of resetting to a blank form.
 */
export default function CreateProductionScreen() {
  const { id: organizationId, createdId } = useLocalSearchParams<{ id: string; createdId?: string }>();
  const { apiClient } = useAuth();
  const router = useRouter();
  const queryClient = useQueryClient();

  const organizationsQuery = useOrganizations();
  const currentPersonQuery = useCurrentPerson();
  const projectsQuery = useQuery({
    queryKey: ['projects'],
    queryFn: () => fetchProjects(apiClient),
  });
  const createdProductionQuery = useProduction(createdId);

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

  const effectiveCreated: CreatedProduction | null =
    created ??
    (createdProductionQuery.data
      ? {
          id: createdProductionQuery.data.id,
          name: createdProductionQuery.data.name,
          slug: createdProductionQuery.data.slug ?? '',
          publishedAt: createdProductionQuery.data.published_at,
        }
      : null);
  const restoringFromReload = !created && !!createdId && createdProductionQuery.isLoading;

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
      // Same class of bug as organizations/create.tsx's own fix: without
      // this, Home's GET /productions list (queryKey ['productions'])
      // stays stale, so a just-created (and possibly just-published)
      // Production never appears there until an unrelated full reload -
      // easily mistaken for "公開したのに反映されない" even though the
      // publish call itself (handlePublish below) succeeds every time.
      await queryClient.invalidateQueries({ queryKey: ['productions'] });
      setCreated({ id: production.id, name: production.name, slug: production.slug ?? slug, publishedAt: production.published_at });
      router.setParams({ createdId: production.id });
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
    if (!effectiveCreated) {
      return;
    }

    setPublishing(true);
    setErrorMessage(null);

    try {
      // title_heading is always null at this point - this onboarding form
      // never sets it (see updateProduction()'s own docblock for why it
      // must still be sent explicitly, not omitted).
      const production = await updateProduction(apiClient, effectiveCreated.id, {
        name: effectiveCreated.name,
        titleHeading: null,
        published: true,
      });
      await queryClient.invalidateQueries({ queryKey: ['productions'] });
      await queryClient.invalidateQueries({ queryKey: ['production', effectiveCreated.id] });
      setCreated({ ...effectiveCreated, publishedAt: production.published_at });
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    } finally {
      setPublishing(false);
    }
  }

  if (restoringFromReload) {
    return (
      <AppShell scroll>
        <ScrollView contentContainerStyle={styles.container}>
          <ActivityIndicator />
        </ScrollView>
      </AppShell>
    );
  }

  if (effectiveCreated) {
    const publicPath = organization?.slug ? `/${organization.slug}/${effectiveCreated.slug}` : null;
    const created = effectiveCreated;

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
