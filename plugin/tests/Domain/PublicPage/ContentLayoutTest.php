<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\PublicPage;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\PublicPage\ContentLayout;

/**
 * The コンテンツ配置 rules taken from StageArtCore's homepage コンテンツブロック
 * (SiteStructure / SiteStructureAdmin / ContentBlockAdmin).
 */
final class ContentLayoutTest extends TestCase
{
    private const PRODUCTION = '11111111-1111-4111-8111-111111111111';
    private const CONTENT = '22222222-2222-4222-8222-222222222222';
    private const OTHER = '33333333-3333-4333-8333-333333333333';
    private const MEMBER = '44444444-4444-4444-8444-444444444444';

    private static function layout(array $blocks): ContentLayout
    {
        return ContentLayout::fromInput(
            $blocks,
            static fn (string $id): bool => $id === self::PRODUCTION,
            static fn (string $id): bool => $id === self::CONTENT,
            static fn (string $id): bool => $id === self::MEMBER
        );
    }

    private static function section(array $slots, int $columns = 1, string $layout = 'horizontal', array $extra = []): array
    {
        return $extra + ['heading' => '公演', 'columns' => $columns, 'layout' => $layout, 'slots' => $slots];
    }

    public function test_keeps_blocks_sections_and_slots_in_order_as_core_stores_them(): void
    {
        $blocks = self::layout([
            ['block_id' => 'block-a', 'sections' => [
                self::section([
                    ['type' => 'system', 'ref' => 'productions', 'indent' => 0],
                    ['type' => 'production', 'ref' => self::PRODUCTION, 'indent' => 1],
                    ['type' => 'free_content', 'ref' => self::CONTENT, 'indent' => 0],
                ], 3, 'horizontal', ['section_id' => 'sec-1']),
                self::section([['type' => 'heading', 'ref' => '  お知らせ  ', 'indent' => 2]], 1, 'vertical', ['section_id' => 'sec-2']),
            ]],
            ['block_id' => 'block-b', 'sections' => [self::section([['type' => 'url', 'ref' => 'https://example.com/a', 'indent' => 0]])]],
        ])->blocks();

        $this->assertSame(['block-a', 'block-b'], array_column($blocks, 'block_id'));
        $this->assertSame(['sec-1', 'sec-2'], array_column($blocks[0]['sections'], 'section_id'));
        $this->assertSame(
            [
                ['type' => 'system', 'ref' => 'productions', 'indent' => 0],
                ['type' => 'production', 'ref' => self::PRODUCTION, 'indent' => 1],
                ['type' => 'free_content', 'ref' => self::CONTENT, 'indent' => 0],
            ],
            $blocks[0]['sections'][0]['slots']
        );
        $this->assertSame(['columns' => 3, 'layout' => 'horizontal'], array_intersect_key($blocks[0]['sections'][0], ['columns' => 0, 'layout' => 0]));
        $this->assertSame([['type' => 'heading', 'ref' => 'お知らせ', 'indent' => 2]], $blocks[0]['sections'][1]['slots']);
        $this->assertSame('vertical', $blocks[0]['sections'][1]['layout']);
    }

    public function test_cuts_slots_beyond_the_column_count_like_core(): void
    {
        $slots = self::layout([['sections' => [self::section([
            ['type' => 'system', 'ref' => 'productions'],
            ['type' => 'system', 'ref' => 'about'],
            ['type' => 'none'],
        ], 2)]]])->blocks()[0]['sections'][0]['slots'];

        $this->assertCount(2, $slots);
        $this->assertSame(['productions', 'about'], array_column($slots, 'ref'));
    }

    public function test_makes_missing_or_duplicate_ids(): void
    {
        $blocks = self::layout([
            ['block_id' => 'same', 'sections' => [self::section([], 1, 'horizontal', ['section_id' => 'same'])]],
            ['sections' => []],
        ])->blocks();

        $ids = [$blocks[0]['block_id'], $blocks[0]['sections'][0]['section_id'], $blocks[1]['block_id']];
        $this->assertSame('same', $ids[1]);
        $this->assertCount(3, array_unique($ids));
    }

    public function test_an_empty_layout_is_allowed(): void
    {
        $this->assertSame([], self::layout([])->blocks());
    }

    /** @dataProvider invalidInputs */
    public function test_rejects_invalid_input(array $blocks): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::layout($blocks);
    }

    /** @return array<string, array{0: array}> */
    public static function invalidInputs(): array
    {
        return [
            'columns 0' => [[['sections' => [self::section([], 0)]]]],
            'columns 7 (Core homepage blocks allow 1-6)' => [[['sections' => [self::section([], 7)]]]],
            'columns as string' => [[['sections' => [self::section([], 1, 'horizontal', ['columns' => '2'])]]]],
            'unknown layout' => [[['sections' => [self::section([], 1, 'grid')]]]],
            'indent 4' => [[['sections' => [self::section([['type' => 'none', 'indent' => 4]])]]]],
            'member slot with a non-id ref' => [[['sections' => [self::section([['type' => 'member', 'ref' => '1']])]]]],
            'someone who is not a listed member' => [[['sections' => [self::section([['type' => 'member', 'ref' => self::OTHER]])]]]],
            'Core system entry StageArt cannot show' => [[['sections' => [self::section([['type' => 'system', 'ref' => 'news']])]]]],
            'another organization\'s production' => [[['sections' => [self::section([['type' => 'production', 'ref' => self::OTHER]])]]]],
            'another page\'s free content' => [[['sections' => [self::section([['type' => 'free_content', 'ref' => self::OTHER]])]]]],
            'non-uuid production' => [[['sections' => [self::section([['type' => 'production', 'ref' => '12']])]]]],
            'javascript url' => [[['sections' => [self::section([['type' => 'url', 'ref' => 'javascript:alert(1)']])]]]],
            'empty heading slot' => [[['sections' => [self::section([['type' => 'heading', 'ref' => '   ']])]]]],
            'too long heading' => [[['sections' => [self::section([], 1, 'horizontal', ['heading' => str_repeat('あ', 101)])]]]],
            'blocks not a list' => [['a' => ['sections' => []]]],
            'too many blocks' => [array_fill(0, 21, ['sections' => []])],
        ];
    }

    public function test_a_listed_member_is_kept_by_person_id_alone(): void
    {
        $slots = self::layout([['sections' => [self::section([['type' => 'member', 'ref' => self::MEMBER, 'indent' => 1, 'name' => '山田 太郎']])]]])
            ->blocks()[0]['sections'][0]['slots'];

        $this->assertSame([['type' => 'member', 'ref' => self::MEMBER, 'indent' => 1]], $slots, 'nothing else about the member is stored');
    }

    public function test_without_a_member_check_no_member_can_be_placed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ContentLayout::fromInput(
            [['sections' => [self::section([['type' => 'member', 'ref' => self::MEMBER]])]]],
            static fn (): bool => true,
            static fn (): bool => true
        );
    }

    public function test_a_stored_layout_that_no_longer_validates_reads_as_empty(): void
    {
        $this->assertSame([], ContentLayout::fromStored([['sections' => [['columns' => 99, 'slots' => []]]]])->blocks());
    }

    public function test_a_stored_layout_is_read_back_without_reference_checks(): void
    {
        $stored = [['block_id' => 'b', 'sections' => [['section_id' => 's', 'heading' => '', 'columns' => 1, 'layout' => 'vertical', 'slots' => [
            ['type' => 'production', 'ref' => self::OTHER, 'indent' => 0],
        ]]]]];

        $this->assertSame(self::OTHER, ContentLayout::fromStored($stored)->blocks()[0]['sections'][0]['slots'][0]['ref']);
    }
}
