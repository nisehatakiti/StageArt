import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, Switch, TouchableOpacity, View } from 'react-native';
import { useQueryClient } from '@tanstack/react-query';

import { ApiError } from '@/api/errors';
import { useAuth } from '@/auth/AuthContext';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { updateOrganization } from '@/features/organization/api';
import { useOrganizationExternalLinks, useOrganizationSns } from '@/features/organization/useOrganizationProfile';
import { useOrganizations } from '@/features/organization/useOrganizations';
import type { OrganizationExternalLink, OrganizationSNS } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';
import { isValidSlug } from '@/utils/slug';

const SNS_SERVICES: { value: string; label: string }[] = [
  { value: 'X', label: 'X' },
  { value: 'INSTAGRAM', label: 'Instagram' },
  { value: 'FACEBOOK', label: 'Facebook' },
  { value: 'OTHER', label: 'その他' },
];

/**
 * StageArt 団体管理UI整理: 団体情報 is the single, unified management
 * entry point for everything about the Organization itself - basic
 * info/logo (this round's own additive Domain fields), 公開ページ
 * (published state, already existed), SNS
 * (docs/04-DomainModel/OrganizationSNS.md) and 外部リンク
 * (docs/04-DomainModel/OrganizationPublicSiteStructure.md "OTHER
 * LINKS"). ABOUT/SNS/リンク/公開ページ管理 are no longer separate screens -
 * their content lives here instead (see this round's own report for
 * which old routes were removed).
 *
 * OWNER and ORGANIZATION_MANAGER (代理人) can both edit - matches
 * UpdateOrganizationUseCase/the SNS and external-link Use Cases' own
 * Authorization (both allow OWNER or ORGANIZATION_MANAGER, never a
 * plain MEMBER).
 */
export default function OrganizationEditScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { apiClient } = useAuth();
  const router = useRouter();
  const queryClient = useQueryClient();
  const organizationsQuery = useOrganizations();

  const organization = organizationsQuery.data?.find((candidate) => candidate.id === id) ?? null;
  const role = organization?.current_person_role ?? null;
  const canEdit = role === 'OWNER' || role === 'ORGANIZATION_MANAGER';

  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [description, setDescription] = useState('');
  const [logoUrl, setLogoUrl] = useState('');
  const [published, setPublished] = useState(false);
  const [initialized, setInitialized] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [savedMessage, setSavedMessage] = useState<string | null>(null);

  useEffect(() => {
    if (organization && !initialized) {
      setName(organization.name);
      setSlug(organization.slug ?? '');
      setDescription(organization.description ?? '');
      setLogoUrl(organization.logo_url ?? '');
      setPublished(organization.published_at !== null);
      setInitialized(true);
    }
  }, [organization, initialized]);

  const slugChanged = slug !== (organization?.slug ?? '');
  const slugValid = isValidSlug(slug);
  const canSubmit = !!name.trim() && (!slugChanged || slugValid) && !submitting;

  async function handleSubmit() {
    if (!organization || !canSubmit) {
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);
    setSavedMessage(null);

    try {
      await updateOrganization(apiClient, organization.id, {
        name: name.trim(),
        type: organization.type,
        description: description.trim() ? description.trim() : null,
        status: organization.status,
        slug: slug !== (organization.slug ?? '') ? slug : undefined,
        published,
        logo_url: logoUrl.trim() ? logoUrl.trim() : null,
      });
      await queryClient.invalidateQueries({ queryKey: ['organizations'] });
      setSavedMessage('団体情報を保存しました。');
    } catch (error) {
      if (error instanceof ApiError && error.code === 'stageart_organization_slug_taken') {
        setErrorMessage('このSlugは既に使用されています。別のSlugを入力してください。');
      } else if (error instanceof ApiError && error.statusCode === 403) {
        setErrorMessage('この団体を編集する権限がありません（オーナーまたは代理人のみ編集できます）。');
      } else {
        setErrorMessage(getErrorMessage(error));
      }
    } finally {
      setSubmitting(false);
    }
  }

  if (organizationsQuery.isLoading) {
    return <ActivityIndicator testID="organization-edit-loading" />;
  }

  if (!organization) {
    return <ThemedText testID="organization-edit-not-found">この団体が見つからないか、参加していません。</ThemedText>;
  }

  if (!canEdit) {
    return <ThemedText testID="organization-edit-forbidden">この団体を編集する権限がありません（オーナーまたは代理人のみ編集できます）。</ThemedText>;
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <ThemedText type="title" style={styles.pageTitle}>
        団体情報
      </ThemedText>

      <ThemedView style={styles.section}>
        <ThemedText type="subtitle" style={styles.sectionTitle}>
          基本情報
        </ThemedText>

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
          団体説明
        </ThemedText>
        <ThemedTextInput
          testID="organization-edit-description"
          value={description}
          onChangeText={setDescription}
          multiline
          numberOfLines={4}
          style={[styles.input, styles.textarea]}
        />

        <ThemedText type="small" themeColor="textSecondary">
          団体ロゴ画像URL
        </ThemedText>
        <ThemedTextInput
          testID="organization-edit-logo-url"
          value={logoUrl}
          onChangeText={setLogoUrl}
          autoCapitalize="none"
          autoCorrect={false}
          placeholder="https://..."
          style={styles.input}
        />
      </ThemedView>

      <ThemedView style={styles.section}>
        <ThemedText type="subtitle" style={styles.sectionTitle}>
          公開ページ
        </ThemedText>

        <View style={styles.switchRow}>
          <ThemedText type="small">公開する</ThemedText>
          <Switch testID="organization-edit-published" value={published} onValueChange={setPublished} />
        </View>

        {organization.published_at !== null && organization.slug && (
          <ThemedText type="small" themeColor="textSecondary">
            公開ページ: /{organization.slug}
          </ThemedText>
        )}
      </ThemedView>

      {errorMessage && (
        <ThemedText testID="organization-edit-error" style={styles.error}>
          {errorMessage}
        </ThemedText>
      )}
      {savedMessage && (
        <ThemedText testID="organization-edit-saved" style={styles.success}>
          {savedMessage}
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
          <ThemedText type="link">団体トップへ戻る</ThemedText>
        </TouchableOpacity>
      </View>

      <OrganizationSnsSection organizationId={organization.id} />
      <OrganizationExternalLinksSection organizationId={organization.id} />
    </ScrollView>
  );
}

function OrganizationSnsSection({ organizationId }: { organizationId: string }) {
  const { query, create, update, remove } = useOrganizationSns(organizationId);

  const [service, setService] = useState('X');
  const [accountIdentifier, setAccountIdentifier] = useState('');
  const [profileUrl, setProfileUrl] = useState('');
  const [formError, setFormError] = useState<string | null>(null);

  async function handleAdd() {
    if (!accountIdentifier.trim() || !profileUrl.trim()) {
      return;
    }

    setFormError(null);

    try {
      await create.mutateAsync({
        service,
        account_identifier: accountIdentifier.trim(),
        profile_url: profileUrl.trim(),
      });
      setAccountIdentifier('');
      setProfileUrl('');
    } catch (error) {
      setFormError(getErrorMessage(error));
    }
  }

  return (
    <ThemedView style={styles.section} testID="organization-edit-sns-section">
      <ThemedText type="subtitle" style={styles.sectionTitle}>
        SNS
      </ThemedText>

      {query.isLoading && <ActivityIndicator testID="organization-edit-sns-loading" />}

      <View style={styles.list}>
        {(query.data ?? []).map((sns) => (
          <SnsRow key={sns.id} sns={sns} onUpdate={update} onDelete={() => remove.mutate(sns.id)} />
        ))}
      </View>

      <View style={styles.addForm} testID="organization-edit-sns-add-form">
        <View style={styles.chipRow}>
          {SNS_SERVICES.map((option) => (
            <TouchableOpacity
              key={option.value}
              testID={`organization-edit-sns-service-${option.value}`}
              onPress={() => setService(option.value)}
              style={[styles.chip, service === option.value && styles.chipSelected]}
            >
              <ThemedText type="small" style={service === option.value ? styles.chipTextSelected : undefined}>
                {option.label}
              </ThemedText>
            </TouchableOpacity>
          ))}
        </View>
        <ThemedTextInput
          testID="organization-edit-sns-account-identifier"
          value={accountIdentifier}
          onChangeText={setAccountIdentifier}
          placeholder="アカウントID（例: stageart_theatre）"
          autoCapitalize="none"
          style={styles.input}
        />
        <ThemedTextInput
          testID="organization-edit-sns-profile-url"
          value={profileUrl}
          onChangeText={setProfileUrl}
          placeholder="https://..."
          autoCapitalize="none"
          style={styles.input}
        />
        {formError && <ThemedText style={styles.error}>{formError}</ThemedText>}
        <TouchableOpacity
          testID="organization-edit-sns-add-submit"
          onPress={handleAdd}
          disabled={create.isPending}
          style={styles.secondaryButton}
        >
          <ThemedText style={styles.secondaryButtonText}>SNSを追加する</ThemedText>
        </TouchableOpacity>
      </View>
    </ThemedView>
  );
}

function SnsRow({
  sns,
  onUpdate,
  onDelete,
}: {
  sns: OrganizationSNS;
  onUpdate: ReturnType<typeof useOrganizationSns>['update'];
  onDelete: () => void;
}) {
  return (
    <View style={styles.row} testID={`organization-sns-row-${sns.id}`}>
      <View style={styles.rowInfo}>
        <ThemedText type="smallBold">{SNS_SERVICES.find((option) => option.value === sns.service)?.label ?? sns.service}</ThemedText>
        <ThemedText type="small" themeColor="textSecondary">
          {sns.account_identifier}
        </ThemedText>
      </View>
      <View style={styles.rowActions}>
        <TouchableOpacity
          testID={`organization-sns-visibility-${sns.id}`}
          onPress={() =>
            onUpdate.mutate({
              id: sns.id,
              fields: {
                service: sns.service,
                account_identifier: sns.account_identifier,
                profile_url: sns.profile_url,
                display_name: sns.display_name,
                display_order: sns.display_order,
                visibility: !sns.visibility,
              },
            })
          }
        >
          <ThemedText type="small" style={styles.secondaryButtonText}>
            {sns.visibility ? '公開中' : '非公開'}
          </ThemedText>
        </TouchableOpacity>
        <TouchableOpacity testID={`organization-sns-delete-${sns.id}`} onPress={onDelete}>
          <ThemedText type="small" style={styles.error}>
            削除
          </ThemedText>
        </TouchableOpacity>
      </View>
    </View>
  );
}

function OrganizationExternalLinksSection({ organizationId }: { organizationId: string }) {
  const { query, create, remove } = useOrganizationExternalLinks(organizationId);

  const [label, setLabel] = useState('');
  const [url, setUrl] = useState('');
  const [formError, setFormError] = useState<string | null>(null);

  async function handleAdd() {
    if (!label.trim() || !url.trim()) {
      return;
    }

    setFormError(null);

    try {
      await create.mutateAsync({ label: label.trim(), url: url.trim(), display_order: query.data?.length ?? 0 });
      setLabel('');
      setUrl('');
    } catch (error) {
      setFormError(getErrorMessage(error));
    }
  }

  return (
    <ThemedView style={styles.section} testID="organization-edit-external-links-section">
      <ThemedText type="subtitle" style={styles.sectionTitle}>
        外部リンク
      </ThemedText>

      {query.isLoading && <ActivityIndicator testID="organization-edit-external-links-loading" />}

      <View style={styles.list}>
        {(query.data ?? []).map((link: OrganizationExternalLink) => (
          <View key={link.id} style={styles.row} testID={`organization-external-link-row-${link.id}`}>
            <View style={styles.rowInfo}>
              <ThemedText type="smallBold">{link.label}</ThemedText>
              <ThemedText type="small" themeColor="textSecondary">
                {link.url}
              </ThemedText>
            </View>
            <TouchableOpacity testID={`organization-external-link-delete-${link.id}`} onPress={() => remove.mutate(link.id)}>
              <ThemedText type="small" style={styles.error}>
                削除
              </ThemedText>
            </TouchableOpacity>
          </View>
        ))}
      </View>

      <View style={styles.addForm} testID="organization-edit-external-links-add-form">
        <ThemedTextInput
          testID="organization-edit-external-link-label"
          value={label}
          onChangeText={setLabel}
          placeholder="名称（例: YouTube）"
          style={styles.input}
        />
        <ThemedTextInput
          testID="organization-edit-external-link-url"
          value={url}
          onChangeText={setUrl}
          placeholder="https://..."
          autoCapitalize="none"
          style={styles.input}
        />
        {formError && <ThemedText style={styles.error}>{formError}</ThemedText>}
        <TouchableOpacity
          testID="organization-edit-external-link-add-submit"
          onPress={handleAdd}
          disabled={create.isPending}
          style={styles.secondaryButton}
        >
          <ThemedText style={styles.secondaryButtonText}>リンクを追加する</ThemedText>
        </TouchableOpacity>
      </View>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.two },
  pageTitle: { marginBottom: Spacing.two },
  section: { gap: Spacing.one, maxWidth: 480, marginBottom: Spacing.four },
  sectionTitle: { marginBottom: Spacing.one },
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
  error: { color: '#a6483a' },
  success: { color: '#2e7d32', marginBottom: Spacing.two },
  actions: { flexDirection: 'row', alignItems: 'center', gap: Spacing.four, marginTop: Spacing.one, marginBottom: Spacing.four },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  switchRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two, marginBottom: Spacing.one },
  list: { gap: Spacing.two, marginBottom: Spacing.two },
  row: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: Spacing.two,
  },
  rowInfo: { gap: Spacing.half },
  rowActions: { flexDirection: 'row', alignItems: 'center', gap: Spacing.three },
  addForm: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.one,
    backgroundColor: '#F7F5F1',
  },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.one, marginBottom: Spacing.one },
  chip: { borderWidth: 1, borderColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.half, paddingHorizontal: Spacing.two },
  chipSelected: { backgroundColor: BrandColors.warmAmber },
  chipTextSelected: { color: '#fff' },
  secondaryButton: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
    alignItems: 'center',
  },
  secondaryButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
});
