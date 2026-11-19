import * as ImagePicker from 'expo-image-picker';
import { createElement, useMemo, useState } from 'react';
import { ActivityIndicator, Image, Linking, Platform, StyleSheet, Switch, TouchableOpacity, View } from 'react-native';

import { ApiError } from '@/api/errors';
import { FormInput } from '@/components/form-input';
import { ManagementCard } from '@/components/management-card';
import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { mutedColor, themeFontFamily } from '@/features/publicPage/PublicPageTheme';
import type {
  HeroPreset,
  HeroSettings,
  PublicPageSettings,
  PublicPageSite,
  PublicPageThemeValues,
  ThemeColorKey,
  ThemeFontKey,
  ThemeSettings,
} from '@/features/publicPage/types';
import { usePublicPageSettings } from '@/features/publicPage/usePublicPageSettings';
import { getErrorMessage } from '@/utils/errorMessage';

/** A rejected hero image (too large, broken, animation over 5MB, ...) says
 * why in the server's own message; anything else keeps the generic text. */
function heroUploadErrorMessage(error: unknown): string {
  if (error instanceof ApiError && error.code === 'stageart_public_page_hero_image_rejected' && error.message) {
    return error.message;
  }

  return getErrorMessage(error);
}

/** Field labels of StageArtCore's ThemeAdmin. */
const COLOR_FIELDS: { key: ThemeColorKey; label: string }[] = [
  { key: 'bg', label: '背景色' },
  { key: 'surface', label: 'カード・面の色' },
  { key: 'text', label: '本文色' },
  { key: 'primary', label: 'メイン色' },
  { key: 'accent', label: 'アクセント色' },
  { key: 'border', label: '境界線色' },
];
const FONT_FIELDS: { key: ThemeFontKey; label: string }[] = [
  { key: 'heading_font', label: '見出しフォント' },
  { key: 'body_font', label: '本文フォント' },
];
const HEX_COLOR = /^#([0-9a-fA-F]{3}){1,2}$/;

type HeroForm = Omit<HeroSettings, 'custom_image_url'>;

/**
 * 公開ページ設定 tab of the organization / production management screens:
 * theme (preset or custom colors + fonts) and hero (preset or own image,
 * title, description) of that one page, following StageArtCore's
 * ThemeAdmin / HeroAdmin / ProductionHeroAdmin. Every list shown here
 * (theme presets, fonts, hero presets) comes from the API.
 */
export function PublicPageSettingsPanel({
  site,
  ownerId,
  publicPageUrl,
}: {
  site: PublicPageSite;
  ownerId: string;
  /** Shown as a link when the page is published. */
  publicPageUrl?: string | null;
}) {
  const settings = usePublicPageSettings(site, ownerId);
  const { query } = settings;

  if (query.isLoading) {
    return <ActivityIndicator testID={`${site}-public-page-loading`} />;
  }

  if (query.isError || !query.data) {
    return (
      <ThemedText testID={`${site}-public-page-load-error`} style={styles.error}>
        {getErrorMessage(query.error)}
      </ThemedText>
    );
  }

  return <PublicPageSettingsForm site={site} data={query.data} settings={settings} publicPageUrl={publicPageUrl} />;
}

/**
 * Mounted once the saved settings are loaded, so they seed the form state
 * directly; later cache updates (an upload, a save) do not reset what the
 * form shows - only `data` (e.g. the uploaded own image) is read live.
 */
function PublicPageSettingsForm({
  site,
  data,
  settings,
  publicPageUrl,
}: {
  site: PublicPageSite;
  data: PublicPageSettings;
  settings: ReturnType<typeof usePublicPageSettings>;
  publicPageUrl?: string | null;
}) {
  const { save, uploadHeroImage } = settings;
  const [theme, setTheme] = useState<ThemeSettings>(data.theme);
  const [hero, setHero] = useState<HeroForm>(() => {
    const { custom_image_url: _uploaded, ...form } = data.hero;
    return form;
  });
  const [savedMessage, setSavedMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const invalidColor = theme.mode === 'custom' && COLOR_FIELDS.some(({ key }) => !HEX_COLOR.test(theme.custom[key]));

  function markDirty() {
    setSavedMessage(null);
  }

  async function handleSave() {
    if (invalidColor) {
      return;
    }

    setErrorMessage(null);
    setSavedMessage(null);

    try {
      await save.mutateAsync({ theme, hero });
      setSavedMessage('公開ページ設定を保存しました。');
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  return (
    <View testID={`${site}-public-page-settings`}>
      <ThemeCard
        site={site}
        data={data}
        theme={theme}
        onChange={(next) => {
          setTheme(next);
          markDirty();
        }}
      />

      <HeroCard
        site={site}
        data={data}
        hero={hero}
        uploadHeroImage={uploadHeroImage}
        onChange={(next) => {
          setHero(next);
          markDirty();
        }}
      />

      {errorMessage && (
        <ThemedText testID={`${site}-public-page-error`} style={styles.error}>
          {errorMessage}
        </ThemedText>
      )}
      {savedMessage && (
        <ThemedText testID={`${site}-public-page-saved`} style={styles.success}>
          {savedMessage}
        </ThemedText>
      )}

      <View style={styles.actions}>
        <TouchableOpacity
          testID={`${site}-public-page-submit`}
          onPress={handleSave}
          disabled={save.isPending || invalidColor || uploadHeroImage.isPending}
          style={[styles.button, (save.isPending || invalidColor || uploadHeroImage.isPending) && styles.buttonDisabled]}
        >
          {save.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>公開ページ設定を保存</ThemedText>}
        </TouchableOpacity>
        {publicPageUrl && (
          <TouchableOpacity testID={`${site}-public-page-view`} onPress={() => Linking.openURL(publicPageUrl)}>
            <ThemedText type="link">公開ページを見る</ThemedText>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );
}

function resolveTheme(data: PublicPageSettings, theme: ThemeSettings): PublicPageThemeValues {
  const preset = data.theme_presets.find((candidate) => candidate.key === theme.preset) ?? data.theme_presets[0];
  // Same rule as StageArtCore's ThemeRenderer: custom values override the preset's.
  return theme.mode === 'custom' ? { ...preset, ...theme.custom, mode: preset.mode } : preset;
}

function ThemeCard({
  site,
  data,
  theme,
  onChange,
}: {
  site: PublicPageSite;
  data: PublicPageSettings;
  theme: ThemeSettings;
  onChange: (theme: ThemeSettings) => void;
}) {
  const resolved = useMemo(() => resolveTheme(data, theme), [data, theme]);
  const custom = theme.mode === 'custom';

  return (
    <ManagementCard
      title="テーマ"
      description={site === 'organization' ? '団体の公開ページの配色とフォントを選びます。' : 'この公演の公開ページだけに適用される配色とフォントを選びます（団体のテーマとは別に保存されます）。'}
      testID={`${site}-public-page-theme`}
    >
      <ThemedText type="smallBold">テーマプリセット</ThemedText>
      <View style={styles.presetGrid}>
        {data.theme_presets.map((preset) => {
          const selected = preset.key === theme.preset;

          return (
            <TouchableOpacity
              key={preset.key}
              testID={`${site}-theme-preset-${preset.key}`}
              accessibilityRole="radio"
              accessibilityState={{ selected }}
              onPress={() => onChange({ ...theme, preset: preset.key })}
              style={[styles.themePreset, selected && styles.selectedOutline]}
            >
              <View style={[styles.themePresetSample, { backgroundColor: preset.bg, borderColor: preset.border }]}>
                {(['surface', 'text', 'primary', 'accent'] as const).map((key) => (
                  <View key={key} style={[styles.swatch, { backgroundColor: preset[key], borderColor: preset.border }]} />
                ))}
              </View>
              <ThemedText type="small" style={styles.themePresetLabel}>
                {selected ? '✓ ' : ''}
                {preset.label}
              </ThemedText>
              <ThemedText type="small" themeColor="textSecondary">
                {preset.mode === 'dark' ? 'ダーク系' : 'ライト系'}
              </ThemedText>
            </TouchableOpacity>
          );
        })}
      </View>

      <View style={styles.switchRow}>
        <Switch
          style={styles.switch}
          testID={`${site}-theme-custom-mode`}
          value={custom}
          onValueChange={(on) => onChange({ ...theme, mode: on ? 'custom' : 'preset' })}
        />
        <ThemedText type="small" style={styles.switchLabel}>カスタム配色・フォントを使用する</ThemedText>
      </View>
      <ThemedText type="small" themeColor="textSecondary">
        {custom
          ? 'カスタム配色が選択中のプリセットより優先されます。オフにすると、カスタム配色を残したままプリセットに戻ります。'
          : '選択中のプリセットの配色とフォントを使います。'}
      </ThemedText>

      {custom && (
        <View style={styles.customBox} testID={`${site}-theme-custom-fields`}>
          {COLOR_FIELDS.map(({ key, label }) => (
            <ColorField
              key={key}
              testID={`${site}-theme-color-${key}`}
              label={label}
              value={theme.custom[key]}
              onChange={(value) => onChange({ ...theme, custom: { ...theme.custom, [key]: value } })}
            />
          ))}
          {FONT_FIELDS.map(({ key, label }) => (
            <View key={key} style={styles.fieldRow}>
              <ThemedText type="small" style={styles.fieldLabel}>
                {label}
              </ThemedText>
              <View style={styles.chipRow}>
                {data.fonts.map((font) => {
                  const selected = theme.custom[key] === font.key;

                  return (
                    <TouchableOpacity
                      key={font.key}
                      testID={`${site}-theme-${key}-${font.key}`}
                      accessibilityRole="radio"
                      accessibilityState={{ selected }}
                      onPress={() => onChange({ ...theme, custom: { ...theme.custom, [key]: font.key } })}
                      style={[styles.chip, selected && styles.chipSelected]}
                    >
                      <ThemedText type="small" style={[selected ? styles.chipTextSelected : undefined, { fontFamily: themeFontFamily(font.key) }]}>
                        {font.label}
                      </ThemedText>
                    </TouchableOpacity>
                  );
                })}
              </View>
            </View>
          ))}
        </View>
      )}

      <ThemePreview theme={resolved} testID={`${site}-theme-preview`} />
    </ManagementCard>
  );
}

function ColorField({ label, value, onChange, testID }: { label: string; value: string; onChange: (value: string) => void; testID: string }) {
  const valid = HEX_COLOR.test(value);

  return (
    <View style={styles.fieldRow}>
      <ThemedText type="small" style={styles.fieldLabel}>
        {label}
      </ThemedText>
      {Platform.OS === 'web' ? (
        createElement('input', {
          type: 'color',
          value: valid && value.length === 7 ? value : '#000000',
          onChange: (event: { target: { value: string } }) => onChange(event.target.value),
          'data-testid': testID,
          'aria-label': label,
          style: { width: 48, height: 32, padding: 0, border: '1px solid #d7d2ca', borderRadius: 6, background: 'none', cursor: 'pointer' },
        })
      ) : (
        <View style={[styles.swatchLarge, { backgroundColor: valid ? value : 'transparent' }]} />
      )}
      <FormInput
        testID={`${testID}-hex`}
        value={value}
        onChangeText={(text) => onChange(text.trim())}
        autoCapitalize="none"
        autoCorrect={false}
        style={styles.hexInput}
      />
      {!valid && (
        <ThemedText type="small" style={styles.error}>
          #rrggbb 形式で入力してください
        </ThemedText>
      )}
    </View>
  );
}

/** How the theme looks on the public page (heading, text, card, button). */
function ThemePreview({ theme, testID }: { theme: PublicPageThemeValues; testID: string }) {
  return (
    <View testID={testID} style={[styles.preview, { backgroundColor: theme.bg, borderColor: theme.border }]}>
      <ThemedText type="small" style={{ color: mutedColor(theme.text) }}>
        プレビュー
      </ThemedText>
      <ThemedText style={[styles.previewHeading, { color: theme.primary, fontFamily: themeFontFamily(theme.heading_font) }]}>見出し</ThemedText>
      <ThemedText style={{ color: theme.text, fontFamily: themeFontFamily(theme.body_font) }}>本文のテキストはこのように表示されます。</ThemedText>
      <View style={[styles.previewCard, { backgroundColor: theme.surface, borderColor: theme.border }]}>
        <ThemedText type="small" style={{ color: theme.text, fontFamily: themeFontFamily(theme.body_font) }}>
          カード・面
        </ThemedText>
        <ThemedText type="small" style={{ color: theme.accent, fontFamily: themeFontFamily(theme.body_font) }}>
          アクセント色
        </ThemedText>
      </View>
      <View style={[styles.previewButton, { backgroundColor: theme.primary }]}>
        <ThemedText type="small" style={{ color: theme.surface, fontWeight: '600' }}>
          ボタン
        </ThemedText>
      </View>
    </View>
  );
}

function HeroCard({
  site,
  data,
  hero,
  uploadHeroImage,
  onChange,
}: {
  site: PublicPageSite;
  data: PublicPageSettings;
  hero: HeroForm;
  uploadHeroImage: ReturnType<typeof usePublicPageSettings>['uploadHeroImage'];
  onChange: (hero: HeroForm) => void;
}) {
  const [pickError, setPickError] = useState<string | null>(null);
  const customImageUrl = data.hero.custom_image_url;
  const groups = useMemo(() => {
    const byCategory = new Map<string, HeroPreset[]>();
    data.hero_presets.forEach((preset) => {
      byCategory.set(preset.category, [...(byCategory.get(preset.category) ?? []), preset]);
    });
    return [...byCategory.entries()];
  }, [data.hero_presets]);
  const owner = site === 'organization' ? '団体' : '公演';

  async function handlePick() {
    setPickError(null);

    if (Platform.OS !== 'web') {
      const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!permission.granted) {
        setPickError('画像ライブラリへのアクセスが許可されていません。');
        return;
      }
    }

    const result = await ImagePicker.launchImageLibraryAsync({ mediaTypes: 'images', quality: 0.9 });

    if (result.canceled || result.assets.length === 0) {
      return;
    }

    const asset = result.assets[0];

    try {
      const response = await fetch(asset.uri);
      const blob = await response.blob();
      const fileName = asset.fileName ?? `hero.${(blob.type.split('/')[1] ?? 'jpg').replace('jpeg', 'jpg')}`;
      await uploadHeroImage.mutateAsync({ file: blob, fileName });
      onChange({ ...hero, type: 'custom' });
    } catch (error) {
      setPickError(heroUploadErrorMessage(error));
    }
  }

  return (
    <ManagementCard
      title="Hero"
      description={`${owner}の公開ページ上部に表示する背景画像とタイトルです。`}
      testID={`${site}-public-page-hero`}
    >
      <View style={styles.switchRow}>
        <Switch style={styles.switch} testID={`${site}-hero-enabled`} value={hero.enabled} onValueChange={(enabled) => onChange({ ...hero, enabled })} />
        <ThemedText type="small" style={styles.switchLabel}>{`${owner}Heroを使用する`}</ThemedText>
      </View>
      {!hero.enabled && (
        <ThemedText type="small" themeColor="textSecondary">
          {site === 'production'
            ? 'オフの場合、公開ページにHeroは表示されません（保存すると、この公演のHero設定は消去されます）。'
            : 'オフの場合、公開ページにHeroは表示されません（設定内容は保持されます）。'}
        </ThemedText>
      )}

      {hero.enabled && (
        <View style={styles.heroOptions} testID={`${site}-hero-options`}>
          <ThemedText type="small" themeColor="textSecondary">
            Heroタイトル
          </ThemedText>
          <FormInput
            testID={`${site}-hero-title`}
            value={hero.title}
            onChangeText={(title) => onChange({ ...hero, title })}
            placeholder={`${owner}名を表示`}
            style={styles.input}
          />
          <ThemedText type="small" themeColor="textSecondary">
            Hero説明文
          </ThemedText>
          <FormInput
            kind="textarea"
            testID={`${site}-hero-description`}
            value={hero.description}
            onChangeText={(description) => onChange({ ...hero, description })}
            placeholder={`${owner}の短い紹介文を表示`}
            multiline
            style={[styles.input, styles.textarea]}
          />
          <ThemedText type="small" themeColor="textSecondary">
            {site === 'organization' ? '空欄の場合は団体基本情報の団体名・団体説明を使用します。' : '空欄の場合は、公演基本情報の公演名・公演説明を使用します。'}
          </ThemedText>

          <ThemedText type="smallBold" style={styles.subheading}>
            背景画像
          </ThemedText>
          <View style={styles.chipRow}>
            {(
              [
                { key: 'preset', label: 'プリセットから選択' },
                { key: 'custom', label: '独自画像を使用' },
              ] as const
            ).map((option) => {
              const selected = hero.type === option.key;

              return (
                <TouchableOpacity
                  key={option.key}
                  testID={`${site}-hero-type-${option.key}`}
                  accessibilityRole="radio"
                  accessibilityState={{ selected }}
                  onPress={() => onChange({ ...hero, type: option.key })}
                  style={[styles.chip, selected && styles.chipSelected]}
                >
                  <ThemedText type="small" style={selected ? styles.chipTextSelected : undefined}>
                    {selected ? '◉ ' : '○ '}
                    {option.label}
                  </ThemedText>
                </TouchableOpacity>
              );
            })}
          </View>

          {hero.type === 'preset' &&
            groups.map(([category, presets]) => (
              <View key={category} style={styles.heroGroup}>
                <ThemedText type="small" themeColor="textSecondary">
                  {category}
                </ThemedText>
                <View style={styles.heroGrid}>
                  {presets.map((preset) => {
                    const selected = hero.preset === preset.id;

                    return (
                      <TouchableOpacity
                        key={preset.id}
                        testID={`${site}-hero-preset-${preset.id}`}
                        accessibilityRole="radio"
                        accessibilityLabel={preset.label}
                        accessibilityState={{ selected }}
                        onPress={() => onChange({ ...hero, preset: preset.id })}
                        style={[styles.heroPreset, selected && styles.selectedOutline]}
                      >
                        <Image source={{ uri: preset.thumbnail_url }} style={styles.heroThumb} resizeMode="cover" />
                        <ThemedText type="small" numberOfLines={1} style={styles.heroPresetLabel}>
                          {selected ? '✓ ' : ''}
                          {preset.label}
                        </ThemedText>
                      </TouchableOpacity>
                    );
                  })}
                </View>
              </View>
            ))}

          {hero.type === 'custom' && (
            <View style={styles.heroCustom} testID={`${site}-hero-custom`}>
              {customImageUrl ? (
                <Image testID={`${site}-hero-custom-preview`} source={{ uri: customImageUrl }} style={styles.heroCustomPreview} resizeMode="cover" />
              ) : (
                <ThemedText type="small" themeColor="textSecondary" testID={`${site}-hero-custom-empty`}>
                  独自画像はまだアップロードされていません。
                </ThemedText>
              )}
              <TouchableOpacity
                testID={`${site}-hero-custom-upload`}
                onPress={handlePick}
                disabled={uploadHeroImage.isPending}
                style={styles.secondaryButton}
              >
                {uploadHeroImage.isPending ? (
                  <ActivityIndicator color={BrandColors.warmAmber} />
                ) : (
                  <ThemedText style={styles.secondaryButtonText}>{customImageUrl ? '画像を変更' : '画像をアップロード'}</ThemedText>
                )}
              </TouchableOpacity>
              <ThemedText type="small" themeColor="textSecondary">
                JPEG / PNG / WebP / GIF、10MBまで（5MBを超える画像はサーバーで自動的に縮小されます）。16:9 の範囲に中央を基準に切り取って表示されます。
              </ThemedText>
              {(pickError || uploadHeroImage.isError) && (
                <ThemedText testID={`${site}-hero-custom-error`} style={styles.error}>
                  {pickError ?? heroUploadErrorMessage(uploadHeroImage.error)}
                </ThemedText>
              )}
            </View>
          )}
        </View>
      )}
    </ManagementCard>
  );
}

const styles = StyleSheet.create({
  error: { color: '#a6483a' },
  success: { color: '#2e7d32', marginBottom: Spacing.two },
  actions: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: Spacing.four, marginTop: Spacing.one, marginBottom: Spacing.four },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  presetGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two, marginBottom: Spacing.two },
  themePreset: {
    width: 148,
    maxWidth: '100%',
    borderWidth: 1,
    borderColor: '#d7d2ca',
    borderRadius: Radius.small,
    padding: Spacing.two,
    gap: Spacing.half,
    backgroundColor: '#fff',
  },
  selectedOutline: { borderColor: BrandColors.warmAmber, borderWidth: 3, padding: Spacing.two - 2 },
  themePresetSample: { flexDirection: 'row', gap: 4, padding: 6, borderRadius: 6, borderWidth: 1 },
  swatch: { width: 18, height: 18, borderRadius: 9, borderWidth: 1 },
  swatchLarge: { width: 32, height: 32, borderRadius: 6, borderWidth: 1, borderColor: '#d7d2ca' },
  themePresetLabel: { fontWeight: '600' },
  switchRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two, marginTop: Spacing.two },
  // Never squeezed by its label on a narrow screen.
  switch: { flexShrink: 0 },
  switchLabel: { flexShrink: 1 },
  customBox: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.two,
    backgroundColor: '#F7F5F1',
    marginTop: Spacing.two,
  },
  fieldRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: Spacing.two },
  fieldLabel: { width: 120 },
  hexInput: {
    width: 110,
    borderWidth: 1,
    borderColor: '#d7d2ca',
    borderRadius: 8,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.one,
    fontSize: 14,
  },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.one },
  chip: { maxWidth: '100%', borderWidth: 1, borderColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.half, paddingHorizontal: Spacing.two },
  chipSelected: { backgroundColor: BrandColors.warmAmber },
  chipTextSelected: { color: '#fff' },
  preview: { borderWidth: 1, borderRadius: Radius.medium, padding: Spacing.three, gap: Spacing.one, marginTop: Spacing.three },
  previewHeading: { fontSize: 24, lineHeight: 32, fontWeight: '600' },
  previewCard: { borderWidth: 1, borderRadius: Radius.small, padding: Spacing.two, gap: Spacing.half },
  previewButton: { alignSelf: 'flex-start', borderRadius: Radius.small, paddingVertical: Spacing.one, paddingHorizontal: Spacing.three, marginTop: Spacing.one },
  heroOptions: { gap: Spacing.one, marginTop: Spacing.two },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
    marginBottom: Spacing.one,
  },
  textarea: { minHeight: 90, textAlignVertical: 'top' },
  subheading: { marginTop: Spacing.two },
  heroGroup: { gap: Spacing.one, marginTop: Spacing.two },
  heroGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two },
  heroPreset: {
    width: 150,
    maxWidth: '100%',
    borderWidth: 1,
    borderColor: '#d7d2ca',
    borderRadius: Radius.small,
    overflow: 'hidden',
    backgroundColor: '#fff',
  },
  heroThumb: { width: '100%', aspectRatio: 16 / 9, backgroundColor: '#111' },
  heroPresetLabel: { paddingHorizontal: Spacing.two, paddingVertical: Spacing.one, fontWeight: '600' },
  heroCustom: { gap: Spacing.two, marginTop: Spacing.two, alignItems: 'flex-start' },
  heroCustomPreview: { width: 360, maxWidth: '100%', aspectRatio: 16 / 9, borderRadius: Radius.small, backgroundColor: '#111' },
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
