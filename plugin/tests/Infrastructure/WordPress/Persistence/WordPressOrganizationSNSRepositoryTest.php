<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationSNS;
use StageArt\Domain\Organization\OrganizationSNSId;
use StageArt\Domain\Organization\OrganizationSNSService;
use StageArt\Infrastructure\WordPress\Persistence\WordPressOrganizationSNSRepository;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * PR #7 R14-A (N3): an SNS row stored with no profile URL (NULL, or '' from
 * older code) is read back as "no URL" instead of throwing a TypeError -
 * before R14-A one such row made the public organization page and SNS
 * management answer 500, and the row could not be updated or deleted.
 * (The in-memory repository the use case tests use never goes through
 * this read path.)
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WordPressOrganizationSNSRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
    }

    private static function row(string $id, ?string $profileUrl): array
    {
        return [
            'id' => $id,
            'organization_id' => OrganizationId::generate()->toString(),
            'service' => OrganizationSNSService::OTHER,
            'account_identifier' => 'handle',
            'profile_url' => $profileUrl,
            'display_name' => null,
            'display_order' => '0',
            'visibility' => '1',
            'created_at' => '2026-11-01 10:00:00',
            'updated_at' => '2026-11-01 10:00:00',
        ];
    }

    public function test_rows_with_no_url_an_empty_url_and_a_url_all_load(): void
    {
        $rows = [
            self::row(OrganizationSNSId::generate()->toString(), null),
            self::row(OrganizationSNSId::generate()->toString(), ''),
            self::row(OrganizationSNSId::generate()->toString(), 'https://example.com/me'),
        ];
        $wpdb = new \wpdb(static function (string $method) use ($rows) {
            if ($method === 'get_results') {
                return $rows;
            }
            if ($method === 'get_row') {
                return $rows[0];
            }

            return FakeWpdbDefault::VALUE;
        });
        $repository = new WordPressOrganizationSNSRepository($wpdb);

        $listed = $repository->findByOrganizationId(OrganizationId::generate());

        $this->assertSame([null, null, 'https://example.com/me'], array_map(static fn (OrganizationSNS $sns): ?string => $sns->profileUrl(), $listed));
        $this->assertNull($repository->findById(OrganizationSNSId::fromString($rows[0]['id']))->profileUrl());
    }

    public function test_an_entry_without_a_url_can_be_updated_and_saved(): void
    {
        $row = self::row(OrganizationSNSId::generate()->toString(), null);
        $updates = [];
        $wpdb = new \wpdb(static function (string $method, array $args) use ($row, &$updates) {
            if ($method === 'get_row') {
                return $row;
            }
            if ($method === 'get_var') {
                return $row['id'];
            }
            if ($method === 'update') {
                $updates[] = $args;
            }

            return FakeWpdbDefault::VALUE;
        });
        $repository = new WordPressOrganizationSNSRepository($wpdb);

        $sns = $repository->findById(OrganizationSNSId::fromString($row['id']));
        $sns->update(OrganizationSNSService::fromString(OrganizationSNSService::OTHER), 'new-handle', null, '表示名', 1, true);
        $repository->save($sns);

        $this->assertNotEmpty($updates, 'the row is written back');
        $this->assertNull(end($updates)[1]['profile_url']);
    }
}
