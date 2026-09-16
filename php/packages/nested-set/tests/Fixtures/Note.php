<?php

declare(strict_types=1);

namespace WebxUi\NestedSet\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use WebxUi\NestedSet\HasNestedSet;

/**
 * A tree that keeps its trashed nodes standing — the shape `webx-ui/module-pages` has.
 *
 * @property string $name
 * @property int|null $parent_id
 */
class Note extends Model
{
    use HasNestedSet;
    use SoftDeletes;

    protected $table = 'notes';

    protected $guarded = [];

    public function softDeletesInTree(): bool
    {
        return true;
    }
}
