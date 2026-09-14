<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Notification;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Person\Person;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\FakeWordPressUserLookup;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class PersonEmailResolverTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryEmailCredentialRepository $emailCredentials;
    private FakeWordPressUserLookup $wordPressUsers;
    private InMemoryNotificationEmailRepository $notificationEmails;
    private PersonEmailResolver $resolver;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $this->wordPressUsers = new FakeWordPressUserLookup();
        $this->notificationEmails = new InMemoryNotificationEmailRepository();
        $this->resolver = new PersonEmailResolver(
            $this->people,
            $this->userAccounts,
            $this->emailCredentials,
            $this->wordPressUsers,
            $this->notificationEmails
        );
    }

    public function test_prefers_the_email_credential_when_one_exists(): void
    {
        $person = Person::create(1);
        $this->people->save($person);
        $userAccount = UserAccount::create($person->id());
        $this->userAccounts->save($userAccount);
        $this->emailCredentials->save(EmailCredential::create($userAccount->id(), 'real@example.com', 'hash'));
        // A WordPress user_email is also present but must be ignored in favor of the EmailCredential.
        $this->wordPressUsers->register(1, 'wp-fallback@example.com', 'Someone');

        $email = $this->resolver->resolve($person->id());

        $this->assertSame('real@example.com', $email);
    }

    public function test_falls_back_to_the_wordpress_user_email_when_no_credential_exists(): void
    {
        $person = Person::create(1);
        $this->people->save($person);
        $this->userAccounts->save(UserAccount::create($person->id()));
        $this->wordPressUsers->register(1, 'wp-real@example.com', 'Someone');

        $email = $this->resolver->resolve($person->id());

        $this->assertSame('wp-real@example.com', $email);
    }

    public function test_skips_the_synthetic_google_provisioned_placeholder_email(): void
    {
        $person = Person::create(1);
        $this->people->save($person);
        $this->userAccounts->save(UserAccount::create($person->id()));
        $this->wordPressUsers->register(1, 'abc123@users.stageart.invalid', 'Google User');

        $email = $this->resolver->resolve($person->id());

        $this->assertNull($email, 'A .invalid placeholder email must never be treated as deliverable.');
    }

    public function test_returns_null_when_no_person_account_or_wordpress_user_exists(): void
    {
        $person = Person::create(1);
        $this->people->save($person);

        $email = $this->resolver->resolve($person->id());

        $this->assertNull($email);
    }

    public function test_returns_null_for_an_unknown_person(): void
    {
        $email = $this->resolver->resolve(\StageArt\Domain\Person\PersonId::generate());

        $this->assertNull($email);
    }

    // --- Google認証ユーザーのEmail通知先対応: NotificationEmail priority ---

    public function test_prefers_a_verified_notification_email_over_the_email_credential(): void
    {
        $person = Person::create(1);
        $this->people->save($person);
        $userAccount = UserAccount::create($person->id());
        $this->userAccounts->save($userAccount);
        $this->emailCredentials->save(EmailCredential::create($userAccount->id(), 'password-login@example.com', 'hash'));
        $this->notificationEmails->save(
            NotificationEmail::create($person->id(), 'notification@example.com', true, NotificationEmail::SOURCE_GOOGLE)
        );

        $email = $this->resolver->resolve($person->id());

        $this->assertSame('notification@example.com', $email);
    }

    public function test_prefers_a_verified_notification_email_over_the_wordpress_user_email(): void
    {
        $person = Person::create(1);
        $this->people->save($person);
        $this->wordPressUsers->register(1, 'wp-fallback@example.com', 'Someone');
        $this->notificationEmails->save(
            NotificationEmail::create($person->id(), 'notification@example.com', true, NotificationEmail::SOURCE_GOOGLE)
        );

        $email = $this->resolver->resolve($person->id());

        $this->assertSame('notification@example.com', $email);
    }

    public function test_falls_back_to_the_email_credential_when_the_notification_email_is_unverified(): void
    {
        $person = Person::create(1);
        $this->people->save($person);
        $userAccount = UserAccount::create($person->id());
        $this->userAccounts->save($userAccount);
        $this->emailCredentials->save(EmailCredential::create($userAccount->id(), 'password-login@example.com', 'hash'));
        $this->notificationEmails->save(
            NotificationEmail::create($person->id(), 'unverified@example.com', false, NotificationEmail::SOURCE_GOOGLE)
        );

        $email = $this->resolver->resolve($person->id());

        $this->assertSame(
            'password-login@example.com',
            $email,
            'An unverified NotificationEmail must never be used as a notification destination.'
        );
    }

    public function test_falls_back_to_the_email_credential_when_no_notification_email_row_exists(): void
    {
        $person = Person::create(1);
        $this->people->save($person);
        $userAccount = UserAccount::create($person->id());
        $this->userAccounts->save($userAccount);
        $this->emailCredentials->save(EmailCredential::create($userAccount->id(), 'password-login@example.com', 'hash'));

        $email = $this->resolver->resolve($person->id());

        $this->assertSame('password-login@example.com', $email);
    }

    // --- 通知用Email確認・変更機能 §3: resolveWithSource() ---

    public function test_resolve_with_source_tags_a_saved_notification_email_correctly(): void
    {
        $person = Person::create(1);
        $this->people->save($person);
        $this->notificationEmails->save(
            NotificationEmail::create($person->id(), 'notify@example.com', true, NotificationEmail::SOURCE_GOOGLE)
        );

        $resolution = $this->resolver->resolveWithSource($person->id());

        $this->assertSame('notify@example.com', $resolution->email);
        $this->assertSame(\StageArt\Application\Notification\PersonEmailResolution::SOURCE_NOTIFICATION_EMAIL, $resolution->source);
    }

    public function test_resolve_with_source_tags_an_email_credential_fallback_correctly(): void
    {
        $person = Person::create(1);
        $this->people->save($person);
        $userAccount = UserAccount::create($person->id());
        $this->userAccounts->save($userAccount);
        $this->emailCredentials->save(EmailCredential::create($userAccount->id(), 'foo@example.com', 'hash'));

        $resolution = $this->resolver->resolveWithSource($person->id());

        $this->assertSame('foo@example.com', $resolution->email);
        $this->assertSame(\StageArt\Application\Notification\PersonEmailResolution::SOURCE_EMAIL_CREDENTIAL, $resolution->source);
    }

    public function test_resolve_with_source_tags_no_deliverable_email_correctly(): void
    {
        $person = Person::create(1);
        $this->people->save($person);

        $resolution = $this->resolver->resolveWithSource($person->id());

        $this->assertNull($resolution->email);
        $this->assertSame(\StageArt\Application\Notification\PersonEmailResolution::SOURCE_NONE, $resolution->source);
    }
}
