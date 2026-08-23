<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

/**
 * What a verified Google ID Token yields, after issuer/audience/expiry/
 * signature checks have already passed (see GoogleIdTokenVerifierInterface).
 * `sub` is the only field used as an identity key anywhere downstream
 * (ExternalIdentity.providerUserId) - per UserAccount.md's explicit
 * "emailをGoogle Identityの主キーにしない". `email` is carried only for
 * WordPress User auto-provisioning bookkeeping (see
 * WordPressUserProvisionerInterface), never as an identity lookup key.
 *
 * StageArt Authentication Phase 6: familyName/givenName are carried
 * purely as UI hints for mobile-rn's set-name.tsx default values -
 * never written to Person automatically (see AuthenticateWithGoogleUseCase's
 * docblock). Google does not guarantee these claims are present (a
 * Google Account can have no name set, or the consent screen can omit
 * the `profile` scope in some configurations), so both are nullable and
 * must be treated as optional everywhere downstream.
 */
final class GoogleIdTokenClaims
{
    public string $sub;
    public ?string $email;
    public ?string $familyName;
    public ?string $givenName;

    public function __construct(string $sub, ?string $email, ?string $familyName = null, ?string $givenName = null)
    {
        $this->sub = $sub;
        $this->email = $email;
        $this->familyName = $familyName;
        $this->givenName = $givenName;
    }
}
