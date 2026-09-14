<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Notification;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\GetNotificationEmailSettingsQuery;
use StageArt\Application\Notification\GetNotificationEmailSettingsUseCase;
use StageArt\Application\Notification\NotificationAccessDeniedException;
use StageArt\Application\Notification\PersonEmailResolution;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Notification\NotificationEmailChangeRequest;
use StageArt\Domain\Person\Person;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\FakeWordPressUserLookup;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailChangeRequestRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

/**
 * 通知用Email確認・変更機能 §3/§23「表示」/AC-01: the Settings screen's
 * own read model.
 */
final class GetNotificationEmailSettingsUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryEmailCredentialRepository $emailCredentials;
    private InMemoryNotificationEmailRepository $notificationEmails;
    private InMemoryNotificationEmailChangeRequestRepository $changeRequests;
    private GetNotificationEmailSettingsUseCase $useCase;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $this->notificationEmails = new InMemoryNotificationEmailRepository();
        $this->changeRequests = new InMemoryNotificationEmailChangeRequestRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, new InMemoryMembershipRepository());
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );
        $resolver = new PersonEmailResolver(
            $this->people,
            $this->userAccounts,
            $this->emailCredentials,
            new FakeWordPressUserLookup(),
            $this->notificationEmails
        );

        $this->useCase = new GetNotificationEmailSettingsUseCase($productionAuthorization, $resolver, $this->changeRequests);
    }

    // 1. verified NotificationEmailが表示される (Case A)
    public function test_shows_the_verified_notification_email_when_one_exists(): void
    {
        $person = Person::create(1);
        $this->people->save($person);
        $this->notificationEmails->save(NotificationEmail::create($person->id(), 'notify@example.com', true, 'GOOGLE'));

        $result = $this->useCase->execute(new GetNotificationEmailSettingsQuery(1));

        $this->assertSame('notify@example.com', $result->currentEmail);
        $this->assertSame(PersonEmailResolution::SOURCE_NOTIFICATION_EMAIL, $result->source);
    }

    // 2. NotificationEmailなしでEmailCredentialがある場合の表示が既存Resolverと整合する (Case B)
    public function test_shows_the_email_credential_fallback_tagged_as_not_a_saved_notification_email(): void
    {
        $person = Person::create(2);
        $this->people->save($person);
        $userAccount = UserAccount::create($person->id());
        $this->userAccounts->save($userAccount);
        $this->emailCredentials->save(EmailCredential::create($userAccount->id(), 'foo@example.com', 'hash'));

        $result = $this->useCase->execute(new GetNotificationEmailSettingsQuery(2));

        $this->assertSame('foo@example.com', $result->currentEmail);
        $this->assertSame(
            PersonEmailResolution::SOURCE_EMAIL_CREDENTIAL,
            $result->source,
            'Case B must be distinguishable from a saved NotificationEmail so the UI never misrepresents it as one.'
        );
    }

    // 3. 有効なEmailがない場合の表示 (Case D)
    public function test_shows_no_current_email_when_nothing_is_resolvable(): void
    {
        $person = Person::create(3);
        $this->people->save($person);

        $result = $this->useCase->execute(new GetNotificationEmailSettingsQuery(3));

        $this->assertNull($result->currentEmail);
        $this->assertSame(PersonEmailResolution::SOURCE_NONE, $result->source);
        $this->assertNull($result->pendingEmail);
    }

    /**
     * §8: a usable pending change request is surfaced separately from
     * currentEmail - the actual notification destination stays the old
     * email until verification completes.
     */
    public function test_shows_a_usable_pending_change_request_separately_from_the_current_email(): void
    {
        $person = Person::create(4);
        $this->people->save($person);
        $this->notificationEmails->save(NotificationEmail::create($person->id(), 'notify@example.com', true, 'GOOGLE'));
        $this->changeRequests->save(NotificationEmailChangeRequest::create(
            $person->id(),
            'new@example.com',
            hash('sha256', 'token'),
            (new DateTimeImmutable())->add(new DateInterval('PT24H'))
        ));

        $result = $this->useCase->execute(new GetNotificationEmailSettingsQuery(4));

        $this->assertSame('notify@example.com', $result->currentEmail);
        $this->assertSame('new@example.com', $result->pendingEmail);
    }

    public function test_does_not_surface_an_expired_pending_change_request(): void
    {
        $person = Person::create(5);
        $this->people->save($person);
        $this->changeRequests->save(NotificationEmailChangeRequest::create(
            $person->id(),
            'new@example.com',
            hash('sha256', 'token'),
            (new DateTimeImmutable())->sub(new DateInterval('PT1H'))
        ));

        $result = $this->useCase->execute(new GetNotificationEmailSettingsQuery(5));

        $this->assertNull($result->pendingEmail);
    }

    public function test_throws_when_no_person_is_linked_to_the_wordpress_user(): void
    {
        $this->expectException(NotificationAccessDeniedException::class);

        $this->useCase->execute(new GetNotificationEmailSettingsQuery(999));
    }
}
