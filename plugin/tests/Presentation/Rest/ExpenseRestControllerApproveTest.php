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
 * Use Case runs, instead of being truncated to an integer. The amount
 * checks stay with the Domain: since Version 1.10 the approved amount
 * must equal the requested one (any other -> 422; omitted, null or empty
 * means "as requested"); a stale revision -> 409.
 *
 * WordPress user 1 is the PrimaryManager (an approver), 2 the applicant.
 *
 * Also PATCH /expenses/{id} (Version 1.11): only the applicant edits the
 * Expense - anyone else, the PrimaryManager included, gets 403 - and a
 * POSTED Expense is not edited even by the applicant (409).
 *
 * PR #6 review N5 / N6: an id that is not a UUID, and lines or a reason that
 * are malformed, are refused with 422 on every Expense route - never a 500,
 * and never coerced (100.9 is not truncated to 100).
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
    private string $expenseAccountId;
    private string $productionId;

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
            new UpdateExpenseUseCase($this->expenses, $productionContext, $identity, $lineFactory, $transactions),
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
        $this->productionId = $production->id()->toString();

        $applicant = Person::create(2);
        $people->save($applicant);
        $memberships->save(Membership::create($organization->id(), $applicant->id(), RoleKey::member()));
        $participants->save(Participant::create($production->id(), ParticipantSubjectType::person(), $applicant->id()->toString(), ParticipantType::cast()));

        $expenseAccountId = $this->expenseAccountId = (new CreateAccountUseCase($accounts, $organizationContext, $identity, $authorization))->execute(
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
            'JSON integer amount equal to the requested' => [1, 5000, 5000],
            'string amount equal to the requested' => ['1', '5000', 5000],
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

    // --- the Domain's checks -----------------------------------------------

    /**
     * Version 1.10: a whole number other than the requested 5,000 is
     * refused (422) by the Domain, reached through the Use Case.
     *
     * @dataProvider otherAmounts
     *
     * @param mixed $amount
     */
    public function test_an_amount_other_than_the_requested_is_refused($amount): void
    {
        $response = $this->approve(['revision' => 1, 'approved_amount' => $amount]);

        $this->assertRefusedWith(422, $response);
        $this->assertSame('stageart_expense_invalid', $response->get_error_code());
        $this->assertStringContainsString('must equal the requested amount (5000)', $response->get_error_message());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function otherAmounts(): array
    {
        return [
            'one yen more' => [5001],
            'one yen less' => [4999],
            'string one yen more' => ['5001'],
            'string one yen less' => ['4999'],
            'half' => [2500],
            'one yen' => ['1'],
        ];
    }

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

    // --- edit: the applicant only (Version 1.11) ---------------------------

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    private function update(int $wordPressUserId, int $amount)
    {
        $GLOBALS['stageart_test_wp']['current_user'] = $wordPressUserId;

        return $this->controller->update(new \WP_REST_Request([
            'id' => $this->expenseId,
            'lines' => [['account_id' => $this->expenseAccountId, 'amount' => $amount]],
        ]));
    }

    public function test_the_primary_manager_cannot_edit_the_applicants_expense(): void
    {
        $before = $this->expenses->findById(ExpenseId::fromString($this->expenseId));
        $revision = $before->revision();

        $response = $this->update(1, 4800);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertSame(['status' => 403], $response->get_error_data(), $response->get_error_message());
        $after = $this->expenses->findById(ExpenseId::fromString($this->expenseId));
        $this->assertSame([ExpenseStatus::SUBMITTED, $revision, 5000], [$after->status()->toString(), $after->revision(), $after->totalAmount()]);
    }

    public function test_the_applicant_edits_their_submitted_expense_as_a_new_revision(): void
    {
        $response = $this->update(2, 4800);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertSame([ExpenseStatus::SUBMITTED, 2, 4800], [$response->get_data()['status'], $response->get_data()['revision'], $response->get_data()['requested_amount']]);
    }

    public function test_a_posted_expense_is_not_edited_even_by_the_applicant(): void
    {
        $this->assertInstanceOf(\WP_REST_Response::class, $this->approve(['revision' => 1]));

        $response = $this->update(2, 4800);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertSame(['status' => 409], $response->get_error_data(), $response->get_error_message());
        $this->assertSame(5000, $this->expenses->findById(ExpenseId::fromString($this->expenseId))->totalAmount());
    }

    // --- N5: an id that is not a UUID --------------------------------------

    /**
     * @dataProvider routesTakingAnId
     */
    public function test_an_id_that_is_not_a_uuid_is_refused_with_422(string $route, array $body): void
    {
        $GLOBALS['stageart_test_wp']['current_user'] = $route === 'approve' || $route === 'reject' ? 1 : 2;

        $response = $this->controller->{$route}(new \WP_REST_Request(['id' => 'not-a-uuid'] + $body));

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertSame('stageart_expense_invalid', $response->get_error_code());
        $this->assertSame(['status' => 422], $response->get_error_data());
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function routesTakingAnId(): array
    {
        return [
            'GET /expenses/{id}' => ['get', []],
            'PUT /expenses/{id}' => ['update', ['lines' => [['account_id' => '00000000-0000-4000-8000-000000000000', 'amount' => 1000]]]],
            'PATCH submit' => ['submit', []],
            'PATCH withdraw' => ['withdraw', []],
            'PATCH resubmit' => ['resubmit', []],
            'PATCH approve' => ['approve', ['revision' => 1]],
            'PATCH reject' => ['reject', ['reason' => '確認のため']],
        ];
    }

    // --- N6: malformed lines and reasons -------------------------------------

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    private function create(array $lines)
    {
        $GLOBALS['stageart_test_wp']['current_user'] = 2;

        return $this->controller->create(new \WP_REST_Request(['id' => $this->productionId, 'lines' => $lines]));
    }

    /**
     * @dataProvider malformedLines
     *
     * @param mixed $line
     */
    public function test_malformed_lines_are_refused_with_422_on_create_and_update($line, string $message): void
    {
        $lines = is_array($line) && array_key_exists('extra', $line)
            ? array_map(fn ($l) => $this->withAccount($l), $line['extra'])
            : [$this->withAccount($line)];
        $before = count($this->expenses->findByProductionId(\StageArt\Domain\Production\ProductionId::fromString($this->productionId)));

        $created = $this->create($lines);

        $this->assertInstanceOf(\WP_Error::class, $created);
        $this->assertSame(['status' => 422], $created->get_error_data(), $created->get_error_message());
        $this->assertStringContainsString($message, $created->get_error_message());
        $this->assertCount($before, $this->expenses->findByProductionId(\StageArt\Domain\Production\ProductionId::fromString($this->productionId)), 'Nothing was created');

        $GLOBALS['stageart_test_wp']['current_user'] = 2;
        $updated = $this->controller->update(new \WP_REST_Request(['id' => $this->expenseId, 'lines' => $lines]));

        $this->assertInstanceOf(\WP_Error::class, $updated);
        $this->assertSame(['status' => 422], $updated->get_error_data(), $updated->get_error_message());
        $after = $this->expenses->findById(ExpenseId::fromString($this->expenseId));
        $this->assertSame([1, 5000], [$after->revision(), $after->totalAmount()], 'The Expense is unchanged');
    }

    /**
     * @param mixed $line
     * @return mixed
     */
    private function withAccount($line)
    {
        if (is_array($line) && in_array($line['account_id'] ?? null, ['ACCOUNT', '__ACCOUNT__'], true)) {
            $line['account_id'] = $this->expenseAccountId;
        }

        return $line;
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function malformedLines(): array
    {
        return [
            'a line that is not an object' => ['5000', 'must be an object'],
            'a fraction' => [['account_id' => 'ACCOUNT', 'amount' => 100.9], 'whole number'],
            'a fraction as text' => [['account_id' => 'ACCOUNT', 'amount' => '100.9'], 'whole number'],
            'a whole float' => [['account_id' => 'ACCOUNT', 'amount' => 1000.0], 'whole number'],
            'an exponent' => [['account_id' => 'ACCOUNT', 'amount' => '1e3'], 'whole number'],
            'text' => [['account_id' => 'ACCOUNT', 'amount' => 'abc'], 'whole number'],
            'a boolean' => [['account_id' => 'ACCOUNT', 'amount' => true], 'whole number'],
            'beyond the integer range' => [['account_id' => 'ACCOUNT', 'amount' => '99999999999999999999'], 'whole number'],
            'zero' => [['account_id' => 'ACCOUNT', 'amount' => 0], 'positive'],
            'negative' => [['account_id' => 'ACCOUNT', 'amount' => '-5'], 'positive'],
            'an account id that is not text' => [['account_id' => ['x'], 'amount' => 1000], 'account_id must be a string'],
            'a description that is not text' => [['account_id' => 'ACCOUNT', 'amount' => 1000, 'description' => ['x']], 'description must be a string'],
            'a total beyond the integer range' => [['extra' => [
                ['account_id' => '__ACCOUNT__', 'amount' => PHP_INT_MAX],
                ['account_id' => '__ACCOUNT__', 'amount' => 1],
            ]], 'too large'],
        ];
    }

    public function test_whole_numbers_as_integers_or_digit_strings_are_still_accepted(): void
    {
        $created = $this->create([
            ['account_id' => $this->expenseAccountId, 'amount' => 1200],
            ['account_id' => $this->expenseAccountId, 'amount' => '800', 'description' => '架空の小道具'],
        ]);

        $this->assertInstanceOf(\WP_REST_Response::class, $created);
        $this->assertSame(201, $created->get_status());
        $this->assertSame(2000, $created->get_data()['total_amount']);
    }

    /**
     * @dataProvider reasonsThatAreNotText
     *
     * @param mixed $reason
     */
    public function test_a_reason_that_is_not_text_is_refused_with_422($reason): void
    {
        $GLOBALS['stageart_test_wp']['current_user'] = 1;

        $response = $this->controller->reject(new \WP_REST_Request(['id' => $this->expenseId, 'reason' => $reason]));

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertSame(['status' => 422], $response->get_error_data(), $response->get_error_message());
        $this->assertSame(ExpenseStatus::SUBMITTED, $this->status());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function reasonsThatAreNotText(): array
    {
        return [
            'an array' => [['不備']],
            'a number' => [5],
            'a boolean' => [true],
            'missing' => [null],
            'blank' => ['   '],
        ];
    }
}
