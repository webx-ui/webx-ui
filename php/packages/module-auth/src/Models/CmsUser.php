<?php

declare(strict_types=1);

namespace WebxUi\Auth\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Somebody who administers the site — not somebody who uses it.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_super
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 */
class CmsUser extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasApiTokens;

    protected $table = 'cms_users';

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_super' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'cms_role_user', 'cms_user_id', 'cms_role_id');
    }

    /**
     * @return HasMany<LoginRecord, $this>
     */
    public function loginRecords(): HasMany
    {
        return $this->hasMany(LoginRecord::class, 'cms_user_id')->latest();
    }

    /**
     * A super administrator answers yes to everything: somebody has to be able to grant the
     * first permission, and a panel whose only administrator has locked themselves out is
     * worse than one with a documented bypass.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->is_super) {
            return true;
        }

        return $this->roles->contains(static fn (Role $role): bool => $role->grants($permission));
    }

    /**
     * Everything this administrator may do, for the front end to hide what it must.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        $permissions = $this->roles
            ->flatMap(static fn (Role $role): array => $role->permissions ?? [])
            ->unique()
            ->values()
            ->all();

        sort($permissions);

        return $permissions;
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }
}
