<?php

declare(strict_types=1);

namespace StageArt\Application\Person;

/**
 * Deliberately narrower than CurrentPersonResult: this is the output of
 * "did the name update", not a general snapshot of the caller's Person -
 * reusing CurrentPersonResult here would have forced a fabricated
 * emailVerified value (UpdatePersonNameUseCase never touches
 * UserAccount/EmailCredential, so it has no real value to report).
 */
final class UpdatePersonNameResult
{
    public string $id;
    public string $familyName;
    public string $givenName;

    public function __construct(string $id, string $familyName, string $givenName)
    {
        $this->id = $id;
        $this->familyName = $familyName;
        $this->givenName = $givenName;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'family_name' => $this->familyName,
            'given_name' => $this->givenName,
        ];
    }
}
