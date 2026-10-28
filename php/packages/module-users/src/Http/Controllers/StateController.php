<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\JsonResponse;
use WebxUi\Users\Emails\Mask;
use WebxUi\Users\Models\User;
use WebxUi\Users\Urls;

/**
 * `GET /account/state` (spec §11.6): what a page cached for everybody may print about the visitor
 * looking at it, asked by `users.js` only when the cookie `webx_visitor` says somebody is signed
 * in — a guest never asks. The address goes masked: the page may be on a screen others see.
 */
final readonly class StateController
{
    public function __invoke(Auth $auth): JsonResponse
    {
        $user = $auth->guard(Urls::guard())->user();

        if (! $user instanceof User) {
            return new JsonResponse(['signedIn' => false]);
        }

        return new JsonResponse([
            'signedIn' => true,
            'name' => $user->name,
            'verified' => $user->hasVerifiedEmail(),
            'email' => Mask::email($user->email),
        ]);
    }
}
