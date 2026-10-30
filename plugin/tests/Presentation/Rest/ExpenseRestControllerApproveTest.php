<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Account\CreateAccountCommand;
use StageArt\Application\Account\CreateAccountUseCase;
use StageArt\Application\Accounting\StandardPayableAccountResolver;
use StageArt\Application\Expense\ApplicantExpenseTransitionUseCase;
use StageArt\Application\Expense\ApproveExpenseUseCase;
use StageArt\Application\Expense\ConfirmExpenseUseCase;
use StageArt\Application\Expense\CreateExpenseCommand;
use StageArt\Application\Expense\CreateExpenseUseCase;
use StageArt\Application\Expense\ExpenseLineFactory;
use StageArt\Application\Expense\ExpenseTransitionCommand;
use StageArt\Application\Expense\ExpenseWorkflowNotifier;
use StageArt\Application\Expense\GetExpenseUseCase;
use StageArt\Application\Expense\ListExpensesUseCase;
use StageArt\Application\Expense\RejectExpenseUseCase;
use StageArt\Application\Expense\UpdateExpenseUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreOrganizationContextAdapter;
use StageArt\Core\Adapter\CoreProductionCapabilityDirectoryAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseStatus;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Presentation\Rest\ExpenseRestController;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryExpenseRepository;
use StageArt\Tests\Support\InMemoryJournalEntryRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\RecordingNotificationContract;
use StageArt\Tests\Support\RollbackingTransactionManager;

/**
 * PATCH /expenses/{id}/approve: revision and approved_amount must be whole
 * numbers. A JSON integer or a string of digits is accepted as before; a
 * fraction, an exponent or any other text is refused with 422 before the
 * Use Case runs, instead of being truncated to an integer. The range
 * checks stay with the Domain (amount 1 yen or more -> 422, a stale
 * revision -> 409).
 *
 * WordPress user 1 is the PrimaryManager (an approver), 2 the applicant.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ExpenseRestControllerApproveTest extends TestCase
{
    private InMemoryExpenseRepository $expenses;
    private InMemoryJournalEntryRepository $journalEntries;
    private ExpenseRestController $controller;
    private string $expenseId;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Support/wordpress-rest-stubs.php';

        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $projects = new InMemoryProjectRepository();
        $productions = new InMemoryProductionRepository();
        $accounts = new InMemoryAccountRepository();
        $participants = new InMemoryParticipantRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $this->expenses = new InMemoryExpenseRepository();
        $this->journalEntries = new InMemoryJournalEntryRepository();
        $transactions = new RollbackingTransactionManager($this->expenses, $this->journalEntries);

        $orgAuth = new OrganizationAuthorizationService($people, $memberships);
        $prodAuth = new ProductionAuthorizationService($orgAuth, $delegates, $participants);
        $productionContext = new CoreProductionContextAdapter($productions, new ProductionOrganizationResolver($projects));
        $identity = new CoreIdentityAdapter($people);
        $authorization = new CoreAuthorizationAdapter($prodAuth, $productions, $people);
        $membership = new CoreMembershipAdapter($participants, $productions, $people, $prodAuth);
        $organizationContext = new CoreOrganizationContextAdapter($organizations);
        $directory = new CoreProductionCapabilityDirectoryAdapter($prodAuth, $productions, $delegates, $people);
        $notifier = new ExpenseWorkflowNotifier(new RecordingNotificationContract($transactions), $directory, $productionContext);
        $lineFactory = new ExpenseLineFactory($accounts);

        $createExpense = new CreateExpenseUseCase($this->expenses, $productionContext, $membership, $identity, $lineFactory, $transactions);
        $applicantTransitions = new ApplicantExpenseTransitionUseCase($this->expenses, $identity, $membership, $transactions, $notifier);

        $this->controller = new ExpenseRestController(
            $createExpense,
            new UpdateExpenseUseCase($this->expenses, $productionContext, $identity, $authorization, $lineFactory, $transactions),
            new ConfirmExpenseUseCase($this->expenses, $identity, $authorization),
            new GetExpenseUseCase($this->expenses, $identity, $membership, $authorization),
            new ListExpensesUseCase($this->expenses, $productionContext, $membership, $identity, $authorization),
            $applicantTransitions,
            new ApproveExpenseUseCase(
                $this->expenses,
                $this->journalEntries,
                $accounts,
                new StandardPayableAccountResolver($accounts),
                $productionContext,
                $organizationContext,
                $identity,
                $authorization,
                $transactions,
                $notifier
            ),
            new RejectExpenseUseCase($this->expenses, $identity, $authorization, $transactions, $notifier)
        );

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organization->enableAccounting();
        $organizations->save($organization);

        $manager = Person::create(1);
        $people->save($manager);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $manager->id()));
        $project = Project::create($organization->id(), 'Spring Season');
        $projects->save($project);
        $production = Production::create($project->id(), new ProductionName('Autumn Play'), $manager->id());
        $productions->save($production);

        $applicant = Person::create(2);
        $people->save($applicant);
        $memberships->save(Membership::create($organization->id(), $applicant->id(), RoleKey::member()));
        $participants->save(Participant::create($production->id(), ParticipantSubjectType::person(), $applicant->id()->toString(), ParticipantType::cast()));

        $expenseAccountId = (new CreateAccountUseCase($accounts, $organizationContext, $identity, $authorization))->execute(
            new CreateAccountCommand(1, $organization->id()->toString(), '消耗品費', AccountType::EXPENSE)
        )->id;

        // A single-line Expense of 5,000 yen, submitted at revision 1.
        $this->expenseId = $createExpense->execute(new CreateExpenseCommand(2, $production->id()->toString(), [['account_id' => $expenseAccountId, 'amount' => 5000]], null))->id;
        $applicantTransitions->submit(new ExpenseTransitionCommand($this->expenseId, 2));

        $GLOBALS['stageart_test_wp']['current_user'] = 1;
    }

    /**
     * @param array<string, mixed> $body
     * @return \WP_REST_Response|\WP_Error
     */
    private function approve(array $body)
    {
        return $this->controller->approve(new \WP_REST_Request(['id' => $this->expenseId] + $body));
    }

    private function status(): string
    {
        return $this->expenses->findById(ExpenseId::fromString($this->expenseId))->status()->toString();
    }

    /**
     * @param \WP_REST_Response|\WP_Error $response
     */
    private function assertRefusedWith(int $status, $response): void
    {
        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertSame(['status' => $status], $response->get_error_data(), $response->get_error_message());
        $this->assertSame(ExpenseStatus::SUBMITTED, $this->status(), 'A refused approval changes nothing.');
        $this->assertSame([], $this->journalEntries->all(), 'A refused approval posts nothing.');
    }

    // --- accepted, as before ---------------------------------------------

    /**
     * @dataProvider integers
     *
     * @param mixed $revision
     * @param mixed $amount
     */
    public function test_whole_numbers_are_accepted($revision, $amount, int $approved): void
    {
        $body = ['revision' => $revision];

        if ($amount !== 'absent') {
            $body['approved_amount'] = $amount;
        }

        $response = $this->approve($body);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertSame(ExpenseStatus::POSTED, $response->get_data()['status']);
        $this->assertSame(1, $response->get_data()['revision']);
        $this->assertSame(5000, $response->get_data()['requested_amount']);
        $this->assertSame($approved, $response->get_data()['approved_amount']);
        $this->assertCount(1, $this->journalEntries->all());
    }

    /**
     * @return array<string, array{mixed, mixed, int}>
     */
    public static function integers(): array
    {
        return [
            'JSON integer revision, no amount' => [1, 'absent', 5000],
            'JSON integer revision, null amount' => [1, null, 5000],
            'JSON integer revision, empty amount' => [1, '', 5000],
            'string revision' => ['1', 'absent', 5000],
            'JSON integer amount' => [1, 2500, 2500],
            'string amount' => ['1', '2500', 2500],
            'the whole requested amount' => [1, 5000, 5000],
            'one yen' => [1, '1', 1],
        ];
    }

    // --- refused before the Use Case ---------------------------------------

    /**
     * @dataProvider notIntegers
     *
     * @param mixed $value
     */
    public function test_a_revision_that_is_not_a_whole_number_is_refused($value): void
    {
        $response = $this->approve(['revision' => $value]);

        $this->assertRefusedWith(422, $response);
        $this->assertSame('stageart_expense_invalid', $response->get_error_code());
        $this->assertSame('revision must be an integer.', $response->get_error_message());
    }

    /**
     * @dataProvider notIntegers
     *
     * @param mixed $value
     */
    public function test_an_approved_amount_that_is_not_a_whole_number_is_refused($value): void
    {
        $response = $this->approve(['revision' => 1, 'approved_amount' => $value]);

        $this->assertRefusedWith(422, $response);
        $this->assertSame('stageart_expense_invalid', $response->get_error_code());
        $this->assertSame('approved_amount must be an integer.', $response->get_error_message());
    }

    /**
     * Values is_numeric() accepted and an (int) cast would have truncated,
     * and other text.
     *
     * @return array<string, array{mixed}>
     */
    public static function notIntegers(): array
    {
        return [
            'JSON fraction' => [100.5],
            'JSON fraction 1.5' => [1.5],
            'JSON whole float' => [1.0],
            'JSON exponent (a float)' => [1e3],
            'string fraction' => ['100.5'],
            'string fraction 1.5' => ['1.5'],
            'string whole float' => ['1.0'],
            'string trailing dot' => ['1.'],
            'string leading dot' => ['.5'],
            'string exponent' => ['1e3'],
            'string capital exponent' => ['1E3'],
            'string negative exponent' => ['5000e-1'],
            'hexadecimal' => ['0x10'],
            'leading zero' => ['01'],
            'plus sign' => ['+1'],
            'leading space' => [' 1'],
            'trailing space' => ['1 '],
            'trailing newline' => ["1\n"],
            'thousands separator' => ['1,000'],
            'digits then text' => ['100abc'],
            'text' => ['abc'],
            'full-width digits' => ['１'],
            'beyond the int range' => ['99999999999999999999'],
            'boolean' => [true],
            'array' => [[1]],
        ];
    }

    public function test_a_missing_revision_is_still_required(): void
    {
        foreach ([[], ['revision' => null], ['revision' => '']] as $body) {
            $response = $this->approve($body);

            $this->assertRefusedWith(422, $response);
            $this->assertSame('revision (the submission revision being approved) is required.', $response->get_error_message());
        }
    }

    // --- the Domain's range checks are unchanged ---------------------------

    /**
     * @dataProvider outOfRange
     *
     * @param mixed $revision
     * @param mixed $amount
     */
    public function test_whole_numbers_out_of_range_are_still_refused_by_the_domain($revision, $amount, int $status): void
    {
        $this->assertRefusedWith($status, $this->approve(['revision' => $revision, 'approved_amount' => $amount]));
    }

    /**
     * @return array<string, array{mixed, mixed, int}>
     */
    public static function outOfRange(): array
    {
        return [
            'zero amount' => [1, 0, 422],
            'string zero amount' => [1, '0', 422],
            'negative amount' => [1, -1, 422],
            'string negative amount' => [1, '-100', 422],
            'stale revision' => [2, null, 409],
            'string stale revision' => ['2', null, 409],
            'revision zero' => [0, null, 409],
            'negative revision' => ['-1', null, 409],
        ];
    }
}
