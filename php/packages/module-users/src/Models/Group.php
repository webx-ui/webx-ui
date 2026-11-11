<?php

declare(strict_types=1);

namespace WebxUi\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Localization\HasCodes;

/**
 * A customer group (spec §13.1): a code the rules of a shop and the site's code name it by, and a
 * name in one language for the panel (decision 24) — no translations, no default group, no order.
 *
 * Deleting one is never refused: its links to the visitors go with it by the cascade, and a rule
 * that names the code simply stops matching.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Group extends Model
{
    use HasCodes;
    use RecordsHistory;

    /** The journal's type (§16). */
    public const string TYPE = 'users.group';

    /** `[a-z0-9-]`, as a field of the site is named. */
    public const string CODE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    protected $table = 'users_groups';

    protected $fillable = ['code', 'name'];

    /** Typed by a person or an agent: «Оптовики» is kept as `optoviki`, not refused (#125). */
    protected static function codes(): array
    {
        return ['code' => ['max' => 64]];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_group_user', 'group_id', 'user_id')->withPivot('created_at');
    }
}
