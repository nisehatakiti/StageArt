<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Authentication;

use StageArt\Application\UserAccount\WordPressUserInfo;
use StageArt\Application\UserAccount\WordPressUserLookupInterface;

final class WordPressUserLookup implements WordPressUserLookupInterface
{
    public function find(int $wordPressUserId): ?WordPressUserInfo
    {
        $wpUser = get_userdata($wordPressUserId);

        if (! $wpUser) {
            return null;
        }

        return new WordPressUserInfo((string) $wpUser->user_email, (string) $wpUser->display_name);
    }
}
