import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';
import { useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';
import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { ApiError } from '@/api/errors';
import { createOrganization, createProject, updateOrganization } from '@/features/organization/api';
import { useOrganizationContext } from '@/features/organization/OrganizationContext';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { getErrorMessage } from '@/utils/errorMessage';
import { isValidSlug, suggestSlug } from '@/utils/slug';

type CreatedOrganization = { id: string; name: string; slug: string; publishedAt: string | null };

/**
 * StageArt Web First Phase 2: the minimal 団体作成 onboarding this
 * Phase's plan calls for - name+slug on one screen (per the Blueprint's
 * "団体名とSlugは同じオンボーディング画面で入力する" requirement), not the
 * full onboarding form (会場/出演者/チケット/稽古日程 are explicitly
 * deferred). Creates the Organization, then transparently creates its
 * (user-invisible) Project bridge - see createProject()'s own docblock -
 * so "公演・活動を作る" is immediately available without the user ever
 * seeing Project as a concept.
 *
 * StageArt mobile-rn 受け入れテスト3で発見: `created` was purely local
 * `useState`, tied to nothing in the URL - a real browser reload of this
 * screen re-mounted it from scratch and silently reset to the blank
 * "団体を作る" form, discarding all confirmation that the Organization
 * had just been created and published (mirrors the identical bug found
 * and fixed in organizations/[id]/productions/create.tsx - see that
 * file's own docblock for the full explanation). Fixed the same way:
 * the created Organization's id is written into this screen's own
 * `createdId` route param the moment creation succeeds, and the
 * "created" view is derived from the already-fetched `useOrganizations()`
 * list (this app has no single-Organization-by-id GET endpoint, but the
 * Membership-scoped list this screen's own creation flow already
 * invalidates is sufficient) whenever local `created` state is absent.
 */
export default function CreateOrganizationScreen() {
  const { apiClient } = useAuth();
  const router = useRouter();
  const queryClient = useQueryClient();
  const { selectOrganization } = useOrganizationContext();
  const { createdId } = useLocalSearchParams<{ createdId?: string }>();
  const organizationsQuery = useOrganizations();

  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [slugEditedManually, setSlugEditedManually] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [created, setCreated] = useState<CreatedOrganization | null>(null);
  const [publishing, setPublishing] = useState(false);

  const effectiveCreated: CreatedOrganization | null =
    created ??
    (() => {
      const match = organizationsQuery.data?.find((candidate) => candidate.id === createdId);
      return match ? { id: match.id, name: match.name, slug: match.slug ?? '', publishedAt: match.published_at } : null;
    })();
  const restoringFromReload = !created && !!createdId && organizationsQuery.isLoading;

  function handleNameChange(value: string) {
    setName(value);
    if (!slugEditedManually) {
      setSlug(suggestSlug(value, 'team'));
    }
  }

  function handleSlugChange(value: string) {
    setSlugEditedManually(true);
    setSlug(value);
  }

  const slugValid = isValidSlug(slug);

  async function handleSubmit() {
    if (!name.trim() || !slugValid) {
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);

    try {
      const organization = await createOrganization(apiClient, name.trim(), slug);
      await createProject(apiClient, organization.id);
      selectOrganization(organization.id);
      // Without this, Home's GET /organizations list (queryKey ['organizations'])
      // stays whatever it fetched before this screen was reached - for a
      // Person's very first Organization that was an empty list, so
      // hasOrganizations stayed false and the entire "団体の管理" section
      // (including this brand-new Organization) stayed invisible on Home
      // until an unrelated full reload happened to refetch it. A real bug,
      // not a hypothetical - confirmed by reading useOrganizations()/home.tsx
      // together, matching the reported "登録したのに管理画面に辿り着けない" symptom.
      await queryClient.invalidateQueries({ queryKey: ['organizations'] });
      setCreated({ id: organization.id, name: organization.name, slug: organization.slug ?? slug, publishedAt: organization.published_at });
      router.setParams({ createdId: organization.id });
    } catch (error) {
      if (error instanceof ApiError && error.code === 'stageart_organization_slug_taken') {
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
      // type/description are always null at this point - createOrganization()
      // (the only call that can precede this one) never sets them - so
      // passing them through as null here is accurate, not a wipe of real
      // data. See updateOrganization()'s own docblock for why they must be
      // sent at all.
      const organization = await updateOrganization(apiClient, effectiveCreated.id, {
        name: effectiveCreated.name,
        type: null,
        description: null,
        status: 'ACTIVE',
        published: true,
      });
      await queryClient.invalidateQueries({ queryKey: ['organizations'] });
      setCreated({ ...effectiveCreated, publishedAt: organization.published_at });
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
    const created = effectiveCreated;
    return (
      <AppShell scroll>
        <ScrollView contentContainerStyle={styles.container}>
          <ThemedText type="title" style={styles.title}>
            団体を作成しました
          </ThemedText>
          <ThemedText style={styles.body}>「{created.name}」を作成しました。まだ非公開です。</ThemedText>

          {errorMessage && (
            <ThemedText testID="create-organization-publish-error" style={styles.error}>
              {errorMessage}
            </ThemedText>
          )}

          {!created.publishedAt ? (
            <TouchableOpacity
              testID="create-organization-publish"
              onPress={handlePublish}
              disabled={publishing}
              style={[styles.button, publishing && styles.buttonDisabled]}
            >
              {publishing ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>団体を公開する</ThemedText>}
            </TouchableOpacity>
          ) : (
            <TouchableOpacity
              testID="create-organization-view-public-page"
              onPress={() => router.push(`/${created.slug}` as Href)}
              style={styles.buttonSecondary}
            >
              <ThemedText style={styles.buttonSecondaryText}>公開ページを見る（/o/{created.slug}）</ThemedText>
            </TouchableOpacity>
          )}

          <TouchableOpacity
            testID="create-organization-create-production"
            onPress={() => router.push(`/organizations/${created.id}/productions/create` as Href)}
            style={styles.buttonSecondary}
          >
            <ThemedText style={styles.buttonSecondaryText}>公演・活動を作る</ThemedText>
          </TouchableOpacity>

          <TouchableOpacity
            testID="create-organization-invite"
            onPress={() => router.push(`/organizations/${created.id}/invite` as Href)}
            style={styles.buttonSecondary}
          >
            <ThemedText style={styles.buttonSecondaryText}>メンバーを招待・管理する</ThemedText>
          </TouchableOpacity>

          <TouchableOpacity testID="create-organization-done" onPress={() => router.replace('/home')}>
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
          団体を作る
        </ThemedText>
        <ThemedText themeColor="textSecondary" style={styles.body}>
          団体名と公開URL用のSlugを入力してください。Slugはあとから変更できます。
        </ThemedText>

        <ThemedText type="small" themeColor="textSecondary">
          団体名
        </ThemedText>
        <ThemedTextInput
          testID="create-organization-name"
          placeholder="団体名"
          value={name}
          onChangeText={handleNameChange}
          style={styles.input}
        />

        <ThemedText type="small" themeColor="textSecondary">
          Slug（公開URL: /o/{slug || '...'}）
        </ThemedText>
        <ThemedTextInput
          testID="create-organization-slug"
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
          <ThemedText testID="create-organization-error" style={styles.error}>
            {errorMessage}
          </ThemedText>
        )}

        <TouchableOpacity
          testID="create-organization-submit"
          onPress={handleSubmit}
          disabled={submitting || !name.trim() || !slugValid}
          style={[styles.button, (submitting || !name.trim() || !slugValid) && styles.buttonDisabled]}
        >
          {submitting ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>団体を作成する</ThemedText>}
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
