<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Authentication;

use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Infrastructure\Authentication\JwtAccessTokenVerifier;

/**
 * Registers on WordPress's `determine_current_user` filter - the same
 * extension point WordPress's own Application Password authentication
 * uses internally. This is what lets every existing REST Controller and
 * Use Case (all built around get_current_user_id()/is_user_logged_in())
 * keep working completely unchanged: this class's only job is to make
 * WordPress's own "who is the current user" resolution succeed for a
 * valid Bearer Access Token request, exactly as if the caller had
 * authenticated some other way - see this Phase's design report §1.
 *
 * If no Bearer token is present, or it fails verification, this returns
 * $input unchanged so any other authentication method already in
 * WordPress's filter chain (Application Password Basic Auth, cookie
 * auth, etc.) is completely unaffected - the two coexist, they are never
 * both consulted for the same header.
 */
final class CurrentUserResolver
{
    private JwtAccessTokenVerifier $verifier;
    private PersonRepositoryInterface $people;

    public function __construct(JwtAccessTokenVerifier $verifier, PersonRepositoryInterface $people)
    {
        $this->verifier = $verifier;
        $this->people = $people;
    }

    /**
     * @param int|bool $input
     * @return int|bool
     */
    public function resolve($input)
    {
        if (! empty($input)) {
            return $input;
        }

        $header = $this->authorizationHeader();

        if ($header === null || stripos($header, 'Bearer ') !== 0) {
            return $input;
        }

        $token = trim(substr($header, 7));
        $claims = $this->verifier->verify($token);

        if ($claims === null) {
            return $input;
        }

        try {
            $person = $this->people->findById(PersonId::fromString($claims->personId));
        } catch (InvalidArgumentException $exception) {
            return $input;
        }

        if (! $person) {
            return $input;
        }

        return $person->wordPressUserId();
    }

    private function authorizationHeader(): ?string
    {
        if (! empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    return (string) $value;
                }
            }
        }

        return null;
    }
}
