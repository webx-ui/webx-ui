<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use WebxUi\Admin\Relations\HasRelations;

/**
 * The other end, the way a service is one: a module of its own, soft-deleted, retired by a flag —
 * and, without a draft, relating to dishes straight away.
 *
 * @property int $id
 * @property string $title
 * @property bool $retired
 * @property int $position
 */
class Chef extends Model
{
    use HasRelations;
    use SoftDeletes;

    protected $table = 'chefs';

    protected $guarded = [];

    protected $casts = ['retired' => 'boolean'];

    public function relationKey(): string
    {
        return 'chef';
    }

    public function isVisible(?string $locale = null): bool
    {
        return ! $this->retired;
    }
}
