<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Auth\Events\AdminLoggedIn;
use WebxUi\Auth\Events\AdminLoggedOut;
use WebxUi\Auth\Events\AdminLoginFailed;
use WebxUi\Auth\Http\Resources\CmsUserPayload;
use WebxUi\Auth\Models\CmsUser;

final class SessionController
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Dispatcher $events,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $guard = $this->guard();
        $email = (string) $credentials['email'];

        $attempted = $guard->attempt(
            ['email' => $email, 'password' => $credentials['password']],
            (bool) ($credentials['remember'] ?? false),
        );

        if (! $attempted) {
            $this->events->dispatch(new AdminLoginFailed(
                $email,
                $request->ip(),
                $request->userAgent(),
                CmsUser::query()->where('email', $email)->value('id'),
            ));

            // The same answer whether the address exists or not: a different one would turn
            // the login form into a way to enumerate administrators.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = $guard->user();

        // Checked after the password rather than before, so a disabled account cannot be told
        // apart from a wrong one without knowing the password.
        if ($user instanceof CmsUser && ! $user->is_active) {
            $guard->logout();

            $this->events->dispatch(new AdminLoginFailed(
                $email,
                $request->ip(),
                $request->userAgent(),
                $user->id,
            ));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var CmsUser $user */
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        $this->events->dispatch(new AdminLoggedIn($user, $request->ip(), $request->userAgent()));

        return ApiResponse::data(CmsUserPayload::make($user));
    }

    public function destroy(Request $request): JsonResponse
    {
        $guard = $this->guard();
        $user = $guard->user();

        $guard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user instanceof CmsUser) {
            $this->events->dispatch(new AdminLoggedOut($user, $request->ip(), $request->userAgent()));
        }

        return ApiResponse::noContent();
    }

    private function guard(): StatefulGuard
    {
        $guard = $this->auth->guard((string) config('webx-auth.guard'));

        /** @var StatefulGuard $guard */
        return $guard;
    }
}
