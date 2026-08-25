<?php

declare(strict_types=1);

namespace StageArt\Domain\Favorite;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;

/**
 * docs/04-DomainModel/Follow.md's "Favorite": "Personが自分のために保存する
 * 対象" - deliberately simpler than OrganizationFollow (see
 * StageArt\Domain\Follow\OrganizationFollow): a plain save/remove, no
 * ACTIVE/UNFOLLOWED status history, since Favorite carries no ongoing
 * relationship semantics ("Favorite登録だけでHomeへの継続的な新着配信を発生
 * させない" - there is nothing to reactivate). Unfavoriting deletes the
 * row outright (see FavoriteRepositoryInterface::delete()).
 */
final class Favorite
{
    public const TARGET_TYPE_ORGANIZATION = 'ORGANIZATION';
    public const TARGET_TYPE_PRODUCTION = 'PRODUCTION';

    private const VALID_TARGET_TYPES = [self::TARGET_TYPE_ORGANIZATION, self::TARGET_TYPE_PRODUCTION];

    private FavoriteId $id;
    private PersonId $personId;
    private string $targetType;
    private string $targetId;
    private DateTimeImmutable $favoritedAt;

    private function __construct(FavoriteId $id, PersonId $personId, string $targetType, string $targetId, DateTimeImmutable $favoritedAt)
    {
        if (! in_array($targetType, self::VALID_TARGET_TYPES, true)) {
            throw new InvalidArgumentException("Invalid Favorite targetType: {$targetType}");
        }

        $this->id = $id;
        $this->personId = $personId;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->favoritedAt = $favoritedAt;
    }

    public static function create(PersonId $personId, string $targetType, string $targetId): self
    {
        return new self(FavoriteId::generate(), $personId, $targetType, $targetId, new DateTimeImmutable());
    }

    public static function reconstitute(
        FavoriteId $id,
        PersonId $personId,
        string $targetType,
        string $targetId,
        DateTimeImmutable $favoritedAt
    ): self {
        return new self($id, $personId, $targetType, $targetId, $favoritedAt);
    }

    public function id(): FavoriteId
    {
        return $this->id;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function targetType(): string
    {
        return $this->targetType;
    }

    public function targetId(): string
    {
        return $this->targetId;
    }

    public function favoritedAt(): DateTimeImmutable
    {
        return $this->favoritedAt;
    }
}
