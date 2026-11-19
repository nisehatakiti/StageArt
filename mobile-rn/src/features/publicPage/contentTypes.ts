/**
 * 公開ページのコンテンツ配置 (コンテンツ tab), StageArtCore's homepage
 * コンテンツブロック as the API stores it (plugin ContentLayout):
 *
 *   blocks[] { block_id, sections[] }
 *   sections[] { section_id, heading, columns (1-6), layout, slots[] }
 *   slots[] { type, ref, indent (0-3) }
 *
 * One slot per column. The kinds of slot are Core's, limited to what
 * StageArt can show (see ContentLayout on the plugin side). A member slot
 * refers to a member of メンバー管理 by Person id; only the id is stored.
 */
export type ContentSlotType = 'system' | 'production' | 'free_content' | 'member' | 'url' | 'heading' | 'none';
export type ContentSectionLayout = 'horizontal' | 'vertical';

export type ContentSlot = { type: ContentSlotType; ref: string; indent: number };

export type ContentSection = {
  section_id: string;
  heading: string;
  columns: number;
  layout: ContentSectionLayout;
  slots: ContentSlot[];
};

export type ContentBlock = { block_id: string; sections: ContentSection[] };

/** 自由コンテンツ (Core stageart_free_content): "draft" is kept but never shown publicly. */
export type PublicPageContent = {
  id: string;
  title: string;
  body: string;
  status: 'publish' | 'draft';
  created_at: string;
  updated_at: string;
};

/** GET /organizations/{id}/public-page/content (and the PUT .../content-layout response). */
export type PublicPageContentAdmin = {
  /** null until a layout is saved: the public page then keeps its fixed order. */
  layout: ContentBlock[] | null;
  contents: PublicPageContent[];
  choices: {
    system: { key: string; label: string }[];
    productions: { id: string; name: string; is_published: boolean }[];
    /** ACTIVE, named members from メンバー管理 (name only). Absent from an older API. */
    members?: { person_id: string; name: string }[];
    max_columns: number;
    max_indent: number;
  };
};

/** A slot of the public response's `content_layout`: what it points at is resolved by the API. */
export type PublicContentSlot =
  | (ContentSlot & { type: 'system' | 'url' | 'heading' | 'none' })
  | (ContentSlot & { type: 'production'; production: { id: string; name: string; slug: string; status: string } })
  | (ContentSlot & { type: 'free_content'; content: { id: string; title: string; body: string } })
  /** The name as メンバー管理 has it now; the API gives nothing else about the member (ref is empty). */
  | (ContentSlot & { type: 'member'; member: { name: string } });

export type PublicContentBlock = {
  block_id: string;
  sections: (Omit<ContentSection, 'slots'> & { slots: PublicContentSlot[] })[];
};
