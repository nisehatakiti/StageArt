<?php

declare(strict_types=1);

namespace StageArt\Domain\PublicPage;

use InvalidArgumentException;
use StageArt\Domain\Shared\Uuid;

/**
 * Where a public page's contents go: StageArtCore's homepage
 * "コンテンツブロック" structure (SiteStructure::homepageContentBlocks() /
 * SiteStructureAdmin::saveHomepage()), kept as Core stores it:
 *
 *   blocks[]   { block_id, sections[] }
 *   sections[] { section_id, heading, columns, layout, slots[] }
 *   slots[]    { type, ref, indent }
 *
 * - columns: 1-6 (Core's ContentBlockAdmin limit for the homepage blocks),
 *   one slot per column; extra slots are cut off as Core does.
 * - layout: "horizontal" (the slots side by side in `columns` columns) or
 *   "vertical" (stacked).
 * - indent: 0-3.
 * - slot types are Core's, limited to what StageArt can show:
 *   system (productions = 公演一覧, about = 団体について), production (one
 *   Production of this page's owner), free_content (one of this page's own
 *   自由コンテンツ), member (one ACTIVE member of the Organization, by
 *   Person id - the name comes from メンバー管理 when the page is shown,
 *   nothing about the member is stored here), url (an http(s) link),
 *   heading (a text heading) and none (an empty column). Core's other
 *   system entries (members, representative_greeting, news, contact,
 *   home) have no StageArt counterpart yet and are rejected.
 *
 * Unlike Core (which silently drops what it cannot use), anything invalid
 * is rejected (InvalidArgumentException -> 422), so a save never loses
 * part of the layout without saying so. Whether a referenced Production /
 * 自由コンテンツ belongs to this page, or a Person is one of its members,
 * is checked by the caller through the callbacks.
 */
final class ContentLayout
{
    public const MIN_COLUMNS = 1;
    public const MAX_COLUMNS = 6;
    public const MAX_INDENT = 3;
    public const MAX_BLOCKS = 20;
    public const MAX_SECTIONS_PER_BLOCK = 20;
    public const MAX_HEADING_LENGTH = 100;
    public const MAX_URL_LENGTH = 500;

    public const LAYOUT_HORIZONTAL = 'horizontal';
    public const LAYOUT_VERTICAL = 'vertical';

    public const SLOT_SYSTEM = 'system';
    public const SLOT_PRODUCTION = 'production';
    public const SLOT_FREE_CONTENT = 'free_content';
    public const SLOT_MEMBER = 'member';
    public const SLOT_URL = 'url';
    public const SLOT_HEADING = 'heading';
    public const SLOT_NONE = 'none';

    public const SLOT_TYPES = [self::SLOT_SYSTEM, self::SLOT_PRODUCTION, self::SLOT_FREE_CONTENT, self::SLOT_MEMBER, self::SLOT_URL, self::SLOT_HEADING, self::SLOT_NONE];

    /** Core SiteStructure::systemLabels(), the entries StageArt can show. */
    public const SYSTEM_LABELS = ['productions' => '公演一覧', 'about' => '団体について'];

    /** @var list<array{block_id: string, sections: list<array{section_id: string, heading: string, columns: int, layout: string, slots: list<array{type: string, ref: string, indent: int}>}>}> */
    private array $blocks;

    /** @param list<array{block_id: string, sections: list<array{section_id: string, heading: string, columns: int, layout: string, slots: list<array{type: string, ref: string, indent: int}>}>}> $blocks */
    private function __construct(array $blocks)
    {
        $this->blocks = $blocks;
    }

    /**
     * @param mixed                   $blocks          the "blocks" list as sent
     * @param callable(string): bool  $productionOwned whether a Production id belongs to this page's owner
     * @param callable(string): bool  $contentOwned    whether a 自由コンテンツ id belongs to this page
     * @param callable(string): bool|null $memberListed whether a Person id is a member who may be shown
     *        (none given: no member can be placed)
     */
    public static function fromInput($blocks, callable $productionOwned, callable $contentOwned, ?callable $memberListed = null): self
    {
        $memberListed = $memberListed ?? static fn (): bool => false;

        if (! is_array($blocks) || ! self::isList($blocks)) {
            throw new InvalidArgumentException('blocks must be a list.');
        }

        if (count($blocks) > self::MAX_BLOCKS) {
            throw new InvalidArgumentException(sprintf('コンテンツブロックは%d個までです。', self::MAX_BLOCKS));
        }

        $out = [];
        $usedIds = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                throw new InvalidArgumentException('Each block must be an object.');
            }

            $sections = $block['sections'] ?? [];

            if (! is_array($sections) || ! self::isList($sections)) {
                throw new InvalidArgumentException('block.sections must be a list.');
            }

            if (count($sections) > self::MAX_SECTIONS_PER_BLOCK) {
                throw new InvalidArgumentException(sprintf('1つのブロックのセクションは%d個までです。', self::MAX_SECTIONS_PER_BLOCK));
            }

            $outSections = [];

            foreach ($sections as $section) {
                $outSections[] = self::section($section, $usedIds, $productionOwned, $contentOwned, $memberListed);
            }

            $out[] = ['block_id' => self::id($block['block_id'] ?? null, $usedIds), 'sections' => $outSections];
        }

        return new self($out);
    }

    /**
     * A stored layout, read back as saved. Nothing is re-checked against the
     * current Productions / 自由コンテンツ here: what no longer resolves is
     * left out when the page is shown (see the public read).
     *
     * @param array<mixed> $stored
     */
    public static function fromStored(array $stored): self
    {
        try {
            return self::fromInput(array_values($stored), static fn (): bool => true, static fn (): bool => true, static fn (): bool => true);
        } catch (InvalidArgumentException $exception) {
            // A stored value that no longer validates (e.g. a slot type
            // removed since) never breaks the page: the layout is empty.
            return new self([]);
        }
    }

    /** @return list<array{block_id: string, sections: list<array{section_id: string, heading: string, columns: int, layout: string, slots: list<array{type: string, ref: string, indent: int}>}>}> */
    public function blocks(): array
    {
        return $this->blocks;
    }

    /**
     * @param mixed                $section
     * @param array<string, true>  $usedIds
     */
    private static function section($section, array &$usedIds, callable $productionOwned, callable $contentOwned, callable $memberListed): array
    {
        if (! is_array($section)) {
            throw new InvalidArgumentException('Each section must be an object.');
        }

        $columns = $section['columns'] ?? 1;

        if (! is_int($columns) || $columns < self::MIN_COLUMNS || $columns > self::MAX_COLUMNS) {
            throw new InvalidArgumentException(sprintf('列数は%d〜%dで指定してください。', self::MIN_COLUMNS, self::MAX_COLUMNS));
        }

        $layout = $section['layout'] ?? self::LAYOUT_HORIZONTAL;

        if (! in_array($layout, [self::LAYOUT_HORIZONTAL, self::LAYOUT_VERTICAL], true)) {
            throw new InvalidArgumentException('section.layout must be "horizontal" or "vertical".');
        }

        $slots = $section['slots'] ?? [];

        if (! is_array($slots) || ! self::isList($slots)) {
            throw new InvalidArgumentException('section.slots must be a list.');
        }

        $outSlots = [];

        // One slot per column, extra ones cut off (Core: array_slice(slots, 0, columns)).
        foreach (array_slice($slots, 0, $columns) as $slot) {
            $outSlots[] = self::slot($slot, $productionOwned, $contentOwned, $memberListed);
        }

        return [
            'section_id' => self::id($section['section_id'] ?? null, $usedIds),
            'heading' => self::text($section['heading'] ?? '', 'section.heading', self::MAX_HEADING_LENGTH),
            'columns' => $columns,
            'layout' => $layout,
            'slots' => $outSlots,
        ];
    }

    /** @param mixed $slot */
    private static function slot($slot, callable $productionOwned, callable $contentOwned, callable $memberListed): array
    {
        if (! is_array($slot)) {
            throw new InvalidArgumentException('Each slot must be an object.');
        }

        $type = $slot['type'] ?? self::SLOT_NONE;
        $ref = $slot['ref'] ?? '';
        $indent = $slot['indent'] ?? 0;

        if (! is_string($type) || ! in_array($type, self::SLOT_TYPES, true)) {
            throw new InvalidArgumentException('この種類のコンテンツは配置できません。');
        }

        if (! is_string($ref)) {
            throw new InvalidArgumentException('slot.ref must be a string.');
        }

        if (! is_int($indent) || $indent < 0 || $indent > self::MAX_INDENT) {
            throw new InvalidArgumentException(sprintf('インデントは0〜%dで指定してください。', self::MAX_INDENT));
        }

        $ref = trim($ref);

        switch ($type) {
            case self::SLOT_NONE:
                $ref = '';
                break;
            case self::SLOT_SYSTEM:
                if (! isset(self::SYSTEM_LABELS[$ref])) {
                    throw new InvalidArgumentException('この団体コンテンツは配置できません。');
                }
                break;
            case self::SLOT_PRODUCTION:
                if (! Uuid::isValid($ref) || ! $productionOwned($ref)) {
                    throw new InvalidArgumentException('この団体の公演ではありません。');
                }
                break;
            case self::SLOT_FREE_CONTENT:
                if (! Uuid::isValid($ref) || ! $contentOwned($ref)) {
                    throw new InvalidArgumentException('この公開ページの自由コンテンツではありません。');
                }
                break;
            case self::SLOT_MEMBER:
                if (! Uuid::isValid($ref) || ! $memberListed($ref)) {
                    throw new InvalidArgumentException('この団体のメンバーではありません。');
                }
                break;
            case self::SLOT_URL:
                if (strlen($ref) > self::MAX_URL_LENGTH || ! preg_match('#^https?://[^\s/$.?\#][^\s]*$#i', $ref)) {
                    throw new InvalidArgumentException('リンクには http:// または https:// で始まるURLを指定してください。');
                }
                break;
            case self::SLOT_HEADING:
                $ref = self::text($ref, 'slot heading', self::MAX_HEADING_LENGTH);

                if ($ref === '') {
                    throw new InvalidArgumentException('見出しを入力してください。');
                }
                break;
        }

        return ['type' => $type, 'ref' => $ref, 'indent' => $indent];
    }

    /**
     * Core keeps a block/section id given by the editor (sanitize_key) and
     * makes one otherwise; a duplicate gets a new one.
     *
     * @param mixed               $value
     * @param array<string, true> $usedIds
     */
    private static function id($value, array &$usedIds): string
    {
        $id = is_string($value) && preg_match('/^[a-z0-9_-]{1,64}$/', $value) ? $value : '';

        if ($id === '' || isset($usedIds[$id])) {
            $id = Uuid::generate();
        }

        $usedIds[$id] = true;

        return $id;
    }

    /** @param array<mixed> $value array_is_list() (PHP 8.1+), for the plugin's PHP 8.0 floor. */
    private static function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    /** @param mixed $value */
    private static function text($value, string $field, int $max): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("{$field} must be a string.");
        }

        // One line, as Core's sanitize_text_field() keeps it.
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException(sprintf('見出しは%d文字以内で入力してください。', $max));
        }

        return $value;
    }
}
