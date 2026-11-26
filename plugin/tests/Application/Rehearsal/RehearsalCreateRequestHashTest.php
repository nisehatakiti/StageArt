<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Rehearsal;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Rehearsal\RehearsalCreateRequestHash;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\RehearsalActivityType;

/**
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §26.3): the normalized
 * fields and rules of the create-request content hash.
 */
final class RehearsalCreateRequestHashTest extends TestCase
{
    private function jst(string $value): DateTimeImmutable
    {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Asia/Tokyo'));
    }

    public function test_the_normalized_request(): void
    {
        $productionId = ProductionId::fromString('81749910-C793-4551-AD98-2A6BEDF4FE8E');

        $normalized = RehearsalCreateRequestHash::normalize(
            $productionId,
            '  通し稽古 ',
            "1行目\r\n2行目",
            $this->jst('2099-08-20T09:00:00Z'),
            $this->jst('2099-08-20T21:00:00+09:00'),
            null,
            'Asia/Tokyo',
            '',
            ['B-2', 'a-1', 'b-2'],
            null,
            false
        );

        $this->assertSame([
            'v' => 'rehearsal-create/1',
            'production_id' => '81749910-c793-4551-ad98-2a6bedf4fe8e',
            'title' => '通し稽古',
            'description' => "1行目\n2行目",
            'start_date_time' => '2099-08-20T18:00:00+09:00',
            'end_date_time' => '2099-08-20T21:00:00+09:00',
            'response_deadline' => null,
            'timezone' => 'Asia/Tokyo',
            'location' => null,
            'person_ids' => ['a-1', 'b-2'],
            'activity_type' => 'REHEARSAL',
            'confirm_on_create' => false,
        ], $normalized);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', RehearsalCreateRequestHash::hash($normalized));
    }

    public function test_equivalent_requests_hash_the_same_and_different_ones_do_not(): void
    {
        $productionId = ProductionId::generate();
        $base = fn (array $o = []): string => RehearsalCreateRequestHash::hash(RehearsalCreateRequestHash::normalize(
            $productionId,
            $o['title'] ?? '稽古',
            null,
            $this->jst($o['start'] ?? '2099-08-20T18:00:00+09:00'),
            $this->jst('2099-08-20T21:00:00+09:00'),
            null,
            'Asia/Tokyo',
            null,
            $o['persons'] ?? ['p1', 'p2'],
            $o['activity'] ?? null,
            $o['confirm'] ?? false
        ));

        $this->assertSame($base(), $base(['start' => '2099-08-20T09:00:00Z', 'persons' => ['p2', 'p1', 'p1'], 'activity' => RehearsalActivityType::rehearsal()]));
        $this->assertNotSame($base(), $base(['title' => '稽古2']));
        $this->assertNotSame($base(), $base(['start' => '2099-08-20T18:01:00+09:00']));
        $this->assertNotSame($base(), $base(['persons' => ['p1']]));
        $this->assertNotSame($base(), $base(['confirm' => true]));
        $this->assertNotSame($base(), $base(['activity' => RehearsalActivityType::fromString('MEETING')]));
    }
}
