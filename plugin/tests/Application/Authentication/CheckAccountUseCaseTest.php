<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Authentication;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Authentication\AccountCheckCounterInterface;
use StageArt\Application\Authentication\AccountCheckRateLimit;
use StageArt\Application\Authentication\AccountCheckRateLimitedException;
use StageArt\Application\Authentication\CheckAccountCommand;
use StageArt\Application\Authentication\CheckAccountResult;
use StageArt\Application\Authentication\CheckAccountUseCase;
use StageArt\Application\Authentication\InvalidAccountCheckEmailException;
use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Person\Person;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\ExternalIdentity;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryExternalIdentityRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

/**
 * POST /auth/account/check: the sign-in path per account state, the format
 * check, the rate limit and what the answer never contains.
 */
final class CheckAccountUseCaseTest extends TestCase
{
    private InMemoryEmailCredentialRepository $credentials;
    private InMemoryUserAccountRepository $accounts;
    private InMemoryExternalIdentityRepository $identities;
    private InMemoryNotificationEmailRepository $notificationEmails;
    private CountingAccountCheckCounter $counter;
    private DateTimeImmutable $now;
    private int $nextWordPressUserId = 10;

    protected function setUp(): void
    {
        $this->credentials = new InMemoryEmailCredentialRepository();
        $this->accounts = new InMemoryUserAccountRepository();
        $this->identities = new InMemoryExternalIdentityRepository();
        $this->notificationEmails = new InMemoryNotificationEmailRepository();
        $this->counter = new CountingAccountCheckCounter();
        $this->now = new DateTimeImmutable('2026-11-25T06:03:20', new DateTimeZone('UTC'));
    }

    public function test_a_verified_password_account_goes_to_the_password_step(): void
    {
        $this->givenPasswordAccount('pw@example.com', true);

        $this->assertSame(['status' => 'PASSWORD_LOGIN', 'methods' => ['password']], $this->check('pw@example.com')->toArray());
    }

    public function test_an_unverified_password_account_needs_its_address_verified(): void
    {
        $this->givenPasswordAccount('new@example.com', false);

        $this->assertSame(['status' => 'EMAIL_VERIFICATION_REQUIRED', 'methods' => []], $this->check('new@example.com')->toArray());
    }

    public function test_an_unverified_password_account_with_google_linked_also_offers_google(): void
    {
        $account = $this->givenPasswordAccount('both-unverified@example.com', false);
        $this->identities->save(ExternalIdentity::create($account->id(), 'google', 'sub-1'));

        $this->assertSame(['status' => 'EMAIL_VERIFICATION_REQUIRED', 'methods' => ['google']], $this->check('both-unverified@example.com')->toArray());
    }

    public function test_a_google_only_account_is_not_treated_as_unregistered(): void
    {
        $this->givenGoogleOnlyAccount('gmail-user@gmail.com', true);

        $this->assertSame(['status' => 'GOOGLE_LOGIN', 'methods' => ['google']], $this->check('gmail-user@gmail.com')->toArray());
    }

    public function test_an_account_with_both_methods_shows_both(): void
    {
        $account = $this->givenPasswordAccount('both@example.com', true);
        $this->identities->save(ExternalIdentity::create($account->id(), 'google', 'sub-2'));

        $this->assertSame(['status' => 'MULTIPLE_AUTH_METHODS', 'methods' => ['password', 'google']], $this->check('both@example.com')->toArray());
    }

    public function test_an_unknown_address_is_not_found(): void
    {
        $this->assertSame(['status' => 'NOT_FOUND', 'methods' => []], $this->check('nobody@example.com')->toArray());
    }

    public function test_a_gmail_address_alone_is_never_assumed_to_be_a_google_account(): void
    {
        // A Google identity whose notification address is NOT verified, and
        // a verified notification address without any Google identity.
        $this->givenGoogleOnlyAccount('unverified-google@gmail.com', false);
        $person = Person::create($this->nextWordPressUserId++);
        $this->accounts->save(UserAccount::create($person->id()));
        $this->notificationEmails->save(NotificationEmail::create($person->id(), 'no-google@gmail.com', true, NotificationEmail::SOURCE_USER));

        $this->assertSame('NOT_FOUND', $this->check('unverified-google@gmail.com')->status);
        $this->assertSame('NOT_FOUND', $this->check('no-google@gmail.com')->status);
    }

    /**
     * @dataProvider blockedStates
     */
    public function test_a_blocked_account_is_disabled_not_sent_to_login_or_registration(string $state): void
    {
        $account = $this->givenPasswordAccount('blocked@example.com', true);
        $state === 'SUSPENDED' ? $account->suspend() : $account->disable();
        $this->accounts->save($account);

        $google = $this->givenGoogleOnlyAccount('blocked-google@gmail.com', true);
        $state === 'SUSPENDED' ? $google->suspend() : $google->disable();
        $this->accounts->save($google);

        $this->assertSame(['status' => 'DISABLED', 'methods' => []], $this->check('blocked@example.com')->toArray());
        $this->assertSame(['status' => 'DISABLED', 'methods' => []], $this->check('blocked-google@gmail.com')->toArray());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedStates(): array
    {
        return ['SUSPENDED' => ['SUSPENDED'], 'DISABLED' => ['DISABLED']];
    }

    public function test_the_address_is_trimmed_before_the_lookup(): void
    {
        $this->givenPasswordAccount('pw@example.com', true);

        $this->assertSame('PASSWORD_LOGIN', $this->check("  pw@example.com \n")->status);
    }

    /**
     * @dataProvider invalidAddresses
     */
    public function test_an_invalid_address_is_refused_but_still_counted(string $email): void
    {
        try {
            $this->check($email);
            $this->fail('An invalid address must be refused.');
        } catch (InvalidAccountCheckEmailException $exception) {
            $this->assertSame(1, $this->counter->hits('client'));
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAddresses(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['   '],
            'no at sign' => ['not-an-address'],
            'two at signs' => ['a@@example.com'],
            'too long' => [str_repeat('a', 250) . '@example.com'],
        ];
    }

    public function test_the_answer_never_contains_ids_addresses_or_credentials(): void
    {
        $account = $this->givenPasswordAccount('pw@example.com', true);
        $this->identities->save(ExternalIdentity::create($account->id(), 'google', 'google-sub-secret'));

        $body = json_encode($this->check('pw@example.com')->toArray());

        $this->assertSame(['methods', 'status'], $this->sortedKeys($this->check('pw@example.com')->toArray()));
        $this->assertStringNotContainsString('pw@example.com', $body);
        $this->assertStringNotContainsString($account->id()->toString(), $body);
        $this->assertStringNotContainsString($account->personId()->toString(), $body);
        $this->assertStringNotContainsString('google-sub-secret', $body);
        $this->assertStringNotContainsString('hash', $body);
    }

    public function test_more_than_20_checks_from_one_connection_in_a_window_are_refused(): void
    {
        for ($i = 1; $i <= AccountCheckRateLimit::LIMIT_PER_CLIENT; $i++) {
            $this->check("user{$i}@example.com");
        }

        try {
            $this->check('one-more@example.com');
            $this->fail('The 21st check from one connection must be refused.');
        } catch (AccountCheckRateLimitedException $exception) {
            // 06:03:20 -> the window ends at 06:10:00.
            $this->assertSame(400, $exception->retryAfter());
        }

        // Another connection is not affected.
        $this->assertSame('NOT_FOUND', $this->check('one-more@example.com', '198.51.100.7')->status);
    }

    public function test_more_than_10_checks_of_one_address_in_a_window_are_refused_whatever_the_connection(): void
    {
        for ($i = 1; $i <= AccountCheckRateLimit::LIMIT_PER_EMAIL; $i++) {
            $this->check('target@example.com', "203.0.113.{$i}");
        }

        $this->expectException(AccountCheckRateLimitedException::class);
        // Upper case / spaces count as the same address.
        $this->check(' TARGET@example.com ', '203.0.113.99');
    }

    public function test_a_connection_over_its_limit_does_not_use_up_the_address_budget(): void
    {
        for ($i = 1; $i <= AccountCheckRateLimit::LIMIT_PER_CLIENT; $i++) {
            $this->check("user{$i}@example.com");
        }

        $emailHitsBefore = $this->counter->hits('email');

        for ($i = 0; $i < 5; $i++) {
            try {
                $this->check('victim@example.com');
            } catch (AccountCheckRateLimitedException $exception) {
            }
        }

        $this->assertSame($emailHitsBefore, $this->counter->hits('email'), 'refused connection attempts are not counted against the address');
        $this->assertSame('NOT_FOUND', $this->check('victim@example.com', '198.51.100.8')->status);
    }

    public function test_a_new_window_starts_counting_again(): void
    {
        for ($i = 1; $i <= AccountCheckRateLimit::LIMIT_PER_CLIENT; $i++) {
            $this->check("user{$i}@example.com");
        }

        $this->now = $this->now->modify('+400 seconds');

        $this->assertSame('NOT_FOUND', $this->check('next-window@example.com')->status);
    }

    public function test_the_counter_keys_carry_neither_the_address_nor_the_ip(): void
    {
        $this->check('private@example.com', '192.0.2.44');

        foreach ($this->counter->buckets() as $bucket) {
            $this->assertStringNotContainsString('private', $bucket);
            $this->assertStringNotContainsString('192.0.2.44', $bucket);
        }
        $this->assertCount(2, $this->counter->buckets());
    }

    private function check(string $email, string $clientAddress = '192.0.2.1'): CheckAccountResult
    {
        $useCase = new CheckAccountUseCase(
            $this->credentials,
            $this->accounts,
            $this->identities,
            $this->notificationEmails,
            new AccountCheckRateLimit($this->counter, 'test-secret', fn (): DateTimeImmutable => $this->now)
        );

        return $useCase->execute(new CheckAccountCommand($email, $clientAddress));
    }

    private function givenPasswordAccount(string $email, bool $verified): UserAccount
    {
        $person = Person::create($this->nextWordPressUserId++);
        $account = UserAccount::create($person->id());
        $this->accounts->save($account);
        $credential = EmailCredential::create($account->id(), $email, password_hash('secret-password', PASSWORD_DEFAULT));

        if ($verified) {
            $credential->markEmailVerified();
        }

        $this->credentials->save($credential);

        return $account;
    }

    private function givenGoogleOnlyAccount(string $googleEmail, bool $googleVerified): UserAccount
    {
        $person = Person::create($this->nextWordPressUserId++);
        $account = UserAccount::create($person->id());
        $this->accounts->save($account);
        $this->identities->save(ExternalIdentity::create($account->id(), 'google', 'sub-' . $googleEmail));
        $this->notificationEmails->save(NotificationEmail::create($person->id(), $googleEmail, $googleVerified, NotificationEmail::SOURCE_GOOGLE));

        return $account;
    }

    /**
     * @param array<string, mixed> $array
     * @return string[]
     */
    private function sortedKeys(array $array): array
    {
        $keys = array_keys($array);
        sort($keys);

        return $keys;
    }
}

/** An in-memory fixed-window counter that records the buckets it was asked for. */
final class CountingAccountCheckCounter implements AccountCheckCounterInterface
{
    /** @var array<string, int> */
    private array $counts = [];

    public function hit(string $bucket, DateTimeImmutable $windowStart, int $windowSeconds): int
    {
        $key = $bucket . '@' . $windowStart->getTimestamp();
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;

        return $this->counts[$key];
    }

    /** Total hits of one kind (`client` / `email`). */
    public function hits(string $kind): int
    {
        $total = 0;

        foreach ($this->counts as $key => $count) {
            if (strpos($key, 'acct_check_' . $kind . '_') === 0) {
                $total += $count;
            }
        }

        return $total;
    }

    /** @return string[] */
    public function buckets(): array
    {
        return array_keys($this->counts);
    }
}
