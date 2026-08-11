<?php

declare(strict_types=1);

namespace StageArt\Application\Auth;

final class LoginUseCase
{
    public function __construct(
        private UserAuthenticatorInterface $authenticator
    ) {
    }

    public function execute(LoginRequest $request): LoginResult
    {
        if ('' === trim($request->email) || '' === $request->password) {
            return LoginResult::failure('メールアドレスとパスワードを入力してください。');
        }

        $user = $this->authenticator->authenticate($request->email, $request->password);

        if (null === $user) {
            return LoginResult::failure('メールアドレスまたはパスワードが正しくありません。');
        }

        return LoginResult::success($user);
    }
}
