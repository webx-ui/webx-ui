<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;

/**
 * One administrator, as the administrators screen sees them.
 *
 * Not `CmsUserPayload`: that one describes whoever is signed in, and carries the flattened
 * permissions the front end hides buttons with. This one describes somebody being looked at,
 * and what matters there is which roles they hold, not what those roles add up to.
 *
 * @mixin CmsUser
 */
final class AdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CmsUser $user */
        $user = $this->resource;
        $user->loadMissing('roles');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            // The library's key, as everywhere else. The address is worked out where the
            // picture is drawn, so moving the library changes nothing stored here.
            'avatar' => $user->avatar,
            'is_super' => $user->is_super,
            'is_active' => $user->is_active,
            'locale' => $user->locale,
            'last_login_at' => $user->last_login_at?->toAtomString(),
            'created_at' => $user->created_at?->toAtomString(),
            'roles' => $user->roles
                ->map(static fn (Role $role): array => [
                    'id' => $role->id,
                    'slug' => $role->slug,
                    'name' => $role->name,
                ])
                ->values()
                ->all(),
        ];
    }
}
