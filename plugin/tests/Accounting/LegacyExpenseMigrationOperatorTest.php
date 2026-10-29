<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigrationOperator as O;
use StageArt\Presentation\Admin\AccountManagementAdminPage;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;

/**
 * Version 1.9: who may run the legacy Expense migration and its revert -
 * a WordPress user that exists, holds stageart_manage_accounts and is
 * linked to exactly one StageArt Person with a valid id. Anything less is
 * a refusal code, never an operator with a stand-in id.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LegacyExpenseMigrationOperatorTest extends TestCase
{
    private const PERSON = '44444444-4444-4444-8444-444444444444';

    private FakeExpenseMigrationDatabase $db;

    /** @var \StageArt\Tests\Support\FakeWpdb */
    private $wpdb;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../Support/wordpress-stubs.php';
        $this->db = new FakeExpenseMigrationDatabase();
        $this->wpdb = new \wpdb($this->db);
    }

    /**
     * @param string[] $people
     */
    private function linkedTo(array $people, int $wpUserId = 7): void
    {
        foreach ($people as $person) {
            $this->db->tables['stageart_people'][] = ['id' => $person, 'wp_user_id' => (string) $wpUserId];
        }
    }

    public function test_the_capability_is_the_system_administrators(): void
    {
        $this->assertSame(AccountManagementAdminPage::CAPABILITY, O::CAPABILITY);
    }

    public function test_a_qualifying_user_becomes_the_operator(): void
    {
        $this->linkedTo([self::PERSON]);

        $operator = O::resolve($this->wpdb, 7, true, true, 'deploy');

        $this->assertInstanceOf(O::class, $operator);
        $this->assertSame([7, self::PERSON, 'deploy'], [$operator->wpUserId(), $operator->personId(), $operator->osUser()]);
        $this->assertSame(' person=' . self::PERSON . ' wp_user=7 os_user=deploy', $operator->describe());
    }

    /**
     * Required tests 1-3.
     *
     * @dataProvider unqualified
     *
     * @param string[] $people
     */
    public function test_anything_less_is_a_refusal_code(int $wpUserId, bool $exists, bool $capable, array $people, string $code): void
    {
        $this->linkedTo($people);

        $this->assertSame($code, O::resolve($this->wpdb, $wpUserId, $exists, $capable, 'deploy'));
    }

    /**
     * @return array<string, array{int, bool, bool, string[], string}>
     */
    public static function unqualified(): array
    {
        return [
            'no user (WP-CLI without --user)' => [0, false, false, [self::PERSON], O::REFUSED_NO_USER],
            'a negative id' => [-1, true, true, [self::PERSON], O::REFUSED_NO_USER],
            'an unknown user' => [7, false, true, [self::PERSON], O::REFUSED_UNKNOWN_USER],
            'no capability' => [7, true, false, [self::PERSON], O::REFUSED_NOT_PERMITTED],
            'no capability, even when linked to several Persons' => [7, true, false, [self::PERSON, '55555555-5555-4555-8555-555555555555'], O::REFUSED_NOT_PERMITTED],
            'no Person' => [7, true, true, [], O::REFUSED_NO_PERSON],
            'two Persons' => [7, true, true, [self::PERSON, '55555555-5555-4555-8555-555555555555'], O::REFUSED_AMBIGUOUS_PERSON],
            'a Person id that is not a UUID' => [7, true, true, ['root'], O::REFUSED_INVALID_PERSON],
        ];
    }

    public function test_a_person_of_another_user_does_not_count(): void
    {
        $this->linkedTo([self::PERSON], 8);

        $this->assertSame(O::REFUSED_NO_PERSON, O::resolve($this->wpdb, 7, true, true, null));
    }

    /**
     * The OS account is optional and kept to identifier characters.
     *
     * @dataProvider osUsers
     */
    public function test_the_os_account_is_kept_to_identifier_characters(?string $given, ?string $kept): void
    {
        $this->linkedTo([self::PERSON]);

        $operator = O::resolve($this->wpdb, 7, true, true, $given);

        $this->assertInstanceOf(O::class, $operator);
        $this->assertSame($kept, $operator->osUser());
    }

    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function osUsers(): array
    {
        return [
            'unknown' => [null, null],
            'empty' => ['', null],
            'from the environment' => ['env:alice', 'env:alice'],
            'odd characters dropped' => ["ali ce\n;rm", 'alicerm'],
            'nothing usable' => [' ;', null],
            'at most 64' => [str_repeat('a', 80), str_repeat('a', 64)],
        ];
    }
}
