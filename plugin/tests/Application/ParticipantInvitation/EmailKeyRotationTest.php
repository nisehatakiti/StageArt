<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ParticipantInvitation;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\EmailKeyNotConfiguredException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationSendLimitException;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSend;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * PR #7 R14-B stage 8 (design doc §6.7): rotating the email_key keyring.
 *
 * - New sends use the current version; sends under every version used in
 *   the last 30 days still count (two rotations inside 30 days included).
 * - A version may be removed once its last send is older than 30 days;
 *   removing one still used in the window refuses create / resend (500,
 *   never counting too few).
 * - No key value appears in a stored row or an exception message.
 */
final class EmailKeyRotationTest extends TestCase
{
    private const EMAIL = 'taro@example.com';

    private ParticipantConsentFixture $f;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
        $this->f->emailKeys = [
            'v1' => 'k1-0123456789abcdef0123456789abcdef',
            'v2' => 'k2-0123456789abcdef0123456789abcdef',
            'v3' => 'k3-0123456789abcdef0123456789abcdef',
        ];
        $this->f->currentEmailKey = 'v3';
    }

    private function pastSend(float $daysAgo, string $version): void
    {
        $this->f->sends->seed(ParticipantInvitationSend::accept(
            ParticipantInvitationId::generate(),
            $this->f->production->id(),
            hash_hmac('sha256', self::EMAIL, $this->f->emailKeys[$version]),
            $version,
            ParticipantInvitationSend::KIND_INITIAL,
            $this->f->primaryManager->id(),
            (new DateTimeImmutable())->sub(new DateInterval('PT' . (int) round($daysAgo * 86400) . 'S'))
        ));
    }

    private function invite(string $email = self::EMAIL): void
    {
        $this->f->createInvitation()->execute(new CreateParticipantInvitationCommand(
            $this->f->production->id()->toString(),
            $this->f->primaryManager->wordPressUserId(),
            '招待 太郎',
            $email,
            ParticipantType::CAST
        ));
    }

    public function test_a_new_send_uses_the_current_version_without_storing_the_key(): void
    {
        $this->invite();

        $sends = $this->f->sends->all();
        $this->assertCount(1, $sends);
        $this->assertSame('v3', $sends[0]->emailKeyVersion());
        $this->assertSame(hash_hmac('sha256', self::EMAIL, $this->f->emailKeys['v3']), $sends[0]->emailKey());
        $this->assertStringNotContainsString('k3-', serialize($sends[0]));
    }

    public function test_sends_under_three_versions_in_30_days_are_all_counted(): void
    {
        // Two rotations inside the window: v1 -> v2 -> v3.
        $this->pastSend(25, 'v1');
        $this->pastSend(25, 'v1');
        $this->pastSend(15, 'v2');
        $this->pastSend(15, 'v2');
        $this->pastSend(5, 'v3');
        $this->pastSend(5, 'v3');

        $this->expectException(ParticipantInvitationSendLimitException::class);
        $this->invite();
    }

    public function test_a_version_whose_last_send_is_older_than_30_days_can_be_removed(): void
    {
        $this->pastSend(31, 'v1');
        unset($this->f->emailKeys['v1']);

        $this->invite();

        $this->assertCount(1, $this->f->invitations->findByProductionId($this->f->production->id()));
    }

    public function test_removing_a_version_still_used_in_the_window_refuses_the_send(): void
    {
        $this->pastSend(29, 'v1');
        unset($this->f->emailKeys['v1']);

        try {
            $this->invite('someone-else@example.com');
            $this->fail('Refused: never count too few');
        } catch (EmailKeyNotConfiguredException $exception) {
            $this->assertStringNotContainsString('k1-', $exception->getMessage());
            $this->assertStringNotContainsString('k3-', $exception->getMessage());
        }

        $this->assertCount(0, $this->f->invitations->findByProductionId($this->f->production->id()));
    }

    public function test_switching_the_current_version_to_a_missing_key_refuses_the_send(): void
    {
        $this->f->currentEmailKey = 'v4';

        $this->expectException(EmailKeyNotConfiguredException::class);
        $this->invite();
    }
}
