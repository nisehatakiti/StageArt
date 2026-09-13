<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use RuntimeException;

/**
 * Production::complete()'s own docblock ("精算" -> "決算完了") flagged this
 * exact gap as an Open Item pending Production Settlement's existence:
 * "具体的な決算完了条件はAccounting Domainで定義する...a real computed Guard
 * should replace this once Production Settlement exists." Phase 4
 * implements that Guard: every Production Member with a confirmed,
 * unsettled Ticket Back amount must be settled (see
 * SettleProductionMemberUseCase) before the Production can advance past
 * ACTIVE.
 */
final class ProductionSettlementIncompleteException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This Production cannot be completed until every member\'s Ticket Back is settled.');
    }
}
