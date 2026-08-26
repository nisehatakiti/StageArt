<?php

declare(strict_types=1);

namespace StageArt\Tests\Core\Adapter;

use PHPUnit\Framework\TestCase;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Tests\Support\InMemoryParticipantRepository;

final class CoreMembershipAdapterTest extends TestCase
{
    public function test_returns_only_active_person_subject_participants(): void
    {
        $participants = new InMemoryParticipantRepository();
        $productionId = ProductionId::generate();

        $activePerson = PersonId::generate();
        $active = Participant::create(
            $productionId,
            ParticipantSubjectType::person(),
            $activePerson->toString(),
            ParticipantType::cast()
        );
        $participants->save($active);

        $rejectedPerson = Participant::requestParticipation(
            $productionId,
            ParticipantSubjectType::person(),
            PersonId::generate()->toString(),
            ParticipantType::cast()
        );
        $rejectedPerson->reject();
        $participants->save($rejectedPerson);

        $organizationSubject = Participant::create(
            $productionId,
            ParticipantSubjectType::organization(),
            'some-organization-id',
            ParticipantType::staff()
        );
        $participants->save($organizationSubject);

        $adapter = new CoreMembershipAdapter($participants);

        $result = $adapter->activeProductionMemberPersonIds($productionId);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->equals($activePerson));
    }

    public function test_returns_empty_array_when_production_has_no_participants(): void
    {
        $participants = new InMemoryParticipantRepository();
        $adapter = new CoreMembershipAdapter($participants);

        $result = $adapter->activeProductionMemberPersonIds(ProductionId::generate());

        $this->assertSame([], $result);
    }
}
