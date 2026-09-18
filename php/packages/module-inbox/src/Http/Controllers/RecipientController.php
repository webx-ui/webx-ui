<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Auth\Models\CmsUser;

/**
 * Who a form can be told to write to.
 *
 * Not the full list of administrators: the ones who may read the section. A notification
 * about an enquiry is a link to a submission, and naming somebody who cannot open it is a
 * recipient who gets a letter and a 403 — which looks, from their side, like the panel is
 * broken rather than like the form is misconfigured.
 *
 * Asked for by the editor, so it is behind `inbox.manage` like the rest of it. An address
 * typed by hand needs none of this; this is only the shortcut for people with an account.
 */
final class RecipientController
{
    public function __invoke(): JsonResponse
    {
        $admins = CmsUser::query()
            ->where('is_active', true)
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->filter(static fn (CmsUser $admin): bool => $admin->hasPermission('inbox.view'))
            ->map(static fn (CmsUser $admin): array => [
                'id' => (int) $admin->getKey(),
                'name' => $admin->name,
                'email' => $admin->email,
            ])
            ->values()
            ->all();

        return ApiResponse::data($admins);
    }
}
