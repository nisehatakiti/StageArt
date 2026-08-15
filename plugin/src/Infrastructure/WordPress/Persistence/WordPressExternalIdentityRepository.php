<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\UserAccount\ExternalIdentity;
use StageArt\Domain\UserAccount\ExternalIdentityId;
use StageArt\Domain\UserAccount\ExternalIdentityRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountId;
use wpdb;

final class WordPressExternalIdentityRepository implements ExternalIdentityRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_external_identities';
    }

    public function save(ExternalIdentity $identity): void
    {
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $identity->id()->toString())
        );

        if ($existing) {
            // ExternalIdentity has no mutators in this slice (create-only), so there is
            // nothing to update; save() is idempotent for an already-persisted identity.
            return;
        }

        $row = [
            'id' => $identity->id()->toString(),
            'user_account_id' => $identity->userAccountId()->toString(),
            'provider' => $identity->provider(),
            'provider_user_id' => $identity->providerUserId(),
            'linked_at' => $identity->linkedAt()->format('Y-m-d H:i:s'),
        ];

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException("Failed to insert into {$this->table}: " . $this->wpdb->last_error);
        }
    }

    public function findByUserAccountId(UserAccountId $userAccountId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE user_account_id = %s", $userAccountId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByProviderAndProviderUserId(string $provider, string $providerUserId): ?ExternalIdentity
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE provider = %s AND provider_user_id = %s",
                $provider,
                $providerUserId
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): ExternalIdentity
    {
        return ExternalIdentity::reconstitute(
            ExternalIdentityId::fromString($row['id']),
            UserAccountId::fromString($row['user_account_id']),
            $row['provider'],
            $row['provider_user_id'],
            new DateTimeImmutable($row['linked_at'])
        );
    }
}
