<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

/**
 * StageArt Admin Console V1: mirrors WordPressUserProvisionerInterface's
 * shape (Application-owned contract, WordPress-specific implementation
 * in Infrastructure) - keeps this Use Case's own dependency abstract
 * rather than calling get_userdata() directly from the Application
 * Layer.
 */
interface WordPressUserLookupInterface
{
    public function find(int $wordPressUserId): ?WordPressUserInfo;
}
