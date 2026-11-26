<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

/**
 * R14-B §4.6 / §7.1 (stage 4): a delegate can be appointed (or made
 * ACTIVE again) only for an ACTIVE PERSON Participant of the Production -
 * 422 `stageart_production_delegate_target_not_participant`.
 */
final class ProductionDelegateTargetNotParticipantException extends ProductionDelegateTargetNotEligibleException
{
}
