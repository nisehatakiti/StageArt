<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\UserAccount;

use PHPUnit\Framework\TestCase;
use StageArt\Application\UserAccount\EmailCredentialNotFoundException;
use StageArt\Application\UserAccount\RegisterEmailCredentialCommand;
use StageArt\Application\UserAccount\RegisterEmailCredentialUseCase;
use StageArt\Application\UserAccount\RequestEmailVerificationCommand;
use StageArt\Application\UserAccount\RequestEmailVerificationUseCase;
use StageArt\Application\UserAccount\UserAccountNotFoundException;
use StageArt\Domain\Person\Person;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\FakeAuthMailer;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryEmailVerificationTokenRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class RequestEmailVerificationUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryEmailCredentialRepository $emailCredentials;
    private InMemoryEmailVerificationTokenRepository $emailVerificationTokens;
    private FakeAuthMailer $mailer;
    private RequestEmailVerificationUseCase $requestEmailVerification;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $this->emailVerificationTokens = new InMemoryEmailVerificationTokenRepository();
        $this->mailer = new FakeAuthMailer();
        $this->requestEmailVerification = new RequestEmailVerificationUseCase(
            $this->people,
            $this->userAccounts,
            $this->emailCredentials,
            $this->emailVerificationTokens,
            $this->mailer
        );

        $registerEmailCredential = new RegisterEmailCredentialUseCase(
            $this->people,
            $this->userAccounts,
            $this->emailCredentials,
            new InMemoryTransactionManager()
        );

        $person = Person::create(1);
        $this->people->save($person);
        $this->userAccounts->save(UserAccount::create($person->id()));
        $registerEmailCredential->execute(new RegisterEmailCredentialCommand(1, 'verify-me@example.com', 'password123'));
    }

    public function test_requesting_verification_sends_an_email_with_a_token(): void
    {
        $this->requestEmailVerification->execute(new RequestEmailVerificationCommand(1));

        $this->assertCount(1, $this->mailer->verificationEmails);
        $this->assertSame('verify-me@example.com', $this->mailer->verificationEmails[0]['to']);
    }

    public function test_requesting_verification_when_already_verified_is_a_silent_no_op(): void
    {
        $person = $this->people->findByWordPressUserId(1);
        $userAccount = $this->userAccounts->findByPersonId($person->id());
        $credential = $this->emailCredentials->findByUserAccountId($userAccount->id());
        $credential->markEmailVerified();
        $this->emailCredentials->save($credential);

        $this->requestEmailVerification->execute(new RequestEmailVerificationCommand(1));

        $this->assertCount(0, $this->mailer->verificationEmails);
    }

    public function test_requesting_verification_without_an_existing_useraccount_is_rejected(): void
    {
        $this->expectException(UserAccountNotFoundException::class);
        $this->requestEmailVerification->execute(new RequestEmailVerificationCommand(999));
    }

    public function test_requesting_verification_without_an_existing_emailcredential_is_rejected(): void
    {
        $person = Person::create(2);
        $this->people->save($person);
        $this->userAccounts->save(UserAccount::create($person->id()));

        $this->expectException(EmailCredentialNotFoundException::class);
        $this->requestEmailVerification->execute(new RequestEmailVerificationCommand(2));
    }
}
