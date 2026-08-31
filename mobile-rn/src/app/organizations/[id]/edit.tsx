import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';
import { useQueryClient } from '@tanstack/react-query';

import { ApiError } from '@/api/errors';
import { useAuth } from '@/auth/AuthContext';
import { WebLayout } from '@/components/web/WebLayout';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { updateOrganization } from '@/features/organization/api';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { getErrorMessage } from '@/utils/errorMessage';
import { isValidSlug } from '@/utils/slug';

/**
 * StageArt Web版 団体管理 Phase: 団体情報編集. Reuses the exact same
 * `PUT /organizations/{id}` (updateOrganization()) the onboarding flow's
 * own publish step already calls - no new Backend/API work, only a Web
 * form around an endpoint that already accepts name/slug/description
 * (confirmed by reading UpdateOrganizationUseCase.php: name/type/
 * description/status are always applied, slug only when provided).
 * `type` has no editable UI anywhere in this app yet (no existing screen
 * exposes it as a concept to users) - it is read from the current
 * Organization and passed straight through unchanged, never surfaced as
 * a field here, so this save can never silently blank it out.
 *
 * Ownership is enforced server-side (UpdateOrganizationUseCase: "Only an
 * Organization Owner can update this Organization", 403 otherwise) -
 * `current_person_role` is read here only to skip straight to that same
 * message without a round-trip when it is already known locally, not as
 * this screen's own access decision.
 */
export default function OrganizationEditScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { apiClient } = useAuth();
  const router = useRouter();
  const queryClient = useQueryClient();
  const organizationsQuery = useOrganizations();

  const organization = organizationsQuery.data?.find((candidate) => candidate.id === id) ?? null;
  const isOwner = organization?.current_person_role === 'OWNER';

  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [description, setDescription] = useState('');
  const [initialized, setInitialized] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    if (organization && !initialized) {
      setName(organization.name);
      setSlug(organization.slug ?? '');
      setDescription(organization.description ?? '');
      setInitialized(true);
    }
  }, [organization, initialized]);

  // Only require a valid Slug format when the user is actually changing
  // it (matches handleSubmit's own "only send slug when it differs"
  // rule below) - otherwise an Organization whose stored Slug the
  // Backend itself would consider unusual (or simply untouched by this
  // form) could never save any OTHER field either.
  const slugChanged = slug !== (organization?.slug ?? '');
  const slugValid = isValidSlug(slug);
  const canSubmit = !!name.trim() && (!slugChanged || slugValid) && !submitting;

  async function handleSubmit() {
    if (!organization || !canSubmit) {
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);

    try {
      await updateOrganization(apiClient, organization.id, {
        name: name.trim(),
        type: organization.type,
        description: description.trim() ? description.trim() : null,
        status: organization.status,
        slug: slug !== (organization.slug ?? '') ? slug : undefined,
      });
      await queryClient.invalidateQueries({ queryKey: ['organizations'] });
      router.replace({ pathname: `/organizations/${organization.id}`, params: { saved: '1' } } as Href);
    } catch (error) {
      if (error instanceof ApiError && error.code === 'stageart_organization_slug_taken') {
        setErrorMessage('このSlugは既に使用されています。別のSlugを入力してください。');
      } else if (error instanceof ApiError && error.statusCode === 403) {
        setErrorMessage('この団体を編集する権限がありません（オーナーのみ編集できます）。');
      } else {
        setErrorMessage(getErrorMessage(error));
      }
    } finally {
      setSubmitting(false);
    }
  }

  const breadcrumbs = [
    { label: 'StageArt', href: '/dashboard' as Href },
    { label: '団体', href: '/organizations' as Href },
    { label: organization?.name ?? '...', href: `/organizations/${id}` as Href },
    { label: '団体情報編集' },
  ];

  if (organizationsQuery.isLoading) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" organizationId={id}>
        <ActivityIndicator testID="organization-edit-loading" />
      </WebLayout>
    );
  }

  if (!organization) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" organizationId={id}>
        <ThemedText testID="organization-edit-not-found">この団体が見つからないか、参加していません。</ThemedText>
      </WebLayout>
    );
  }

  if (!isOwner) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" organizationId={id}>
        <ThemedText testID="organization-edit-forbidden">この団体を編集する権限がありません（オーナーのみ編集できます）。</ThemedText>
      </WebLayout>
    );
  }

  return (
    <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" organizationId={id}>
      <ThemedText type="title" style={styles.pageTitle}>
        団体情報編集
      </ThemedText>

      <View style={styles.form}>
        <ThemedText type="small" themeColor="textSecondary">
          団体名
        </ThemedText>
        <ThemedTextInput testID="organization-edit-name" value={name} onChangeText={setName} style={styles.input} />

        <ThemedText type="small" themeColor="textSecondary">
          Slug（公開URL: /{slug || '...'}）
        </ThemedText>
        <ThemedTextInput
          testID="organization-edit-slug"
          value={slug}
          onChangeText={setSlug}
          autoCapitalize="none"
          autoCorrect={false}
          style={styles.input}
        />
        {!slugValid && slug.length > 0 && (
          <ThemedText type="small" style={styles.error}>
            Slugは半角英数字とハイフンのみ、3〜64文字で入力してください。
          </ThemedText>
        )}

        <ThemedText type="small" themeColor="textSecondary">
          説明
        </ThemedText>
        <ThemedTextInput
          testID="organization-edit-description"
          value={description}
          onChangeText={setDescription}
          multiline
          numberOfLines={4}
          style={[styles.input, styles.textarea]}
        />

        {errorMessage && (
          <ThemedText testID="organization-edit-error" style={styles.error}>
            {errorMessage}
          </ThemedText>
        )}

        <View style={styles.actions}>
          <TouchableOpacity
            testID="organization-edit-submit"
            onPress={handleSubmit}
            disabled={!canSubmit}
            style={[styles.button, !canSubmit && styles.buttonDisabled]}
          >
            {submitting ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>保存する</ThemedText>}
          </TouchableOpacity>
          <TouchableOpacity testID="organization-edit-cancel" onPress={() => router.push(`/organizations/${organization.id}` as Href)}>
            <ThemedText type="link">キャンセル</ThemedText>
          </TouchableOpacity>
        </View>
      </View>
    </WebLayout>
  );
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.four },
  form: { gap: Spacing.one, maxWidth: 480 },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
    marginBottom: Spacing.two,
  },
  textarea: { minHeight: 100, textAlignVertical: 'top' },
  error: { color: '#a6483a', marginBottom: Spacing.two },
  actions: { flexDirection: 'row', alignItems: 'center', gap: Spacing.four, marginTop: Spacing.two },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
});
