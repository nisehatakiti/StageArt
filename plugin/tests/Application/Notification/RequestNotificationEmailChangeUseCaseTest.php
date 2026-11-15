<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Notification;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\InvalidNotificationEmailException;
use StageArt\Application\Notification\NotificationAccessDeniedException;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Application\Notification\RequestNotificationEmailChangeCommand;
use StageArt\Application\Notification\RequestNotificationEmailChangeResult;
use StageArt\Application\Notification\RequestNotificationEmailChangeUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Person\Person;
use StageArt\Tests\Support\FakeAuthMailer;
use StageArt\Tests\Support\FakeWordPressUserLookup;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailChangeRequestRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

/**
 * 通知用Email確認・変更機能 §4/§9/§10/§23「変更要求」/AC-02/AC-03.
 */
final class RequestNotificationEmailChangeUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryNotificationEmailRepository $notificationEmails;
    private InMemoryNotificationEmailChangeRequestRepository $changeRequests;
    private FakeAuthMailer $mailer;
    private RequestNotificationEmailChangeUseCase $useCase;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $userAccounts = new InMemoryUserAccountRepository();
        $emailCredentials = new InMemoryEmailCredentialRepository();
        $this->notificationEmails = new InMemoryNotificationEmailRepository();
        $this->changeRequests = new InMemoryNotificationEmailChangeRequestRepository();
        $this->mailer = new FakeAuthMailer();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, new InMemoryMembershipRepository());
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );
        $resolver = new PersonEmailResolver(
            $this->people,
            $userAccounts,
            $emailCredentials,
            new FakeWordPressUserLookup(),
            $this->notificationEmails
        );

        $this->useCase = new RequestNotificationEmailChangeUseCase(
            $productionAuthorization,
            $resolver,
            $this->changeRequests,
            $this->mailer,
            new InMemoryTransactionManager()
        );
    }

    // 4. 新しいEmailを入力するとpending verificationになる
    public function test_requesting_a_new_email_creates_a_pending_change_request(): void
    {
        $person = Person::create(1);
        $this->people->save($person);

        $result = $this->useCase->execute(new RequestNotificationEmailChangeCommand(1, 'new@example.com'));

        $this->assertSame(RequestNotificationEmailChangeResult::STATUS_PENDING_VERIFICATION, $result->status);
        $pending = $this->changeRequests->findByPersonId($person->id());
        $this->assertNotNull($pending);
        $this->assertSame('new@example.com', $pending->candidateEmail());
        $this->assertTrue($pending->isUsable());
    }

    // 5. verification完了前は現在のNotificationEmailが維持される
    public function test_the_current_notification_email_is_unchanged_immediately_after_requesting(): void
    {
        $person = Person::create(2);
        $this->people->save($person);
        $this->notificationEmails->save(NotificationEmail::create($person->id(), 'notify@example.com', true, 'GOOGLE'));

        $this->useCase->execute(new RequestNotificationEmailChangeCommand(2, 'new@example.com'));

        $stillCurrent = $this->notificationEmails->findByPersonId($person->id());
        $this->assertSame('notify@example.com', $stillCurrent->email());
    }

    // 6. verification emailが送信される
    public function test_sends_a_verification_email_to_the_new_address(): void
    {
        $person = Person::create(3);
        $this->people->save($person);

        $this->useCase->execute(new RequestNotificationEmailChangeCommand(3, 'new@example.com'));

        $this->assertCount(1, $this->mailer->notificationEmailChangeVerificationEmails);
        $this->assertSame('new@example.com', $this->mailer->notificationEmailChangeVerificationEmails[0]['to']);
    }

    // 7. 不正Emailは受け付けない
    public function test_rejects_a_malformed_email(): void
    {
        $person = Person::create(4);
        $this->people->save($person);

        $this->expectException(InvalidNotificationEmailException::class);
        $this->useCase->execute(new RequestNotificationEmailChangeCommand(4, 'not-an-email'));
    }

    // 8. 現在と同じEmailを入力した場合の挙動 (§10)
    public function test_requesting_the_current_email_is_a_no_op(): void
    {
        $person = Person::create(5);
        $this->people->save($person);
        $this->notificationEmails->save(NotificationEmail::create($person->id(), 'notify@example.com', true, 'GOOGLE'));

        $result = $this->useCase->execute(new RequestNotificationEmailChangeCommand(5, 'notify@example.com'));

        $this->assertSame(RequestNotificationEmailChangeResult::STATUS_ALREADY_CURRENT, $result->status);
        $this->assertNull($this->changeRequests->findByPersonId($person->id()));
        $this->assertCount(0, $this->mailer->notificationEmailChangeVerificationEmails);
    }

    /** §9: a second request replaces the first - only the latest token stays usable. */
    public function test_a_second_request_replaces_the_first_pending_request(): void
    {
        $person = Person::create(6);
        $this->people->save($person);

        $this->useCase->execute(new RequestNotificationEmailChangeCommand(6, 'new1@example.com'));
        $firstTokenHash = $this->changeRequests->findByPersonId($person->id())->tokenHash();

        $this->useCase->execute(new RequestNotificationEmailChangeCommand(6, 'new2@example.com'));
        $current = $this->changeRequests->findByPersonId($person->id());

        $this->assertSame('new2@example.com', $current->candidateEmail());
        $this->assertNotSame($firstTokenHash, $current->tokenHash());
        $this->assertNull($this->changeRequests->findByTokenHash($firstTokenHash), 'The superseded token must no longer resolve to anything.');
    }

    public function test_throws_when_no_person_is_linked_to_the_wordpress_user(): void
    {
        $this->expectException(NotificationAccessDeniedException::class);

        $this->useCase->execute(new RequestNotificationEmailChangeCommand(999, 'new@example.com'));
    }
}
