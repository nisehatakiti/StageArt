<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Organization\OrganizationSlug;
use StageArt\Domain\Organization\OrganizationStatus;
use StageArt\Domain\Role\RoleKey;

final class UpdateOrganizationUseCase
{
    private OrganizationRepositoryInterface $organizations;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        OrganizationRepositoryInterface $organizations,
        OrganizationAuthorizationService $authorization
    ) {
        $this->organizations = $organizations;
        $this->authorization = $authorization;
    }

    public function execute(UpdateOrganizationCommand $command): OrganizationResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner can update this Organization.');
        }

        $organization = $this->organizations->findById($organizationId);

        if (! $organization) {
            throw new OrganizationNotFoundException($command->organizationId);
        }

        $organization->rename(new OrganizationName($command->name));
        $organization->changeType($command->type);
        $organization->changeDescription($command->description);

        if ($command->slug !== null) {
            $newSlug = new OrganizationSlug($command->slug);
            $currentSlug = $organization->slug();

            if ($currentSlug === null || ! $currentSlug->equals($newSlug)) {
                $existing = $this->organizations->findBySlug($newSlug->toString());

                if ($existing !== null && ! $existing->id()->equals($organizationId)) {
                    throw new OrganizationSlugAlreadyTakenException($newSlug->toString());
                }

                $organization->changeSlug($newSlug);
            }
        }

        if ($command->published === true) {
            $organization->publish($this->parseOptionalDateTime($command->publishedAt));
        } elseif ($command->published === false) {
            $organization->unpublish();
        }

        if ($organization->status()->toString() !== $command->status) {
            switch ($command->status) {
                case OrganizationStatus::ACTIVE:
                    $organization->activate();
                    break;
                case OrganizationStatus::INACTIVE:
                    $organization->deactivate();
                    break;
                case OrganizationStatus::ARCHIVED:
                    $organization->archive();
                    break;
                default:
                    throw new InvalidArgumentException("Invalid status: {$command->status}");
            }
        }

        $this->organizations->save($organization);

        return OrganizationResult::fromDomain($organization, RoleKey::owner());
    }

    private function parseOptionalDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new InvalidArgumentException("Invalid published_at value: {$value}");
        }
    }
}
