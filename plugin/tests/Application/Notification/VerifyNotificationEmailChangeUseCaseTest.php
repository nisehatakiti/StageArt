<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Notification;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\InvalidNotificationEmailChangeTokenException;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Application\Notification\VerifyNotificationEmailChangeCommand;
use StageArt\Application\Notification\VerifyNotificationEmailChangeUseCase;
use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Notification\NotificationEmailChangeRequest;
use StageArt\Domain\Person\Person;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\FakeWordPressUserLookup;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailChangeRequestRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

/**
 * 通知用Email確認・変更機能 §6/§7/§9/§23「Verification」/AC-04, AC-06,
 * AC-07, AC-08, AC-12, AC-14, AC-16, AC-17.
 */
final class VerifyNotificationEmailChangeUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryEmailCredentialRepository $emailCredentials;
    private InMemoryNotificationEmailRepository $notificationEmails;
    private InMemoryNotificationEmailChangeRequestRepository $changeRequests;
    private VerifyNotificationEmailChangeUseCase $useCase;
    private PersonEmailResolver $resolver;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $this->notificationEmails = new InMemoryNotificationEmailRepository();
        $this->changeRequests = new InMemoryNotificationEmailChangeRequestRepository();

        $this->useCase = new VerifyNotificationEmailChangeUseCase(
            $this->changeRequests,
            $this->notificationEmails,
            new InMemoryTransactionManager()
        );
        $this->resolver = new PersonEmailResolver(
            $this->people,
            $this->userAccounts,
            $this->emailCredentials,
            new FakeWordPressUserLookup(),
            $this->notificationEmails
        );
    }

    private function usableChangeRequest(
        \StageArt\Domain\Person\PersonId $personId,
        string $candidateEmail,
        string $tokenValue
    ): NotificationEmailChangeRequest {
        return NotificationEmailChangeRequest::create(
            $personId,
            $candidateEmail,
            hash('sha256', $tokenValue),
            (new DateTimeImmutable())->add(new DateInterval('PT24H'))
        );
    }

    // 9 & 10. 正しいtokenでverification成功 → NotificationEmailが新Emailになる
    public function test_a_valid_token_promotes_the_candidate_email_to_notification_email(): void
    {
        $person = Person::create(1);
        $this->people->save($person);
        $this->notificationEmails->save(NotificationEmail::create($person->id(), 'old@example.com', true, 'GOOGLE'));
        $this->changeRequests->save($this->usableChangeRequest($person->id(), 'new@example.com', 'good-token'));

        $this->useCase->execute(new VerifyNotificationEmailChangeCommand('good-token'));

        $notificationEmail = $this->notificationEmails->findByPersonId($person->id());
        $this->assertSame('new@example.com', $notificationEmail->email());
        $this->assertTrue($notificationEmail->verified());
        $this->assertSame(NotificationEmail::SOURCE_USER, $notificationEmail->source());
    }

    /** Same as above but the Person had no NotificationEmail row yet at all. */
    public function test_a_valid_token_creates_a_notification_email_when_none_existed(): void
    {
        $person = Person::create(2);
        $this->people->save($person);
        $this->changeRequests->save($this->usableChangeRequest($person->id(), 'new@example.com', 'good-token-2'));

        $this->useCase->execute(new VerifyNotificationEmailChangeCommand('good-token-2'));

        $this->assertSame('new@example.com', $this->notificationEmails->findByPersonId($person->id())->email());
    }

    // 11. 不正tokenでは変更されない / AC-07
    public function test_an_unknown_token_is_rejected_and_changes_nothing(): void
    {
        $person = Person::create(3);
        $this->people->save($person);
        $this->notificationEmails->save(NotificationEmail::create($person->id(), 'old@example.com', true, 'GOOGLE'));

        $this->expectException(InvalidNotificationEmailChangeTokenException::class);
        try {
            $this->useCase->execute(new VerifyNotificationEmailChangeCommand('never-issued'));
        } finally {
            $this->assertSame('old@example.com', $this->notificationEmails->findByPersonId($person->id())->email());
        }
    }

    // 12. expired tokenでは変更されない
    public function test_an_expired_token_is_rejected_and_changes_nothing(): void
    {
        $person = Person::create(4);
        $this->people->save($person);
        $this->notificationEmails->save(NotificationEmail::create($person->id(), 'old@example.com', true, 'GOOGLE'));
        $this->changeRequests->save(NotificationEmailChangeRequest::create(
            $person->id(),
            'new@example.com',
            hash('sha256', 'expired-token'),
            (new DateTimeImmutable())->sub(new DateInterval('PT1H'))
        ));

        $this->expectException(InvalidNotificationEmailChangeTokenException::class);
        try {
            $this->useCase->execute(new VerifyNotificationEmailChangeCommand('expired-token'));
        } finally {
            $this->assertSame('old@example.com', $this->notificationEmails->findByPersonId($person->id())->email());
        }
    }

    // 13. 使用済みtokenでは再変更できない
    public function test_an_already_consumed_token_cannot_be_reused(): void
    {
        $person = Person::create(5);
        $this->people->save($person);
        $this->changeRequests->save($this->usableChangeRequest($person->id(), 'new@example.com', 'one-shot-token'));

        $this->useCase->execute(new VerifyNotificationEmailChangeCommand('one-shot-token'));

        $this->expectException(InvalidNotificationEmailChangeTokenException::class);
        $this->useCase->execute(new VerifyNotificationEmailChangeCommand('one-shot-token'));
    }

    // 14. 古いtokenで新しい変更要求を上書きできない / AC-08
    public function test_a_superseded_token_no_longer_verifies_the_newer_request(): void
    {
        $person = Person::create(6);
        $this->people->save($person);
        $first = $this->usableChangeRequest($person->id(), 'new1@example.com', 'old-token');
        $this->changeRequests->save($first);

        // A second request supersedes the first in place (same row, same
        // person_id unique key - mirrors RequestNotificationEmailChangeUseCase's
        // own replaceWith() call).
        $first->replaceWith('new2@example.com', hash('sha256', 'new-token'), (new DateTimeImmutable())->add(new DateInterval('PT24H')));
        $this->changeRequests->save($first);

        $this->expectException(InvalidNotificationEmailChangeTokenException::class);
        $this->useCase->execute(new VerifyNotificationEmailChangeCommand('old-token'));
    }

    // 15. Personの異なるtokenを使用できない
    public function test_a_tokens_effect_is_scoped_to_the_person_who_requested_it(): void
    {
        $personA = Person::create(7);
        $personB = Person::create(8);
        $this->people->save($personA);
        $this->people->save($personB);
        $this->notificationEmails->save(NotificationEmail::create($personB->id(), 'b-untouched@example.com', true, 'GOOGLE'));
        $this->changeRequests->save($this->usableChangeRequest($personA->id(), 'a-new@example.com', 'a-token'));

        $this->useCase->execute(new VerifyNotificationEmailChangeCommand('a-token'));

        $this->assertSame('a-new@example.com', $this->notificationEmails->findByPersonId($personA->id())->email());
        $this->assertSame(
            'b-untouched@example.com',
            $this->notificationEmails->findByPersonId($personB->id())->email(),
            "Person A's token must never affect Person B's NotificationEmail."
        );
    }

    // 20. NotificationEmail変更時にEmailCredentialが変更されない / AC-12
    public function test_verifying_never_touches_the_email_credential(): void
    {
        $person = Person::create(9);
        $this->people->save($person);
        $userAccount = UserAccount::create($person->id());
        $this->userAccounts->save($userAccount);
        $this->emailCredentials->save(EmailCredential::create($userAccount->id(), 'foo@example.com', 'hash'));
        $this->changeRequests->save($this->usableChangeRequest($person->id(), 'another@example.com', 'credential-safe-token'));

        $this->useCase->execute(new VerifyNotificationEmailChangeCommand('credential-safe-token'));

        $credential = $this->emailCredentials->findByUserAccountId($userAccount->id());
        $this->assertSame('foo@example.com', $credential->email(), 'EmailCredential must never be modified by a NotificationEmail change.');
    }

    // 21. 新しいNotificationEmailへの変更後、Email Notificationが新しいEmailへ送信される / AC-14
    public function test_the_resolver_returns_the_newly_verified_email_after_a_successful_change(): void
    {
        $person = Person::create(10);
        $this->people->save($person);
        $this->notificationEmails->save(NotificationEmail::create($person->id(), 'old@example.com', true, 'GOOGLE'));
        $this->changeRequests->save($this->usableChangeRequest($person->id(), 'new@example.com', 'resolver-token'));

        $this->useCase->execute(new VerifyNotificationEmailChangeCommand('resolver-token'));

        $this->assertSame('new@example.com', $this->resolver->resolve($person->id()));
    }

    // 22. 確認待ちのEmailには通常Notificationを送らない
    public function test_the_resolver_never_returns_a_pending_unverified_candidate_email(): void
    {
        $person = Person::create(11);
        $this->people->save($person);
        $this->notificationEmails->save(NotificationEmail::create($person->id(), 'old@example.com', true, 'GOOGLE'));
        $this->changeRequests->save($this->usableChangeRequest($person->id(), 'pending@example.com', 'not-yet-verified-token'));

        $this->assertSame(
            'old@example.com',
            $this->resolver->resolve($person->id()),
            'A pending, unverified candidate email must never become the resolved notification destination.'
        );
    }
}
