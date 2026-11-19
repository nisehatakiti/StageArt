import { useState } from 'react';
import { ActivityIndicator, Linking, StyleSheet, Switch, TouchableOpacity, View } from 'react-native';

import { ApiError } from '@/api/errors';
import { FormInput } from '@/components/form-input';
import { ManagementCard } from '@/components/management-card';
import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import type {
  ContentBlock,
  ContentSection,
  ContentSlot,
  ContentSlotType,
  PublicPageContent,
  PublicPageContentAdmin,
} from '@/features/publicPage/contentTypes';
import { usePublicPageContent } from '@/features/publicPage/usePublicPageContent';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * コンテンツ tab of the Organization edit screen, following StageArtCore's
 * homepage editor (SiteStructureAdmin "コンテンツブロック" + ContentBlockAdmin)
 * and its 自由コンテンツ (FreeContentAdmin):
 *
 * - 自由コンテンツ: add / edit / delete; 公開 / 下書き is the show / hide
 *   switch (a draft stays where it is placed but is not shown).
 * - コンテンツ配置: blocks > sections (見出し, 列数 1-6, 横並び / 縦並び) >
 *   one slot per column (団体の公演一覧・団体について, a Production, a member,
 *   a 自由コンテンツ, a 見出し, an external link or 空欄, with インデント
 *   0-3). Blocks, sections and slots can be moved.
 * - Members are not entered here: they are picked from メンバー管理 (its
 *   ACTIVE members, by name) and the page shows them as メンバー管理 has
 *   them; お知らせ・活動報告・募集案内 and other texts are 自由コンテンツ.
 *
 * Until a layout is saved the public page keeps its fixed order; the editor
 * then starts from Core's default (a 公演 section with the 公演一覧).
 */
export function PublicPageContentPanel({ organizationId, publicPageUrl }: { organizationId: string; publicPageUrl?: string | null }) {
  const content = usePublicPageContent(organizationId);
  // Kept here: a save remounts LayoutCard with the stored layout.
  const [layoutMessage, setLayoutMessage] = useState<string | null>(null);

  if (content.query.isLoading) {
    return <ActivityIndicator testID="organization-content-loading" />;
  }

  if (content.query.isError || !content.query.data) {
    return (
      <ThemedText testID="organization-content-load-error" style={styles.error}>
        {getErrorMessage(content.query.error)}
      </ThemedText>
    );
  }

  return (
    <View testID="organization-content-panel">
      <FreeContentCard data={content.query.data} content={content} />
      {/* Remounted when the saved layout changes, so it always starts from what is stored. */}
      <LayoutCard
        key={JSON.stringify(content.query.data.layout)}
        data={content.query.data}
        content={content}
        publicPageUrl={publicPageUrl}
        message={layoutMessage}
        onMessage={setLayoutMessage}
      />
    </View>
  );
}

type ContentApi = ReturnType<typeof usePublicPageContent>;

/** A layout or content the API rejects says why in its own message (列数, URL, タイトル ...); anything else keeps the generic text. */
function saveErrorMessage(error: unknown): string {
  if (error instanceof ApiError && error.code === 'stageart_public_page_content_invalid' && error.message) {
    return error.message;
  }
  return getErrorMessage(error);
}

// ---------------------------------------------------------------- 自由コンテンツ

type ContentForm = { id: string | null; title: string; body: string; status: PublicPageContent['status'] };

const EMPTY_FORM: ContentForm = { id: null, title: '', body: '', status: 'publish' };

function FreeContentCard({ data, content }: { data: PublicPageContentAdmin; content: ContentApi }) {
  const [form, setForm] = useState<ContentForm | null>(null);
  const [confirmDelete, setConfirmDelete] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const busy = content.saveContent.isPending || content.removeContent.isPending;

  async function save(next: ContentForm, done: string) {
    setError(null);
    setMessage(null);

    try {
      await content.saveContent.mutateAsync({ contentId: next.id, fields: { title: next.title, body: next.body, status: next.status } });
      setForm(null);
      setMessage(done);
    } catch (caught) {
      setError(saveErrorMessage(caught));
    }
  }

  async function remove(id: string) {
    setError(null);
    setMessage(null);

    try {
      await content.removeContent.mutateAsync(id);
      setConfirmDelete(null);
      setMessage('自由コンテンツを削除しました。');
    } catch (caught) {
      setError(saveErrorMessage(caught));
    }
  }

  return (
    <ManagementCard
      title="自由コンテンツ"
      description="お知らせ・活動報告・募集案内・団体紹介の追加文章など、公開ページに載せる文章です。下書きにすると、配置したままで公開ページには表示されなくなります。"
      testID="organization-free-contents"
    >
      {data.contents.length === 0 && (
        <ThemedText type="small" themeColor="textSecondary" testID="organization-free-contents-empty">
          自由コンテンツはまだありません。
        </ThemedText>
      )}

      {data.contents.map((item) => (
        <View key={item.id} style={styles.contentRow} testID={`organization-free-content-${item.id}`}>
          <View style={styles.contentRowText}>
            <ThemedText type="smallBold">{item.title}</ThemedText>
            <ThemedText type="small" style={item.status === 'publish' ? styles.badgePublic : styles.badgeDraft} testID={`organization-free-content-${item.id}-status`}>
              {item.status === 'publish' ? '公開' : '下書き（非表示）'}
            </ThemedText>
          </View>
          <View style={styles.rowButtons}>
            <SmallButton
              testID={`organization-free-content-${item.id}-toggle`}
              label={item.status === 'publish' ? '非表示にする' : '表示する'}
              disabled={busy}
              onPress={() =>
                save({ id: item.id, title: item.title, body: item.body, status: item.status === 'publish' ? 'draft' : 'publish' }, item.status === 'publish' ? '非表示にしました。' : '表示するようにしました。')
              }
            />
            <SmallButton testID={`organization-free-content-${item.id}-edit`} label="編集" disabled={busy} onPress={() => setForm({ id: item.id, title: item.title, body: item.body, status: item.status })} />
            {confirmDelete === item.id ? (
              <>
                <SmallButton testID={`organization-free-content-${item.id}-delete-confirm`} label="削除する" danger disabled={busy} onPress={() => remove(item.id)} />
                <SmallButton testID={`organization-free-content-${item.id}-delete-cancel`} label="やめる" disabled={busy} onPress={() => setConfirmDelete(null)} />
              </>
            ) : (
              <SmallButton testID={`organization-free-content-${item.id}-delete`} label="削除" danger disabled={busy} onPress={() => setConfirmDelete(item.id)} />
            )}
          </View>
        </View>
      ))}

      {form ? (
        <View style={styles.formBox} testID="organization-free-content-form">
          <ThemedText type="small" themeColor="textSecondary">
            タイトル
          </ThemedText>
          <FormInput testID="organization-free-content-title" value={form.title} onChangeText={(title) => setForm({ ...form, title })} style={styles.input} />
          <ThemedText type="small" themeColor="textSecondary">
            本文
          </ThemedText>
          <FormInput
            kind="textarea"
            multiline
            testID="organization-free-content-body"
            value={form.body}
            onChangeText={(body) => setForm({ ...form, body })}
            style={[styles.input, styles.textarea]}
          />
          <View style={styles.switchRow}>
            <Switch
              testID="organization-free-content-publish"
              value={form.status === 'publish'}
              onValueChange={(on) => setForm({ ...form, status: on ? 'publish' : 'draft' })}
              style={styles.switch}
            />
            <ThemedText style={styles.switchLabel}>公開ページに表示する</ThemedText>
          </View>
          <View style={styles.rowButtons}>
            <PrimaryButton
              testID="organization-free-content-submit"
              label={form.id ? '自由コンテンツを更新' : '自由コンテンツを追加'}
              pending={content.saveContent.isPending}
              disabled={busy || form.title.trim() === ''}
              onPress={() => save(form, form.id ? '自由コンテンツを更新しました。' : '自由コンテンツを追加しました。')}
            />
            <SmallButton testID="organization-free-content-cancel" label="キャンセル" disabled={busy} onPress={() => setForm(null)} />
          </View>
        </View>
      ) : (
        <SmallButton testID="organization-free-content-add" label="＋ 自由コンテンツを追加" onPress={() => setForm(EMPTY_FORM)} />
      )}

      {error && (
        <ThemedText testID="organization-free-contents-error" style={styles.error}>
          {error}
        </ThemedText>
      )}
      {message && (
        <ThemedText testID="organization-free-contents-message" style={styles.success}>
          {message}
        </ThemedText>
      )}
    </ManagementCard>
  );
}

// ---------------------------------------------------------------- コンテンツ配置

let idSeed = 0;
/** Ids the API keeps as given (lowercase letters, digits, - and _). */
function newId(prefix: string): string {
  idSeed += 1;
  return `${prefix}-${Date.now().toString(36)}-${idSeed}`;
}

const EMPTY_SLOT: ContentSlot = { type: 'none', ref: '', indent: 0 };

function newSection(): ContentSection {
  return { section_id: newId('section'), heading: '', columns: 1, layout: 'horizontal', slots: [{ ...EMPTY_SLOT }] };
}

/** Core SiteStructure::defaultContentBlocks(), limited to what StageArt shows: a 公演 section with the 公演一覧. */
function defaultBlocks(): ContentBlock[] {
  return [
    {
      block_id: newId('block'),
      sections: [{ section_id: newId('section'), heading: '公演', columns: 1, layout: 'vertical', slots: [{ type: 'system', ref: 'productions', indent: 0 }] }],
    },
  ];
}

function move<T>(list: T[], index: number, delta: number): T[] {
  const target = index + delta;

  if (target < 0 || target >= list.length) {
    return list;
  }

  const next = [...list];
  [next[index], next[target]] = [next[target], next[index]];
  return next;
}

/** One slot per column: extra slots are dropped, missing ones are empty (Core cuts slots to the column count). */
function withColumns(section: ContentSection, columns: number): ContentSection {
  const slots = section.slots.slice(0, columns);
  while (slots.length < columns) {
    slots.push({ ...EMPTY_SLOT });
  }
  return { ...section, columns, slots };
}

function LayoutCard({
  data,
  content,
  publicPageUrl,
  message,
  onMessage: setMessage,
}: {
  data: PublicPageContentAdmin;
  content: ContentApi;
  publicPageUrl?: string | null;
  message: string | null;
  onMessage: (message: string | null) => void;
}) {
  const [blocks, setBlocks] = useState<ContentBlock[]>(() => data.layout ?? defaultBlocks());
  const [error, setError] = useState<string | null>(null);
  const neverSaved = data.layout === null;

  function change(next: ContentBlock[]) {
    setBlocks(next);
    setMessage(null);
  }

  function updateBlock(bi: number, block: ContentBlock) {
    change(blocks.map((current, i) => (i === bi ? block : current)));
  }

  function updateSection(bi: number, si: number, section: ContentSection) {
    updateBlock(bi, { ...blocks[bi], sections: blocks[bi].sections.map((current, i) => (i === si ? section : current)) });
  }

  async function handleSave() {
    setError(null);
    setMessage(null);

    try {
      await content.saveLayout.mutateAsync(blocks);
      setMessage('コンテンツ配置を保存しました。');
    } catch (caught) {
      setError(saveErrorMessage(caught));
    }
  }

  return (
    <ManagementCard
      title="コンテンツ配置"
      description="公開ページに表示するコンテンツと、その並び順・列数・配置を設定します。配置したものだけが、この順番で表示されます。"
      testID="organization-content-layout"
    >
      {neverSaved && (
        <ThemedText type="small" themeColor="textSecondary" testID="organization-content-layout-unsaved">
          まだ保存されていません。保存するまで、公開ページは今までどおりの表示です。
        </ThemedText>
      )}

      {blocks.map((block, bi) => (
        <View key={block.block_id} style={styles.block} testID={`organization-content-block-${bi}`}>
          <View style={styles.headerRow}>
            <ThemedText type="smallBold">コンテンツブロック {bi + 1}</ThemedText>
            <View style={styles.rowButtons}>
              <SmallButton testID={`organization-content-block-${bi}-up`} label="↑" disabled={bi === 0} onPress={() => change(move(blocks, bi, -1))} />
              <SmallButton testID={`organization-content-block-${bi}-down`} label="↓" disabled={bi === blocks.length - 1} onPress={() => change(move(blocks, bi, 1))} />
              <SmallButton testID={`organization-content-block-${bi}-remove`} label="ブロックを削除" danger onPress={() => change(blocks.filter((_, i) => i !== bi))} />
            </View>
          </View>

          {block.sections.map((section, si) => (
            <SectionEditor
              key={section.section_id}
              testID={`organization-content-block-${bi}-section-${si}`}
              data={data}
              section={section}
              index={si}
              count={block.sections.length}
              onChange={(next) => updateSection(bi, si, next)}
              onMove={(delta) => updateBlock(bi, { ...block, sections: move(block.sections, si, delta) })}
              onRemove={() => updateBlock(bi, { ...block, sections: block.sections.filter((_, i) => i !== si) })}
            />
          ))}

          <SmallButton
            testID={`organization-content-block-${bi}-add-section`}
            label="＋ セクションを追加"
            onPress={() => updateBlock(bi, { ...block, sections: [...block.sections, newSection()] })}
          />
        </View>
      ))}

      <SmallButton testID="organization-content-add-block" label="＋ コンテンツブロックを追加" onPress={() => change([...blocks, { block_id: newId('block'), sections: [newSection()] }])} />

      {error && (
        <ThemedText testID="organization-content-layout-error" style={styles.error}>
          {error}
        </ThemedText>
      )}
      {message && (
        <ThemedText testID="organization-content-layout-saved" style={styles.success}>
          {message}
        </ThemedText>
      )}

      <View style={styles.actions}>
        <PrimaryButton testID="organization-content-layout-submit" label="コンテンツ配置を保存" pending={content.saveLayout.isPending} disabled={content.saveLayout.isPending} onPress={handleSave} />
        {publicPageUrl && (
          <TouchableOpacity testID="organization-content-view" onPress={() => Linking.openURL(publicPageUrl)}>
            <ThemedText type="link">公開ページを見る</ThemedText>
          </TouchableOpacity>
        )}
      </View>
    </ManagementCard>
  );
}

function SectionEditor({
  testID,
  data,
  section,
  index,
  count,
  onChange,
  onMove,
  onRemove,
}: {
  testID: string;
  data: PublicPageContentAdmin;
  section: ContentSection;
  index: number;
  count: number;
  onChange: (section: ContentSection) => void;
  onMove: (delta: number) => void;
  onRemove: () => void;
}) {
  const columnChoices = Array.from({ length: data.choices.max_columns }, (_, i) => i + 1);

  return (
    <View style={styles.section} testID={testID}>
      <View style={styles.headerRow}>
        <ThemedText type="smallBold">セクション {index + 1}</ThemedText>
        <View style={styles.rowButtons}>
          <SmallButton testID={`${testID}-up`} label="↑" disabled={index === 0} onPress={() => onMove(-1)} />
          <SmallButton testID={`${testID}-down`} label="↓" disabled={index === count - 1} onPress={() => onMove(1)} />
          <SmallButton testID={`${testID}-remove`} label="セクションを削除" danger onPress={onRemove} />
        </View>
      </View>

      <ThemedText type="small" themeColor="textSecondary">
        見出し（任意）
      </ThemedText>
      <FormInput testID={`${testID}-heading`} value={section.heading} onChangeText={(heading) => onChange({ ...section, heading })} style={styles.input} />

      <ThemedText type="small" themeColor="textSecondary">
        列数
      </ThemedText>
      <Chips
        testID={`${testID}-columns`}
        options={columnChoices.map((n) => ({ key: String(n), label: `${n}列` }))}
        selected={String(section.columns)}
        onSelect={(key) => onChange(withColumns(section, Number(key)))}
      />

      <ThemedText type="small" themeColor="textSecondary">
        配置
      </ThemedText>
      <Chips
        testID={`${testID}-layout`}
        options={[
          { key: 'horizontal', label: '横並び' },
          { key: 'vertical', label: '縦並び' },
        ]}
        selected={section.layout}
        onSelect={(key) => onChange({ ...section, layout: key as ContentSection['layout'] })}
      />
      <ThemedText type="small" themeColor="textSecondary">
        {section.layout === 'horizontal' ? `列数に応じて、コンテンツを横に${section.columns}列で並べます（スマートフォンでは縦に並びます）。` : 'コンテンツを縦に並べます。'}
      </ThemedText>

      {section.slots.map((slot, i) => (
        <SlotEditor
          key={i}
          testID={`${testID}-slot-${i}`}
          data={data}
          slot={slot}
          index={i}
          count={section.slots.length}
          onChange={(next) => onChange({ ...section, slots: section.slots.map((current, j) => (j === i ? next : current)) })}
          onMove={(delta) => onChange({ ...section, slots: move(section.slots, i, delta) })}
        />
      ))}
    </View>
  );
}

type SlotKind = 'content' | 'heading' | 'url' | 'none';

function slotKind(type: ContentSlotType): SlotKind {
  if (type === 'heading' || type === 'url' || type === 'none') {
    return type;
  }
  return 'content';
}

function SlotEditor({
  testID,
  data,
  slot,
  index,
  count,
  onChange,
  onMove,
}: {
  testID: string;
  data: PublicPageContentAdmin;
  slot: ContentSlot;
  index: number;
  count: number;
  onChange: (slot: ContentSlot) => void;
  onMove: (delta: number) => void;
}) {
  // "コンテンツ" with nothing picked yet is still an empty slot, so the
  // chosen kind is kept here rather than read back from the slot.
  const [pickingContent, setPickingContent] = useState(false);
  const kind: SlotKind = pickingContent && slot.type === 'none' ? 'content' : slotKind(slot.type);
  const contentKey = kind === 'content' && slot.type !== 'none' && slot.ref ? `${slot.type}:${slot.ref}` : '';

  function setKind(next: SlotKind) {
    if (next === kind) {
      return;
    }
    setPickingContent(next === 'content');
    onChange({ type: next === 'content' ? 'none' : next, ref: '', indent: slot.indent });
  }

  const groups: { label: string; options: { key: string; label: string }[] }[] = [
    { label: '団体', options: data.choices.system.map((s) => ({ key: `system:${s.key}`, label: s.label })) },
    {
      label: '公演',
      options: data.choices.productions.map((p) => ({ key: `production:${p.id}`, label: p.is_published ? p.name : `${p.name}（非公開）` })),
    },
    {
      label: 'メンバー',
      options: (data.choices.members ?? []).map((m) => ({ key: `member:${m.person_id}`, label: m.name })),
    },
    {
      label: '自由コンテンツ',
      options: data.contents.map((c) => ({ key: `free_content:${c.id}`, label: c.status === 'publish' ? c.title : `${c.title}（下書き）` })),
    },
  ];

  return (
    <View style={styles.slot} testID={testID}>
      <View style={styles.headerRow}>
        <ThemedText type="small" themeColor="textSecondary">
          {index + 1}列目
        </ThemedText>
        {count > 1 && (
          <View style={styles.rowButtons}>
            <SmallButton testID={`${testID}-left`} label="←" disabled={index === 0} onPress={() => onMove(-1)} />
            <SmallButton testID={`${testID}-right`} label="→" disabled={index === count - 1} onPress={() => onMove(1)} />
          </View>
        )}
      </View>

      <Chips
        testID={`${testID}-kind`}
        options={[
          { key: 'content', label: 'コンテンツ' },
          { key: 'heading', label: '見出し' },
          { key: 'url', label: '外部リンク' },
          { key: 'none', label: '空欄' },
        ]}
        selected={kind}
        onSelect={(key) => setKind(key as SlotKind)}
      />

      {kind === 'content' &&
        groups.map(
          (group) =>
            group.options.length > 0 && (
              <View key={group.label} style={styles.choiceGroup}>
                <ThemedText type="small" themeColor="textSecondary">
                  {group.label}
                </ThemedText>
                <Chips
                  testID={`${testID}-content`}
                  options={group.options}
                  selected={contentKey}
                  onSelect={(key) => {
                    const [type, ...rest] = key.split(':');
                    onChange({ type: type as ContentSlotType, ref: rest.join(':'), indent: slot.indent });
                  }}
                />
              </View>
            )
        )}
      {kind === 'content' && (
        <ThemedText type="small" themeColor="textSecondary" testID={`${testID}-member-note`}>
          メンバーはメンバー管理に登録されている氏名で表示されます。追加・変更はメンバー管理で行ってください。
        </ThemedText>
      )}
      {kind === 'content' && !contentKey && (
        <ThemedText type="small" style={styles.error} testID={`${testID}-unselected`}>
          表示するコンテンツを選んでください（選ばないと空欄として保存されます）。
        </ThemedText>
      )}

      {kind === 'heading' && (
        <FormInput testID={`${testID}-heading`} value={slot.ref} placeholder="見出しの文字" onChangeText={(ref) => onChange({ ...slot, ref })} style={styles.input} />
      )}
      {kind === 'url' && (
        <FormInput
          testID={`${testID}-url`}
          value={slot.ref}
          placeholder="https://"
          autoCapitalize="none"
          autoCorrect={false}
          onChangeText={(ref) => onChange({ ...slot, ref: ref.trim() })}
          style={styles.input}
        />
      )}

      <ThemedText type="small" themeColor="textSecondary">
        インデント
      </ThemedText>
      <Chips
        testID={`${testID}-indent`}
        options={Array.from({ length: data.choices.max_indent + 1 }, (_, i) => ({ key: String(i), label: i === 0 ? 'なし' : `${i}段` }))}
        selected={String(slot.indent)}
        onSelect={(key) => onChange({ ...slot, indent: Number(key) })}
      />
    </View>
  );
}

// ---------------------------------------------------------------- small parts

function Chips({ testID, options, selected, onSelect }: { testID: string; options: { key: string; label: string }[]; selected: string; onSelect: (key: string) => void }) {
  return (
    <View style={styles.chipRow}>
      {options.map((option) => {
        const isSelected = option.key === selected;
        return (
          <TouchableOpacity
            key={option.key}
            testID={`${testID}-${option.key}`}
            accessibilityRole="button"
            accessibilityState={{ selected: isSelected }}
            onPress={() => onSelect(option.key)}
            style={[styles.chip, isSelected && styles.chipSelected]}
          >
            <ThemedText type="small" style={isSelected ? styles.chipTextSelected : styles.chipText}>
              {option.label}
            </ThemedText>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

function SmallButton({ testID, label, onPress, disabled, danger }: { testID: string; label: string; onPress: () => void; disabled?: boolean; danger?: boolean }) {
  return (
    <TouchableOpacity testID={testID} onPress={onPress} disabled={disabled} style={[styles.smallButton, danger && styles.smallButtonDanger, disabled && styles.disabled]}>
      <ThemedText type="small" style={danger ? styles.smallButtonDangerText : styles.smallButtonText}>
        {label}
      </ThemedText>
    </TouchableOpacity>
  );
}

function PrimaryButton({ testID, label, onPress, disabled, pending }: { testID: string; label: string; onPress: () => void; disabled?: boolean; pending?: boolean }) {
  return (
    <TouchableOpacity testID={testID} onPress={onPress} disabled={disabled} style={[styles.button, disabled && styles.disabled]}>
      {pending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>{label}</ThemedText>}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  error: { color: '#a6483a' },
  success: { color: '#2e7d32' },
  actions: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: Spacing.four, marginTop: Spacing.two },
  button: { backgroundColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.four, alignItems: 'center' },
  buttonText: { color: '#fff', fontWeight: '600' },
  disabled: { opacity: 0.5 },
  smallButton: { alignSelf: 'flex-start', borderWidth: 1, borderColor: BrandColors.warmAmber, borderRadius: Radius.small, paddingVertical: Spacing.half, paddingHorizontal: Spacing.two },
  smallButtonText: { color: BrandColors.warmAmber },
  smallButtonDanger: { borderColor: '#a6483a' },
  smallButtonDangerText: { color: '#a6483a' },
  rowButtons: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: Spacing.one },
  headerRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: Spacing.one },
  contentRow: { borderWidth: 1, borderColor: '#e1dee6', borderRadius: Radius.small, padding: Spacing.two, gap: Spacing.one, marginBottom: Spacing.one },
  contentRowText: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: Spacing.two },
  badgePublic: { color: '#2e7d32' },
  badgeDraft: { color: '#8a6d3b' },
  formBox: { borderWidth: 1, borderColor: '#e1dee6', borderRadius: Radius.medium, padding: Spacing.three, gap: Spacing.one, backgroundColor: '#F7F5F1', marginTop: Spacing.one },
  input: { borderWidth: 1, borderColor: '#ccc', borderRadius: 8, paddingHorizontal: Spacing.three, paddingVertical: Spacing.two, fontSize: 16, marginBottom: Spacing.one },
  textarea: { minHeight: 140, textAlignVertical: 'top' },
  switchRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two, marginVertical: Spacing.one },
  switch: { flexShrink: 0 },
  switchLabel: { flexShrink: 1 },
  block: { borderWidth: 1, borderColor: '#d7d2ca', borderRadius: Radius.medium, padding: Spacing.three, gap: Spacing.two, marginBottom: Spacing.two },
  section: { borderWidth: 1, borderColor: '#e1dee6', borderRadius: Radius.small, padding: Spacing.three, gap: Spacing.one, backgroundColor: '#F7F5F1' },
  slot: { borderLeftWidth: 3, borderLeftColor: BrandColors.warmAmber, paddingLeft: Spacing.two, gap: Spacing.one, marginTop: Spacing.two },
  choiceGroup: { gap: Spacing.half },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.one },
  chip: { maxWidth: '100%', borderWidth: 1, borderColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.half, paddingHorizontal: Spacing.two, backgroundColor: '#fff' },
  chipSelected: { backgroundColor: BrandColors.warmAmber },
  chipText: { color: '#3d3a36' },
  chipTextSelected: { color: '#fff' },
});
