<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

final class DeleteUserAccountsCommand
{
    /** @var string[] */
    public array $userAccountIds;

    /**
     * @param string[] $userAccountIds
     */
    public function __construct(array $userAccountIds)
    {
        $this->userAccountIds = $userAccountIds;
    }
}
