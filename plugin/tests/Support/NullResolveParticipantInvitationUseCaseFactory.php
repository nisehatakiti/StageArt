<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Participant\CreateParticipantUseCase;
use StageArt\Application\ParticipantInvitation\ResolveParticipantInvitationUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Person\PersonRepositoryInterface;

/**
 * Authentication test files (RegisterWithEmailUseCase/
 * AuthenticateWithGoogleUseCase) now require a
 * ResolveParticipantInvitationUseCase, but none of those tests create
 * any ParticipantInvitation, so it is always a no-op there (its
 * InMemoryParticipantInvitationRepository is always empty). This
 * factory builds one real, correctly-wired instance (backed entirely by
 * empty InMemory repositories) so every Authentication test file does
 * not have to repeat the same several-line dependency graph.
 */
final class NullResolveParticipantInvitationUseCaseFactory
{
    public static function create(PersonRepositoryInterface $people): ResolveParticipantInvitationUseCase
    {
        return new ResolveParticipantInvitationUseCase(
            new InMemoryParticipantInvitationRepository(),
            new InMemoryParticipantRepository(),
            $people,
            new CreateParticipantUseCase(
                new InMemoryProductionRepository(),
                new InMemoryParticipantRepository(),
                $people,
                new InMemoryOrganizationRepository(),
                new ProductionAuthorizationService(
                    new OrganizationAuthorizationService($people, new InMemoryMembershipRepository()),
                    new InMemoryProductionDelegateRepository(),
                    new InMemoryParticipantRepository()
                ),
                new InMemoryTransactionManager()
            )
        );
    }
}
