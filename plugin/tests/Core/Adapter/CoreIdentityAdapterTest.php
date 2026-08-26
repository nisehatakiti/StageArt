<?php

declare(strict_types=1);

namespace StageArt\Tests\Core\Adapter;

use PHPUnit\Framework\TestCase;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Domain\Person\Person;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class CoreIdentityAdapterTest extends TestCase
{
    public function test_resolves_a_registered_wordpress_user_to_a_person_id(): void
    {
        $people = new InMemoryPersonRepository();
        $person = Person::create(42);
        $people->save($person);

        $adapter = new CoreIdentityAdapter($people);

        $resolved = $adapter->resolveCurrentPersonId(42);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->equals($person->id()));
    }

    public function test_returns_null_for_an_unregistered_wordpress_user(): void
    {
        $adapter = new CoreIdentityAdapter(new InMemoryPersonRepository());

        $this->assertNull($adapter->resolveCurrentPersonId(999));
    }
}
