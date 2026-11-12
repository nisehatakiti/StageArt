<?php

declare(strict_types=1);

namespace StageArt\Accounting;

use StageArt\Domain\Shared\Uuid;

/**
 * The operator running the legacy Expense migration or its revert
 * (ExpenseApprovalWorkflow.md Version 1.9): a WordPress user that exists,
 * holds the System Administrator capability `stageart_manage_accounts`,
 * and is linked to exactly one StageArt Person. Only resolve() creates
 * one, so LegacyExpenseMigration::execute() and
 * LegacyExpenseMigrationRestore::restore() cannot run without it.
 *
 * The WordPress user is the one WP-CLI runs as (`--user`). That option
 * selects a user; it does not authenticate one - whoever can run WP-CLI
 * on the server can name any user. The capability check limits who can
 * be named, not who is typing.
 */
final class LegacyExpenseMigrationOperator
{
    /**
     * The System Administrator capability (AccountManagementAdminPage::CAPABILITY;
     * a test keeps the two equal - Accounting does not depend on Presentation).
     */
    public const CAPABILITY = 'stageart_manage_accounts';

    public const REFUSED_NO_USER = 'OPERATOR_NOT_GIVEN';
    public const REFUSED_UNKNOWN_USER = 'OPERATOR_UNKNOWN_USER';
    public const REFUSED_NOT_PERMITTED = 'OPERATOR_NOT_PERMITTED';
    public const REFUSED_NO_PERSON = 'OPERATOR_NO_PERSON';
    public const REFUSED_AMBIGUOUS_PERSON = 'OPERATOR_AMBIGUOUS_PERSON';
    public const REFUSED_INVALID_PERSON = 'OPERATOR_INVALID_PERSON';

    private int $wpUserId;
    private string $personId;
    private ?string $osUser;

    private function __construct(int $wpUserId, string $personId, ?string $osUser)
    {
        $this->wpUserId = $wpUserId;
        $this->personId = $personId;
        $this->osUser = $osUser;
    }

    /**
     * The operator, or the reason there is none. Checks in order: a user
     * was given, it exists, it holds CAPABILITY, and exactly one Person
     * with a valid id is linked to it.
     *
     * @param \wpdb $wpdb
     * @param bool $userExists whether WordPress knows the user (get_userdata())
     * @param bool $hasCapability whether the user holds CAPABILITY (user_can())
     * @param string|null $osUser the OS account running WP-CLI, when known
     * @return self|string
     */
    public static function resolve($wpdb, int $wpUserId, bool $userExists, bool $hasCapability, ?string $osUser)
    {
        if ($wpUserId <= 0) {
            return self::REFUSED_NO_USER;
        }

        if (! $userExists) {
            return self::REFUSED_UNKNOWN_USER;
        }

        if (! $hasCapability) {
            return self::REFUSED_NOT_PERMITTED;
        }

        $people = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}stageart_people WHERE wp_user_id = %d", $wpUserId)) ?: [];

        if (count($people) === 0) {
            return self::REFUSED_NO_PERSON;
        }

        if (count($people) > 1) {
            return self::REFUSED_AMBIGUOUS_PERSON;
        }

        $personId = (string) $people[0];

        if (! Uuid::isValid($personId)) {
            return self::REFUSED_INVALID_PERSON;
        }

        return new self($wpUserId, $personId, self::cleanOsUser($osUser));
    }

    /**
     * resolve() for the WordPress user WP-CLI runs as.
     *
     * @param \wpdb $wpdb
     * @return self|string
     */
    public static function fromCurrentWordPressUser($wpdb, ?string $osUser)
    {
        $wpUserId = (int) get_current_user_id();
        $exists = $wpUserId > 0 && get_userdata($wpUserId) !== false;

        return self::resolve($wpdb, $wpUserId, $exists, $exists && user_can($wpUserId, self::CAPABILITY), $osUser);
    }

    /**
     * The OS account running PHP: the effective user when the posix
     * extension is there, otherwise USER / USERNAME from the environment
     * (which the caller controls) marked "env:", otherwise null.
     */
    public static function detectOsUser(): ?string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $pw = posix_getpwuid(posix_geteuid());

            if (is_array($pw) && (string) ($pw['name'] ?? '') !== '') {
                return (string) $pw['name'];
            }
        }

        $env = getenv('USER') ?: getenv('USERNAME');

        return $env === false || $env === '' ? null : 'env:' . $env;
    }

    public function wpUserId(): int
    {
        return $this->wpUserId;
    }

    public function personId(): string
    {
        return $this->personId;
    }

    /** At most 64 of [A-Za-z0-9._@:-], or null when unknown. */
    public function osUser(): ?string
    {
        return $this->osUser;
    }

    /** " person=<id> wp_user=<id> os_user=<name|unknown>" for logs and output. */
    public function describe(): string
    {
        return " person={$this->personId} wp_user={$this->wpUserId} os_user=" . ($this->osUser ?? 'unknown');
    }

    private static function cleanOsUser(?string $osUser): ?string
    {
        $clean = substr((string) preg_replace('/[^A-Za-z0-9._@:-]/', '', (string) $osUser), 0, 64);

        return $clean === '' ? null : $clean;
    }
}
