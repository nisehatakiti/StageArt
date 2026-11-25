<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

/**
 * R14-B §4.6 / §7.1 (stage 4): the target is an ACTIVE PERSON Participant
 * but their consent is not recorded (consented_at IS NULL) - 422
 * `stageart_production_delegate_target_not_consented`.
 */
final class ProductionDelegateTargetNotConsentedException extends ProductionDelegateTargetNotEligibleException
{
}
