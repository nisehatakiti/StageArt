<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

/**
 * The sign-in path for an address - the only things the login screen needs
 * to choose its next step. Never an id, an address, a hash or whether the
 * address came from the login credential or a notification address.
 *
 * - PASSWORD_LOGIN: a verified e-mail login exists (no Google identity).
 * - MULTIPLE_AUTH_METHODS: a verified e-mail login AND a linked Google identity.
 * - EMAIL_VERIFICATION_REQUIRED: an e-mail login exists whose address is not
 *   verified yet (`methods` also lists `google` when one is linked).
 * - GOOGLE_LOGIN: no e-mail login; a Google identity whose Google-verified
 *   address is this one.
 * - NOT_FOUND: neither.
 * - DISABLED: the account exists but is SUSPENDED / DISABLED.
 */
final class CheckAccountResult
{
    public const PASSWORD_LOGIN = 'PASSWORD_LOGIN';
    public const MULTIPLE_AUTH_METHODS = 'MULTIPLE_AUTH_METHODS';
    public const EMAIL_VERIFICATION_REQUIRED = 'EMAIL_VERIFICATION_REQUIRED';
    public const GOOGLE_LOGIN = 'GOOGLE_LOGIN';
    public const NOT_FOUND = 'NOT_FOUND';
    public const DISABLED = 'DISABLED';

    public const METHOD_PASSWORD = 'password';
    public const METHOD_GOOGLE = 'google';

    public string $status;

    /** @var string[] the sign-in methods that can be used now (`password`, `google`) */
    public array $methods;

    /**
     * @param string[] $methods
     */
    public function __construct(string $status, array $methods)
    {
        $this->status = $status;
        $this->methods = $methods;
    }

    /**
     * @return array{status: string, methods: string[]}
     */
    public function toArray(): array
    {
        return ['status' => $this->status, 'methods' => $this->methods];
    }
}
