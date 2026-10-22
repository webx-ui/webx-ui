<?php

declare(strict_types=1);

namespace WebxUi\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Localization\Slug;

/**
 * A named set of permissions.
 *
 * Its changes go into the journal — which permissions came and went is the question an audit
 * of access asks first; who holds it is written by whoever assigns it (WEBX_UI_PERMISSIONS.md §9).
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property list<string> $permissions
 * @property list<string>|null $offered what a ready-made role has been offered so far; null for the site's own
 */
class Role extends Model
{
    use RecordsHistory;

    public const TYPE = 'admins.role';

    protected $table = 'cms_roles';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['permissions' => 'array', 'offered' => 'array'];
    }

    /**
     * @return BelongsToMany<CmsUser, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(CmsUser::class, 'cms_role_user', 'cms_role_id', 'cms_user_id');
    }

    /**
     * @return list<string>|null
     */
    public function historyFields(): ?array
    {
        return ['slug', 'name', 'permissions'];
    }

    /** A code made from the name that no other role has yet — what MCP and the filters name it by. */
    public static function freeSlug(string $name): string
    {
        // Named in the panel, so in the language the panel is open in.
        $base = Slug::make($name, app()->getLocale(), prefix: 'role');
        $slug = $base;

        for ($n = 2; static::query()->where('slug', $slug)->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    public function grants(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }
}
