import { useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { useAuth } from '@/auth/AuthContext';
import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { ApiError } from '@/api/errors';
import { createOrganization, createProject, updateOrganization } from '@/features/organization/api';
import { useOrganizationContext } from '@/features/organization/OrganizationContext';
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
 */
export default function CreateOrganizationScreen() {
  const { apiClient } = useAuth();
  const router = useRouter();
  const { selectOrganization } = useOrganizationContext();

  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [slugEditedManually, setSlugEditedManually] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [created, setCreated] = useState<CreatedOrganization | null>(null);
  const [publishing, setPublishing] = useState(false);

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
      setCreated({ id: organization.id, name: organization.name, slug: organization.slug ?? slug, publishedAt: organization.published_at });
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
    if (!created) {
      return;
    }

    setPublishing(true);
    setErrorMessage(null);

    try {
      const organization = await updateOrganization(apiClient, created.id, { name: created.name, status: 'ACTIVE', published: true });
      setCreated({ ...created, publishedAt: organization.published_at });
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    } finally {
      setPublishing(false);
    }
  }

  if (created) {
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
