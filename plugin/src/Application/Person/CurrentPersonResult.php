<?php

declare(strict_types=1);

namespace StageArt\Application\Person;

final class CurrentPersonResult
{
    public string $id;
    public int $wordPressUserId;
    public bool $emailVerified;
    public ?string $familyName;
    public ?string $givenName;

    public function __construct(
        string $id,
        int $wordPressUserId,
        bool $emailVerified,
        ?string $familyName,
        ?string $givenName
    ) {
        $this->id = $id;
        $this->wordPressUserId = $wordPressUserId;
        $this->emailVerified = $emailVerified;
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
            'word_press_user_id' => $this->wordPressUserId,
            'email_verified' => $this->emailVerified,
            'family_name' => $this->familyName,
            'given_name' => $this->givenName,
        ];
    }
}
