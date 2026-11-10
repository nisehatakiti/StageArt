<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionClosedException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Ticket\TicketBackCondition;
use StageArt\Domain\Ticket\TicketBackMode;

/**
 * Chapter 32 §4's チケットバック／ノルマ設定 screen - one Production-wide
 * save. §19/§20: quota is Production-wide only (no per-member menu), and
 * the buyback-off -> no unit price / buyback-on -> unit price required
 * invariant is enforced here (via Production::updateQuota()) regardless
 * of what the UI sends, matching instruction §20's "UIだけで入力欄を隠す
 * 実装にはしない".
 *
 * PR #7 review (R5): the settings are applied to the Production as re-read
 * under the exclusive Production lock and saved in the same transaction.
 * - Ticket Back is computed from these settings when a member is settled
 *   and when Production Completion checks the settlement; both hold the
 *   same exclusive lock, so neither runs between this read and the commit.
 * - save() writes every column of the Production row, so saving a copy
 *   read before the lock could put back a status (reopen a Production
 *   that Completion had just closed) or another settings change.
 * - Once the Production is COMPLETED / ARCHIVED its settlement is closed,
 *   and changing what it was computed from is refused (409
 *   `stageart_production_closed`), like a settlement cancellation.
 */
final class UpdateQuotaAndTicketBackSettingsUseCase
{
    private ProductionRepositoryInterface $productions;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ProductionRepositoryInterface $productions,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->productions = $productions;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(UpdateQuotaAndTicketBackSettingsCommand $command): QuotaAndTicketBackSettingsResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new TicketAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($command->productionId);
        $production = $this->productions->findById($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, TicketCapability::MANAGE)) {
            throw new TicketAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the TICKET_MANAGER Role can update quota/ticket back settings.'
            );
        }

        $ticketBackMode = null;
        $ticketBackRules = null;

        if ($command->ticketBackMode !== null) {
            $ticketBackMode = TicketBackMode::fromString($command->ticketBackMode)->toString();
            $conditions = array_map(
                static fn (array $data): TicketBackCondition => TicketBackCondition::fromArray($data),
                $command->ticketBackConditions
            );

            if ($conditions === []) {
                throw new InvalidArgumentException('At least one Ticket Back condition is required when Ticket Back is enabled.');
            }

            $ticketBackRules = json_encode(array_map(static fn (TicketBackCondition $c): array => $c->toArray(), $conditions));
        }

        $updated = $this->transactions->run(function () use ($productionId, $command, $ticketBackMode, $ticketBackRules): Production {
            $locked = $this->productions->findByIdForUpdate($productionId);

            if (! $locked) {
                throw new ProductionNotFoundException($command->productionId);
            }

            ProductionClosedException::throwIfIn($locked, ProductionClosedException::SETTLED);

            $locked->updateQuota(
                $command->quotaEnabled,
                $command->quotaCount,
                $command->quotaBuybackEnabled,
                $command->quotaShortfallUnitPrice
            );
            $locked->updateTicketBack($ticketBackMode, $ticketBackRules);

            $this->productions->save($locked);

            return $locked;
        });

        return QuotaAndTicketBackSettingsResult::fromDomain($updated);
    }
}
