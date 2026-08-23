<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Person;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;

final class PersonTest extends TestCase
{
    public function test_a_newly_created_person_has_no_name_set(): void
    {
        $person = Person::create(1);

        $this->assertNull($person->familyName());
        $this->assertNull($person->givenName());
        $this->assertFalse($person->hasName());
    }

    public function test_set_name_updates_both_fields(): void
    {
        $person = Person::create(1);

        $person->setName('秦', '良輔');

        $this->assertSame('秦', $person->familyName());
        $this->assertSame('良輔', $person->givenName());
        $this->assertTrue($person->hasName());
    }

    public function test_set_name_can_be_called_again_to_change_the_name(): void
    {
        $person = Person::create(1);
        $person->setName('旧姓', '旧名');

        $person->setName('新姓', '新名');

        $this->assertSame('新姓', $person->familyName());
        $this->assertSame('新名', $person->givenName());
    }

    public function test_reconstitute_without_a_name_matches_a_freshly_created_person(): void
    {
        $person = Person::reconstitute(PersonId::generate(), 1);

        $this->assertNull($person->familyName());
        $this->assertNull($person->givenName());
        $this->assertFalse($person->hasName());
    }

    public function test_reconstitute_restores_a_previously_set_name(): void
    {
        $person = Person::reconstitute(PersonId::generate(), 1, '秦', '良輔');

        $this->assertSame('秦', $person->familyName());
        $this->assertSame('良輔', $person->givenName());
        $this->assertTrue($person->hasName());
    }

    public function test_word_press_user_id_and_identity_are_unaffected_by_name_changes(): void
    {
        $id = PersonId::generate();
        $person = Person::reconstitute($id, 42);

        $person->setName('秦', '良輔');

        $this->assertSame($id, $person->id());
        $this->assertSame(42, $person->wordPressUserId());
    }
}
