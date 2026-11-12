<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Organization;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Organization\OrganizationSNSService;

final class OrganizationSNSServiceTest extends TestCase
{
    /**
     * @dataProvider validValueProvider
     */
    public function test_accepts_every_confirmed_service(string $value): void
    {
        $this->assertSame($value, OrganizationSNSService::fromString($value)->toString());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validValueProvider(): array
    {
        return [
            'X' => [OrganizationSNSService::X],
            'INSTAGRAM' => [OrganizationSNSService::INSTAGRAM],
            'FACEBOOK' => [OrganizationSNSService::FACEBOOK],
            'OTHER' => [OrganizationSNSService::OTHER],
        ];
    }

    public function test_rejects_an_unknown_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OrganizationSNSService::fromString('TIKTOK');
    }

    public function test_equals(): void
    {
        $a = OrganizationSNSService::fromString(OrganizationSNSService::X);
        $b = OrganizationSNSService::fromString(OrganizationSNSService::X);
        $c = OrganizationSNSService::fromString(OrganizationSNSService::INSTAGRAM);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
