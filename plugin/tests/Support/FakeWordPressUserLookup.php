<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\UserAccount\WordPressUserInfo;
use StageArt\Application\UserAccount\WordPressUserLookupInterface;

final class FakeWordPressUserLookup implements WordPressUserLookupInterface
{
    /** @var array<int, WordPressUserInfo> */
    private array $users = [];

    public function register(int $wordPressUserId, string $email, string $displayName): void
    {
        $this->users[$wordPressUserId] = new WordPressUserInfo($email, $displayName);
    }

    public function find(int $wordPressUserId): ?WordPressUserInfo
    {
        return $this->users[$wordPressUserId] ?? null;
    }
}
