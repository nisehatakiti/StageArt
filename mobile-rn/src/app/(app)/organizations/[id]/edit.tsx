import * as ImagePicker from 'expo-image-picker';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Image, Platform, ScrollView, StyleSheet, Switch, TouchableOpacity, View } from 'react-native';
import { useQueryClient } from '@tanstack/react-query';

import { ApiError } from '@/api/errors';
import { useAuth } from '@/auth/AuthContext';
import { ManagementCard } from '@/components/management-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { updateOrganization } from '@/features/organization/api';
import { useOrganizationExternalLinks, useOrganizationSns } from '@/features/organization/useOrganizationProfile';
import { useOrganizationLogo, useOrganizations } from '@/features/organization/useOrganizations';
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
 * StageArt Organization管理UI/UX統一: 団体情報は団体の設定・公開情報を管理す
 * るダッシュボードとして、1つの長いフォームではなく基本情報/団体ロゴ/公開ペー
 * ジ/SNS/外部リンクの各 ManagementCard に分ける。OWNER/ORGANIZATION_MANAGER
 * のみ編集可能 - UpdateOrganizationUseCase/SNS/外部リンク/ロゴの各UseCase
 * と同じAuthorization。
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
      <ThemedText type="small" themeColor="textSecondary" style={styles.pageLead}>
        団体の基本情報を管理します。
      </ThemedText>

      <ManagementCard title="基本情報">
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
      </ManagementCard>

      <OrganizationLogoCard organizationId={organization.id} logoUrl={organization.logo_url} />

      <ManagementCard title="公開ページ">
        <View style={styles.switchRow}>
          <ThemedText type="small">公開する</ThemedText>
          <Switch testID="organization-edit-published" value={published} onValueChange={setPublished} />
        </View>

        {organization.published_at !== null && organization.slug && (
          <ThemedText type="small" themeColor="textSecondary">
            公開ページURL: /{organization.slug}
          </ThemedText>
        )}
      </ManagementCard>

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

/**
 * StageArt Organization管理UI/UX統一 "団体ロゴ": 画像ファイルを選択 ->
 * アップロード (POST /organizations/{id}/logo, multipart) -> プレビュー
 * 表示 / 画像を変更 / ロゴを削除 - URL入力欄は廃止。pick()はweb/nativeの両方
 * でexpo-image-pickerの`launchImageLibraryAsync`を使い、返ってきたuri
 * をfetch()でBlobへ変換してから送る（Expoの標準パターン - webのblob: URI
 * とnativeのfile: URIの両方で同じ経路が使える）。
 */
function OrganizationLogoCard({ organizationId, logoUrl }: { organizationId: string; logoUrl: string | null }) {
  const { upload, remove } = useOrganizationLogo(organizationId);
  const [pickError, setPickError] = useState<string | null>(null);

  async function handlePick() {
    setPickError(null);

    if (Platform.OS !== 'web') {
      const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!permission.granted) {
        setPickError('画像ライブラリへのアクセスが許可されていません。');
        return;
      }
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: 'images',
      quality: 0.8,
    });

    if (result.canceled || result.assets.length === 0) {
      return;
    }

    const asset = result.assets[0];

    try {
      const response = await fetch(asset.uri);
      const blob = await response.blob();
      const fileName = asset.fileName ?? `logo.${(blob.type.split('/')[1] ?? 'jpg').replace('jpeg', 'jpg')}`;
      await upload.mutateAsync({ file: blob, fileName });
    } catch (error) {
      setPickError(getErrorMessage(error));
    }
  }

  return (
    <ManagementCard title="団体ロゴ">
      <View style={styles.logoRow}>
        {logoUrl ? (
          <Image testID="organization-edit-logo-preview" source={{ uri: logoUrl }} style={styles.logoPreview} />
        ) : (
          <View style={styles.logoPlaceholder} testID="organization-edit-logo-empty">
            <ThemedText type="small" themeColor="textSecondary">
              ロゴ画像が設定されていません
            </ThemedText>
          </View>
        )}

        <View style={styles.logoActions}>
          <TouchableOpacity
            testID="organization-edit-logo-change"
            onPress={handlePick}
            disabled={upload.isPending || remove.isPending}
            style={styles.secondaryButton}
          >
            {upload.isPending ? <ActivityIndicator color={BrandColors.warmAmber} /> : <ThemedText style={styles.secondaryButtonText}>画像を変更</ThemedText>}
          </TouchableOpacity>
          {logoUrl && (
            <TouchableOpacity
              testID="organization-edit-logo-delete"
              onPress={() => remove.mutate()}
              disabled={upload.isPending || remove.isPending}
              style={styles.secondaryButton}
            >
              <ThemedText style={styles.error}>ロゴを削除</ThemedText>
            </TouchableOpacity>
          )}
        </View>
      </View>

      {(pickError || upload.isError) && (
        <ThemedText testID="organization-edit-logo-error" style={styles.error}>
          {pickError ?? getErrorMessage(upload.error)}
        </ThemedText>
      )}
      {upload.isSuccess && !upload.isPending && (
        <ThemedText testID="organization-edit-logo-saved" style={styles.success}>
          ロゴ画像を保存しました。
        </ThemedText>
      )}
    </ManagementCard>
  );
}

function OrganizationSnsSection({ organizationId }: { organizationId: string }) {
  const { query, create, update, remove } = useOrganizationSns(organizationId);

  const [editingId, setEditingId] = useState<string | null>(null);
  const [service, setService] = useState('X');
  const [accountIdentifier, setAccountIdentifier] = useState('');
  const [profileUrl, setProfileUrl] = useState('');
  const [formError, setFormError] = useState<string | null>(null);

  function resetForm() {
    setEditingId(null);
    setService('X');
    setAccountIdentifier('');
    setProfileUrl('');
    setFormError(null);
  }

  function beginEdit(sns: OrganizationSNS) {
    setEditingId(sns.id);
    setService(sns.service);
    setAccountIdentifier(sns.account_identifier);
    setProfileUrl(sns.profile_url);
    setFormError(null);
  }

  async function handleSubmitForm() {
    if (!accountIdentifier.trim() || !profileUrl.trim()) {
      return;
    }

    setFormError(null);

    try {
      if (editingId) {
        const current = (query.data ?? []).find((sns) => sns.id === editingId);
        await update.mutateAsync({
          id: editingId,
          fields: {
            service,
            account_identifier: accountIdentifier.trim(),
            profile_url: profileUrl.trim(),
            display_name: current?.display_name ?? null,
            display_order: current?.display_order ?? 0,
            visibility: current?.visibility ?? true,
          },
        });
      } else {
        await create.mutateAsync({
          service,
          account_identifier: accountIdentifier.trim(),
          profile_url: profileUrl.trim(),
          display_order: query.data?.length ?? 0,
        });
      }
      resetForm();
    } catch (error) {
      setFormError(getErrorMessage(error));
    }
  }

  const busy = create.isPending || update.isPending;

  return (
    <ManagementCard title="SNS" testID="organization-edit-sns-section">
      {query.isLoading && <ActivityIndicator testID="organization-edit-sns-loading" />}

      <View style={styles.list}>
        {(query.data ?? []).map((sns) => (
          <SnsRow
            key={sns.id}
            sns={sns}
            editing={editingId === sns.id}
            onEdit={() => beginEdit(sns)}
            onToggleVisibility={() =>
              update.mutate({
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
            onDelete={() => remove.mutate(sns.id)}
          />
        ))}
        {!query.isLoading && (query.data ?? []).length === 0 && (
          <ThemedText type="small" themeColor="textSecondary" testID="organization-edit-sns-empty">
            登録されているSNSはありません。
          </ThemedText>
        )}
      </View>

      <View style={styles.addForm} testID="organization-edit-sns-add-form">
        <ThemedText type="smallBold">{editingId ? 'SNSを編集' : '+ SNSを追加'}</ThemedText>
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
        <View style={styles.formActions}>
          <TouchableOpacity testID="organization-edit-sns-add-submit" onPress={handleSubmitForm} disabled={busy} style={styles.secondaryButton}>
            {busy ? <ActivityIndicator color={BrandColors.warmAmber} /> : <ThemedText style={styles.secondaryButtonText}>{editingId ? '変更を保存' : 'SNSを追加する'}</ThemedText>}
          </TouchableOpacity>
          {editingId && (
            <TouchableOpacity testID="organization-edit-sns-edit-cancel" onPress={resetForm}>
              <ThemedText type="link">キャンセル</ThemedText>
            </TouchableOpacity>
          )}
        </View>
      </View>
    </ManagementCard>
  );
}

function SnsRow({
  sns,
  editing,
  onEdit,
  onToggleVisibility,
  onDelete,
}: {
  sns: OrganizationSNS;
  editing: boolean;
  onEdit: () => void;
  onToggleVisibility: () => void;
  onDelete: () => void;
}) {
  return (
    <View style={[styles.row, editing && styles.rowEditing]} testID={`organization-sns-row-${sns.id}`}>
      <View style={styles.rowInfo}>
        <ThemedText type="smallBold">{SNS_SERVICES.find((option) => option.value === sns.service)?.label ?? sns.service}</ThemedText>
        <ThemedText type="small" themeColor="textSecondary">
          {sns.account_identifier}
        </ThemedText>
        <ThemedText type="small" themeColor="textSecondary">
          {sns.profile_url}
        </ThemedText>
      </View>
      <View style={styles.rowActions}>
        <TouchableOpacity testID={`organization-sns-visibility-${sns.id}`} onPress={onToggleVisibility}>
          <ThemedText type="small" style={styles.secondaryButtonText}>
            {sns.visibility ? '公開中' : '非公開'}
          </ThemedText>
        </TouchableOpacity>
        <TouchableOpacity testID={`organization-sns-edit-${sns.id}`} onPress={onEdit}>
          <ThemedText type="small" style={styles.secondaryButtonText}>
            編集
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
  const { query, create, update, remove } = useOrganizationExternalLinks(organizationId);

  const [editingId, setEditingId] = useState<string | null>(null);
  const [label, setLabel] = useState('');
  const [url, setUrl] = useState('');
  const [formError, setFormError] = useState<string | null>(null);

  function resetForm() {
    setEditingId(null);
    setLabel('');
    setUrl('');
    setFormError(null);
  }

  function beginEdit(link: OrganizationExternalLink) {
    setEditingId(link.id);
    setLabel(link.label);
    setUrl(link.url);
    setFormError(null);
  }

  async function handleSubmitForm() {
    if (!label.trim() || !url.trim()) {
      return;
    }

    setFormError(null);

    try {
      if (editingId) {
        const current = (query.data ?? []).find((link) => link.id === editingId);
        await update.mutateAsync({
          id: editingId,
          fields: { label: label.trim(), url: url.trim(), display_order: current?.display_order ?? 0, visibility: current?.visibility ?? true },
        });
      } else {
        await create.mutateAsync({ label: label.trim(), url: url.trim(), display_order: query.data?.length ?? 0 });
      }
      resetForm();
    } catch (error) {
      setFormError(getErrorMessage(error));
    }
  }

  const busy = create.isPending || update.isPending;

  return (
    <ManagementCard title="外部リンク" testID="organization-edit-external-links-section">
      {query.isLoading && <ActivityIndicator testID="organization-edit-external-links-loading" />}

      <View style={styles.list}>
        {(query.data ?? []).map((link: OrganizationExternalLink) => (
          <View key={link.id} style={[styles.row, editingId === link.id && styles.rowEditing]} testID={`organization-external-link-row-${link.id}`}>
            <View style={styles.rowInfo}>
              <ThemedText type="smallBold">{link.label}</ThemedText>
              <ThemedText type="small" themeColor="textSecondary">
                {link.url}
              </ThemedText>
            </View>
            <View style={styles.rowActions}>
              <TouchableOpacity
                testID={`organization-external-link-visibility-${link.id}`}
                onPress={() => update.mutate({ id: link.id, fields: { label: link.label, url: link.url, display_order: link.display_order, visibility: !link.visibility } })}
              >
                <ThemedText type="small" style={styles.secondaryButtonText}>
                  {link.visibility ? '公開中' : '非公開'}
                </ThemedText>
              </TouchableOpacity>
              <TouchableOpacity testID={`organization-external-link-edit-${link.id}`} onPress={() => beginEdit(link)}>
                <ThemedText type="small" style={styles.secondaryButtonText}>
                  編集
                </ThemedText>
              </TouchableOpacity>
              <TouchableOpacity testID={`organization-external-link-delete-${link.id}`} onPress={() => remove.mutate(link.id)}>
                <ThemedText type="small" style={styles.error}>
                  削除
                </ThemedText>
              </TouchableOpacity>
            </View>
          </View>
        ))}
        {!query.isLoading && (query.data ?? []).length === 0 && (
          <ThemedText type="small" themeColor="textSecondary" testID="organization-edit-external-links-empty">
            登録されている外部リンクはありません。
          </ThemedText>
        )}
      </View>

      <View style={styles.addForm} testID="organization-edit-external-links-add-form">
        <ThemedText type="smallBold">{editingId ? '外部リンクを編集' : '+ 外部リンクを追加'}</ThemedText>
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
        <View style={styles.formActions}>
          <TouchableOpacity testID="organization-edit-external-link-add-submit" onPress={handleSubmitForm} disabled={busy} style={styles.secondaryButton}>
            {busy ? <ActivityIndicator color={BrandColors.warmAmber} /> : <ThemedText style={styles.secondaryButtonText}>{editingId ? '変更を保存' : 'リンクを追加する'}</ThemedText>}
          </TouchableOpacity>
          {editingId && (
            <TouchableOpacity testID="organization-edit-external-link-edit-cancel" onPress={resetForm}>
              <ThemedText type="link">キャンセル</ThemedText>
            </TouchableOpacity>
          )}
        </View>
      </View>
    </ManagementCard>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.one, paddingBottom: Spacing.six },
  pageTitle: { marginBottom: Spacing.half },
  pageLead: { marginBottom: Spacing.three },
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
  logoRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: Spacing.three },
  logoPreview: { width: 96, height: 96, borderRadius: Radius.medium, backgroundColor: '#f0eee9' },
  logoPlaceholder: {
    width: 96,
    height: 96,
    borderRadius: Radius.medium,
    backgroundColor: '#f0eee9',
    borderWidth: 1,
    borderColor: '#e1dee6',
    alignItems: 'center',
    justifyContent: 'center',
    padding: Spacing.one,
  },
  logoActions: { gap: Spacing.two },
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
  rowEditing: { borderColor: BrandColors.warmAmber, borderWidth: 2 },
  rowInfo: { gap: Spacing.half, flexShrink: 1 },
  rowActions: { flexDirection: 'row', alignItems: 'center', gap: Spacing.three },
  addForm: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.one,
    backgroundColor: '#F7F5F1',
  },
  formActions: { flexDirection: 'row', alignItems: 'center', gap: Spacing.three },
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
