<?php

declare(strict_types=1);

namespace WebxUi\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named set of permissions.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property list<string> $permissions
 */
class Role extends Model
{
    protected $table = 'cms_roles';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    /**
     * @return BelongsToMany<CmsUser, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(CmsUser::class, 'cms_role_user', 'cms_role_id', 'cms_user_id');
    }

    public function grants(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }
}
