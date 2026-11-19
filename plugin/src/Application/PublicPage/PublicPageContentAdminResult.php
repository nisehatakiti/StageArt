<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\Production\Production;
use StageArt\Domain\PublicPage\ContentLayout;
use StageArt\Domain\PublicPage\PublicPageContent;

/**
 * What the コンテンツ tab works with: the saved layout (null when never
 * saved), this page's 自由コンテンツ, and what can be placed (Core
 * SiteStructureAdmin::picker(): the system entries, this Organization's
 * Productions, its members (name only, from メンバー管理) and its
 * 自由コンテンツ, plus heading / link / empty slots).
 */
final class PublicPageContentAdminResult
{
    private ?ContentLayout $layout;
    /** @var list<PublicPageContent> */
    private array $contents;
    /** @var list<Production> */
    private array $productions;
    /** @var list<array{person_id: string, name: string}> */
    private array $members;

    /**
     * @param list<PublicPageContent>                      $contents
     * @param list<Production>                             $productions
     * @param list<array{person_id: string, name: string}> $members
     */
    public function __construct(?ContentLayout $layout, array $contents, array $productions, array $members = [])
    {
        $this->layout = $layout;
        $this->contents = $contents;
        $this->productions = $productions;
        $this->members = $members;
    }

    public function toArray(): array
    {
        $contentIds = array_map(static fn (PublicPageContent $content): string => $content->id(), $this->contents);
        $productionIds = array_map(static fn (Production $production): string => $production->id()->toString(), $this->productions);
        $memberIds = array_map(static fn (array $member): string => $member['person_id'], $this->members);

        return [
            'layout' => $this->layout === null ? null : self::withoutMissing($this->layout->blocks(), $productionIds, $contentIds, $memberIds),
            'contents' => array_map(static fn (PublicPageContent $content): array => self::content($content), $this->contents),
            'choices' => [
                'system' => array_map(
                    static fn (string $key, string $label): array => ['key' => $key, 'label' => $label],
                    array_keys(ContentLayout::SYSTEM_LABELS),
                    array_values(ContentLayout::SYSTEM_LABELS)
                ),
                'productions' => array_map(static fn (Production $production): array => [
                    'id' => $production->id()->toString(),
                    'name' => $production->name()->toString(),
                    'is_published' => $production->isPublished(),
                ], $this->productions),
                'members' => array_map(static fn (array $member): array => [
                    'person_id' => $member['person_id'],
                    'name' => $member['name'],
                ], $this->members),
                'max_columns' => ContentLayout::MAX_COLUMNS,
                'max_indent' => ContentLayout::MAX_INDENT,
            ],
        ];
    }

    public static function content(PublicPageContent $content): array
    {
        return [
            'id' => $content->id(),
            'title' => $content->title(),
            'body' => $content->body(),
            'status' => $content->status(),
            'created_at' => $content->createdAt()->format(DATE_ATOM),
            'updated_at' => $content->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * A Production, 自由コンテンツ or member (left, removed, or no longer
     * named) gone since the layout was saved
     * becomes an empty column (Core reads such a layout and shows nothing
     * for it), so the editor never offers a reference it cannot save.
     *
     * @param list<string> $productionIds
     * @param list<string> $contentIds
     * @param list<string> $memberIds
     */
    private static function withoutMissing(array $blocks, array $productionIds, array $contentIds, array $memberIds): array
    {
        foreach ($blocks as &$block) {
            foreach ($block['sections'] as &$section) {
                foreach ($section['slots'] as &$slot) {
                    $missing = ($slot['type'] === ContentLayout::SLOT_PRODUCTION && ! in_array($slot['ref'], $productionIds, true))
                        || ($slot['type'] === ContentLayout::SLOT_FREE_CONTENT && ! in_array($slot['ref'], $contentIds, true))
                        || ($slot['type'] === ContentLayout::SLOT_MEMBER && ! in_array($slot['ref'], $memberIds, true));

                    if ($missing) {
                        $slot = ['type' => ContentLayout::SLOT_NONE, 'ref' => '', 'indent' => $slot['indent']];
                    }
                }
            }
        }

        return $blocks;
    }
}
