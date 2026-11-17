<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\ParticipantEvent;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\Production\ProductionId;

/** R14-B §6.3: an audit event holds ids and state facts - never an address, a name or a token. */
final class ParticipantEventTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenKeys(): array
    {
        return [
            'email' => ['email'],
            'name' => ['name'],
            'invited_name' => ['invited_name'],
            'display_name' => ['display_name'],
            'token' => ['token'],
            'token_hash' => ['token_hash'],
            'upper case' => ['Email'],
        ];
    }

    /**
     * @dataProvider forbiddenKeys
     */
    public function test_personal_data_and_token_keys_are_refused(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        ParticipantEvent::record(ParticipantEvent::ACCEPTED, ProductionId::generate(), new DateTimeImmutable(), null, null, null, null, null, null, [$key => 'x']);
    }

    public function test_only_scalar_detail_values_and_known_types(): void
    {
        try {
            ParticipantEvent::record(ParticipantEvent::ACCEPTED, ProductionId::generate(), new DateTimeImmutable(), null, null, null, null, null, null, ['previous_status' => ['nested']]);
            $this->fail('A nested value must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        ParticipantEvent::record('SOMETHING_ELSE', ProductionId::generate(), new DateTimeImmutable());
    }

    public function test_a_state_transition_detail_is_kept(): void
    {
        $event = ParticipantEvent::record(
            ParticipantEvent::DELEGATE_DEACTIVATED,
            ProductionId::generate(),
            new DateTimeImmutable(),
            null,
            null,
            null,
            null,
            null,
            null,
            ['reason' => 'DORMANT_ON_ENROLLMENT', 'role' => 'PARTICIPANT_MANAGER', 'notify' => false, 'previous_status' => null]
        );

        $this->assertSame(['reason' => 'DORMANT_ON_ENROLLMENT', 'role' => 'PARTICIPANT_MANAGER', 'notify' => false, 'previous_status' => null], $event->detail());
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $event->id());
    }
}
