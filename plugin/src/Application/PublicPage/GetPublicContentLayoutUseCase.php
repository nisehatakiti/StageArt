<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\PublicPage\ContentLayout;
use StageArt\Domain\PublicPage\ContentLayoutRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageContentRepositoryInterface;
use StageArt\Domain\PublicPage\PublicPageSite;

/**
 * The content layout of a public Organization page, as its public
 * (unauthenticated) response carries it. Like GetPublicPageAppearanceUseCase
 * it has no publication check of its own: only the public by-slug endpoint
 * calls it, after its own publication gate has passed.
 *
 * - Never saved: null (the page keeps its earlier, fixed order).
 * - A Production slot shows only a Production the public page already
 *   lists (i.e. published); a 自由コンテンツ slot only published content
 *   of this page; a member slot only someone who is an ACTIVE, named
 *   member right now (メンバー管理), with the name alone - not the Person
 *   id, role or any other member data. Anything else (draft, deleted,
 *   unpublished, left) becomes an
 *   empty column, so a hidden item never reaches the public response and
 *   the other columns keep their places (Core renders nothing there).
 */
final class GetPublicContentLayoutUseCase
{
    private ContentLayoutRepositoryInterface $layouts;
    private PublicPageContentRepositoryInterface $contents;
    private OrganizationPublicMembersLookup $members;

    public function __construct(
        ContentLayoutRepositoryInterface $layouts,
        PublicPageContentRepositoryInterface $contents,
        OrganizationPublicMembersLookup $members
    ) {
        $this->layouts = $layouts;
        $this->contents = $contents;
        $this->members = $members;
    }

    /**
     * @param list<array{id: string, name: string, slug: string, status: string}> $publicProductions
     *        the Productions the public Organization response already lists
     */
    public function execute(string $organizationId, array $publicProductions): ?array
    {
        $site = PublicPageSite::organization();
        $layout = $this->layouts->find($site, $organizationId);

        if ($layout === null) {
            return null;
        }

        $productions = [];
        foreach ($publicProductions as $production) {
            $productions[$production['id']] = [
                'id' => $production['id'],
                'name' => $production['name'],
                'slug' => $production['slug'],
                'status' => $production['status'],
            ];
        }

        $published = [];
        foreach ($this->contents->findByOwner($site, $organizationId) as $content) {
            if ($content->isPublished()) {
                $published[$content->id()] = ['id' => $content->id(), 'title' => $content->title(), 'body' => $content->body()];
            }
        }

        $blocks = $layout->blocks();
        $members = self::hasMemberSlot($blocks) ? $this->members->forOrganization($organizationId) : [];

        foreach ($blocks as &$block) {
            foreach ($block['sections'] as &$section) {
                foreach ($section['slots'] as &$slot) {
                    $slot = self::resolve($slot, $productions, $published, $members);
                }
            }
        }

        return $blocks;
    }

    private static function hasMemberSlot(array $blocks): bool
    {
        foreach ($blocks as $block) {
            foreach ($block['sections'] as $section) {
                foreach ($section['slots'] as $slot) {
                    if ($slot['type'] === ContentLayout::SLOT_MEMBER) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private static function resolve(array $slot, array $productions, array $published, array $members): array
    {
        $empty = ['type' => ContentLayout::SLOT_NONE, 'ref' => '', 'indent' => $slot['indent']];

        if ($slot['type'] === ContentLayout::SLOT_PRODUCTION) {
            return isset($productions[$slot['ref']]) ? $slot + ['production' => $productions[$slot['ref']]] : $empty;
        }

        if ($slot['type'] === ContentLayout::SLOT_FREE_CONTENT) {
            return isset($published[$slot['ref']]) ? $slot + ['content' => $published[$slot['ref']]] : $empty;
        }

        if ($slot['type'] === ContentLayout::SLOT_MEMBER) {
            return isset($members[$slot['ref']])
                ? ['type' => ContentLayout::SLOT_MEMBER, 'ref' => '', 'indent' => $slot['indent'], 'member' => ['name' => $members[$slot['ref']]['name']]]
                : $empty;
        }

        return $slot;
    }
}
