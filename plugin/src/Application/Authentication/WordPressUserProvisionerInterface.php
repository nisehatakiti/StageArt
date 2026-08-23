<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

/**
 * Port for auto-provisioning the hidden WordPress User a new Person
 * still needs internally (Person.wordPressUserId - see this Phase's
 * design report §2/§3: not removed this Phase, but never exposed to
 * the StageArt user). The generated username/password are never
 * returned to any caller - this method's only output is the resulting
 * WordPress User ID.
 */
interface WordPressUserProvisionerInterface
{
    /**
     * @param string|null $googleEmail Used only as a hint for the WordPress
     *   User's own internal email column (WordPress core requires one) -
     *   never used as an identity lookup key anywhere in StageArt's own
     *   Domain.
     */
    public function provision(?string $googleEmail): int;
}
