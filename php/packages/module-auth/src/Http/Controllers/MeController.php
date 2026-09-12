<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Auth\Http\Resources\CmsUserPayload;
use WebxUi\Auth\Models\CmsUser;

/**
 * The panel's first call. A 401 here is how the front end learns to draw the login form.
 */
final class MeController
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function __invoke(): JsonResponse
    {
        $user = $this->auth->guard((string) config('webx-auth.guard'))->user();

        return ApiResponse::data($user instanceof CmsUser ? CmsUserPayload::make($user) : null);
    }
}
