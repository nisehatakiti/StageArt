<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
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
 */
final class UpdateQuotaAndTicketBackSettingsUseCase
{
    private ProductionRepositoryInterface $productions;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(ProductionRepositoryInterface $productions, IdentityContract $identity, AuthorizationContract $authorization)
    {
        $this->productions = $productions;
        $this->identity = $identity;
        $this->authorization = $authorization;
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

        $production->updateQuota(
            $command->quotaEnabled,
            $command->quotaCount,
            $command->quotaBuybackEnabled,
            $command->quotaShortfallUnitPrice
        );

        if ($command->ticketBackMode === null) {
            $production->updateTicketBack(null, null);
        } else {
            $mode = TicketBackMode::fromString($command->ticketBackMode);
            $conditions = array_map(
                static fn (array $data): TicketBackCondition => TicketBackCondition::fromArray($data),
                $command->ticketBackConditions
            );

            if ($conditions === []) {
                throw new InvalidArgumentException('At least one Ticket Back condition is required when Ticket Back is enabled.');
            }

            $production->updateTicketBack(
                $mode->toString(),
                json_encode(array_map(static fn (TicketBackCondition $c): array => $c->toArray(), $conditions))
            );
        }

        $this->productions->save($production);

        return QuotaAndTicketBackSettingsResult::fromDomain($production);
    }
}
