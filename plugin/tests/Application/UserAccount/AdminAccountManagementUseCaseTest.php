<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\UserAccount;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Authentication\AuthenticateWithEmailCommand;
use StageArt\Application\Authentication\AuthenticateWithEmailUseCase;
use StageArt\Application\Authentication\AuthenticateWithGoogleCommand;
use StageArt\Application\Authentication\AuthenticateWithGoogleUseCase;
use StageArt\Application\Authentication\RegisterWithEmailCommand;
use StageArt\Application\Authentication\RegisterWithEmailUseCase;
use StageArt\Application\Authentication\UserAccountBlockedException;
use StageArt\Application\UserAccount\AdminAccountResult;
use StageArt\Application\UserAccount\BlockUserAccountsCommand;
use StageArt\Application\UserAccount\BlockUserAccountsUseCase;
use StageArt\Application\UserAccount\DeleteUserAccountsCommand;
use StageArt\Application\UserAccount\DeleteUserAccountsUseCase;
use StageArt\Application\UserAccount\ListAllUserAccountsUseCase;
use StageArt\Domain\UserAccount\UserAccountId;
use StageArt\Tests\Support\FakeAccessTokenIssuer;
use StageArt\Tests\Support\FakeAuthMailer;
use StageArt\Tests\Support\FakeGoogleIdTokenVerifier;
use StageArt\Tests\Support\FakeWordPressUserLookup;
use StageArt\Tests\Support\FakeWordPressUserProvisioner;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryEmailVerificationTokenRepository;
use StageArt\Tests\Support\InMemoryExternalIdentityRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryRefreshTokenRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

/**
 * StageArt Admin Console V1: covers the Account Management screen's own
 * Use Cases (list/block/delete) end-to-end against the same
 * Register/Authenticate Use Cases real accounts go through - not just
 * against hand-built Domain fixtures - so a regression in how these
 * interact with real Email/Google accounts would actually be caught
 * here.
 */
final class AdminAccountManagementUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryEmailCredentialRepository $emailCredentials;
    private FakeWordPressUserLookup $wordPressUsers;
    private FakeGoogleIdTokenVerifier $googleVerifier;

    private RegisterWithEmailUseCase $registerWithEmail;
    private AuthenticateWithEmailUseCase $authenticateWithEmail;
    private AuthenticateWithGoogleUseCase $authenticateWithGoogle;
    private ListAllUserAccountsUseCase $listAllUserAccounts;
    private BlockUserAccountsUseCase $blockUserAccounts;
    private DeleteUserAccountsUseCase $deleteUserAccounts;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $externalIdentities = new InMemoryExternalIdentityRepository();
        $this->wordPressUsers = new FakeWordPressUserLookup();
        $this->googleVerifier = new FakeGoogleIdTokenVerifier();

        $refreshTokens = new InMemoryRefreshTokenRepository();
        $accessTokenIssuer = new FakeAccessTokenIssuer();
        $wordPressUserProvisioner = new FakeWordPressUserProvisioner();
        $transactions = new InMemoryTransactionManager();

        $this->registerWithEmail = new RegisterWithEmailUseCase(
            $this->emailCredentials,
            $this->people,
            $this->userAccounts,
            $refreshTokens,
            new InMemoryEmailVerificationTokenRepository(),
            $accessTokenIssuer,
            $wordPressUserProvisioner,
            $transactions,
            new FakeAuthMailer()
        );
        $this->authenticateWithEmail = new AuthenticateWithEmailUseCase(
            $this->emailCredentials,
            $this->userAccounts,
            $this->people,
            $refreshTokens,
            $accessTokenIssuer,
            $transactions
        );
        $this->authenticateWithGoogle = new AuthenticateWithGoogleUseCase(
            $this->googleVerifier,
            $externalIdentities,
            $this->userAccounts,
            $this->people,
            $refreshTokens,
            $accessTokenIssuer,
            $wordPressUserProvisioner,
            $transactions
        );
        $this->listAllUserAccounts = new ListAllUserAccountsUseCase(
            $this->userAccounts,
            $this->people,
            $this->emailCredentials,
            $this->wordPressUsers
        );
        $this->blockUserAccounts = new BlockUserAccountsUseCase($this->userAccounts, $transactions);
        $this->deleteUserAccounts = new DeleteUserAccountsUseCase($this->userAccounts, $transactions);
    }

    public function test_lists_an_email_account_using_its_emailcredential_address(): void
    {
        $registered = $this->registerWithEmail->execute(new RegisterWithEmailCommand('jane@example.com', 'password123'));

        $results = $this->listAllUserAccounts->execute();

        $this->assertCount(1, $results);
        $this->assertSame($registered->userAccountId, $results[0]->userAccountId);
        $this->assertSame('jane@example.com', $results[0]->email);
        $this->assertSame('ACTIVE', $results[0]->status);
        $this->assertTrue($results[0]->hasEmailCredential);
    }

    public function test_lists_a_google_only_account_using_its_wordpress_user_email_and_flags_no_credential(): void
    {
        $this->googleVerifier->registerValidToken('valid-token', 'google-sub-list', 'kenji@example.com');
        $auth = $this->authenticateWithGoogle->execute(new AuthenticateWithGoogleCommand('valid-token'));

        $person = $this->people->findById(
            \StageArt\Domain\Person\PersonId::fromString($auth->personId)
        );
        $this->wordPressUsers->register($person->wordPressUserId(), 'kenji@users.stageart.invalid', 'Kenji');

        $results = $this->listAllUserAccounts->execute();

        $this->assertCount(1, $results);
        $this->assertSame($auth->userAccountId, $results[0]->userAccountId);
        $this->assertFalse($results[0]->hasEmailCredential);
        $this->assertSame('kenji@users.stageart.invalid', $results[0]->email);
        $this->assertSame('Kenji', $results[0]->name);
    }

    public function test_blocking_a_single_account_suspends_it_and_prevents_login(): void
    {
        $registered = $this->registerWithEmail->execute(new RegisterWithEmailCommand('kay@example.com', 'password123'));

        $this->blockUserAccounts->execute(new BlockUserAccountsCommand([$registered->userAccountId]));

        $this->assertSame('SUSPENDED', $this->listAllUserAccounts->execute()[0]->status);

        $this->expectException(UserAccountBlockedException::class);
        $this->authenticateWithEmail->execute(new AuthenticateWithEmailCommand('kay@example.com', 'password123'));
    }

    public function test_blocking_multiple_accounts_at_once_suspends_all_of_them(): void
    {
        $first = $this->registerWithEmail->execute(new RegisterWithEmailCommand('leo@example.com', 'password123'));
        $second = $this->registerWithEmail->execute(new RegisterWithEmailCommand('mia@example.com', 'password123'));

        $this->blockUserAccounts->execute(new BlockUserAccountsCommand([$first->userAccountId, $second->userAccountId]));

        $statuses = array_map(
            static fn (AdminAccountResult $result): string => $result->status,
            $this->listAllUserAccounts->execute()
        );
        $this->assertSame(['SUSPENDED', 'SUSPENDED'], $statuses);
    }

    public function test_deleting_an_account_disables_it_but_preserves_the_person(): void
    {
        $registered = $this->registerWithEmail->execute(new RegisterWithEmailCommand('noah@example.com', 'password123'));

        $this->deleteUserAccounts->execute(new DeleteUserAccountsCommand([$registered->userAccountId]));

        $userAccount = $this->userAccounts->findById(UserAccountId::fromString($registered->userAccountId));
        $this->assertSame('DISABLED', $userAccount->status()->toString());
        // UserAccount.md: "Personおよび過去のBusiness Dataは削除しない" -
        // the Person row must still exist, completely untouched.
        $this->assertNotNull($this->people->findById($userAccount->personId()));

        $this->expectException(UserAccountBlockedException::class);
        $this->authenticateWithEmail->execute(new AuthenticateWithEmailCommand('noah@example.com', 'password123'));
    }

    /**
     * The Account Management screen's own explicit expectation: a
     * deleted account must disappear from the default listing - the
     * same "disappears from the active listing" behavior
     * DeleteOrganizationUseCase's own docblock already establishes for
     * archived Organizations.
     */
    public function test_a_deleted_account_no_longer_appears_in_the_default_listing(): void
    {
        $kept = $this->registerWithEmail->execute(new RegisterWithEmailCommand('paula@example.com', 'password123'));
        $deleted = $this->registerWithEmail->execute(new RegisterWithEmailCommand('quinn@example.com', 'password123'));

        $this->deleteUserAccounts->execute(new DeleteUserAccountsCommand([$deleted->userAccountId]));

        $remainingIds = array_map(
            static fn (AdminAccountResult $result): string => $result->userAccountId,
            $this->listAllUserAccounts->execute()
        );
        $this->assertSame([$kept->userAccountId], $remainingIds);
    }

    public function test_bulk_action_with_an_unknown_id_skips_it_without_error(): void
    {
        $registered = $this->registerWithEmail->execute(new RegisterWithEmailCommand('olivia@example.com', 'password123'));
        $unknownId = \StageArt\Domain\Shared\Uuid::generate();

        $this->blockUserAccounts->execute(new BlockUserAccountsCommand([$registered->userAccountId, $unknownId]));

        $this->assertSame('SUSPENDED', $this->listAllUserAccounts->execute()[0]->status);
    }
}
