<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ParticipantInvitation;

use PHPUnit\Framework\TestCase;
use StageArt\Application\ParticipantInvitation\InvitationRecipientNotEligibleException;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * R14-B §4.3 / §6.4 / §7.4: InvitationRecipientVerifier - verified
 * addresses only, ACTIVE accounts only, exact comparison after trim +
 * lower-case, never a match for an address shared by several Persons.
 */
final class InvitationRecipientVerifierTest extends TestCase
{
    private ParticipantConsentFixture $f;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
    }

    public function test_a_verified_login_address_matches(): void
    {
        $person = $this->f->person('taro@example.com');
        $recipient = $this->f->verifier()->recipient($person->wordPressUserId());

        $this->assertSame(['taro@example.com'], $recipient->verifiedAddresses());
        $this->assertTrue($this->f->verifier()->isAddressedTo($recipient, $this->f->invitation('taro@example.com')));
    }

    public function test_a_verified_notification_address_matches(): void
    {
        $person = $this->f->person('login@example.com');
        $this->f->notificationAddress($person, 'notify@example.com', true);
        $recipient = $this->f->verifier()->recipient($person->wordPressUserId());

        $this->assertTrue($this->f->verifier()->isAddressedTo($recipient, $this->f->invitation('notify@example.com')));
    }

    public function test_an_unverified_address_is_never_used(): void
    {
        $person = $this->f->person('taro@example.com', false);
        $this->f->notificationAddress($person, 'notify@example.com', false);
        $recipient = $this->f->verifier()->recipient($person->wordPressUserId());

        $this->assertSame([], $recipient->verifiedAddresses());
        $this->assertFalse($this->f->verifier()->isAddressedTo($recipient, $this->f->invitation('taro@example.com')));
        $this->assertFalse($this->f->verifier()->isAddressedTo($recipient, $this->f->invitation('notify@example.com')));

        $this->expectException(InvitationRecipientNotEligibleException::class);
        $this->f->verifier()->recipientWithVerifiedAddress($person->wordPressUserId());
    }

    public function test_case_and_surrounding_whitespace_do_not_matter(): void
    {
        $person = $this->f->person('Taro@Example.com');
        $recipient = $this->f->verifier()->recipient($person->wordPressUserId());

        $this->assertTrue($this->f->verifier()->isAddressedTo($recipient, $this->f->invitation('TARO@example.COM')));
        $this->assertTrue($this->f->verifier()->isAddressedTo($recipient, $this->f->invitation(' taro@example.com ')));
    }

    public function test_a_similar_but_different_address_does_not_match(): void
    {
        $person = $this->f->person('taro@example.com');
        $recipient = $this->f->verifier()->recipient($person->wordPressUserId());

        $this->assertFalse($this->f->verifier()->isAddressedTo($recipient, $this->f->invitation('taro2@example.com')));
        $this->assertFalse($this->f->verifier()->isAddressedTo($recipient, $this->f->invitation('taro@example.co')));
    }

    public function test_an_address_of_more_than_one_person_matches_nobody(): void
    {
        $taro = $this->f->person('shared@example.com');
        $hanako = $this->f->person('hanako@example.com');
        $this->f->notificationAddress($hanako, 'shared@example.com', true);
        $invitation = $this->f->invitation('shared@example.com');

        $this->assertFalse($this->f->verifier()->isAddressedTo($this->f->verifier()->recipient($taro->wordPressUserId()), $invitation));
        $this->assertFalse($this->f->verifier()->isAddressedTo($this->f->verifier()->recipient($hanako->wordPressUserId()), $invitation));
    }

    public function test_someone_elses_address_does_not_match(): void
    {
        $this->f->person('taro@example.com');
        $other = $this->f->person('other@example.com');

        $this->assertFalse($this->f->verifier()->isAddressedTo(
            $this->f->verifier()->recipient($other->wordPressUserId()),
            $this->f->invitation('taro@example.com')
        ));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function blockedAccounts(): array
    {
        return ['SUSPENDED' => ['SUSPENDED'], 'DISABLED' => ['DISABLED']];
    }

    /**
     * @dataProvider blockedAccounts
     */
    public function test_a_suspended_or_disabled_account_is_refused(string $status): void
    {
        $person = $this->f->person('taro@example.com', true, $status);

        $this->expectException(InvitationRecipientNotEligibleException::class);
        $this->f->verifier()->recipient($person->wordPressUserId());
    }

    public function test_a_user_without_person_is_refused(): void
    {
        $this->expectException(InvitationRecipientNotEligibleException::class);
        $this->f->verifier()->recipient(9999);
    }
}
