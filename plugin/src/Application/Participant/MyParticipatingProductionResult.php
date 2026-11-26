<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Production\Production;

/**
 * 「参加している公演・活動」 の正式なデータソース: the caller's own ACTIVE
 * PERSON Participant rows, each joined to its Production via the
 * existing ProductionRepositoryInterface::findByIds() (no N+1) - see
 * ListMyParticipatingProductionsUseCase. Deliberately narrow, matching
 * MyFollowResult's own precedent (id/name/slug only): a Person's own
 * participation list is not a Participant-management screen, so no
 * `status`/`remarks`/subject fields here.
 */
final class MyParticipatingProductionResult
{
    public string $participantId;
    public string $productionId;
    public string $productionName;
    public ?string $productionSlug;
    public string $participantType;

    public function __construct(
        string $participantId,
        string $productionId,
        string $productionName,
        ?string $productionSlug,
        string $participantType
    ) {
        $this->participantId = $participantId;
        $this->productionId = $productionId;
        $this->productionName = $productionName;
        $this->productionSlug = $productionSlug;
        $this->participantType = $participantType;
    }

    public static function fromDomain(Participant $participant, Production $production): self
    {
        return new self(
            $participant->id()->toString(),
            $production->id()->toString(),
            $production->name()->toString(),
            $production->slug()?->toString(),
            $participant->participantType()->toString()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'participant_id' => $this->participantId,
            'production_id' => $this->productionId,
            'production_name' => $this->productionName,
            'production_slug' => $this->productionSlug,
            'participant_type' => $this->participantType,
        ];
    }
}
