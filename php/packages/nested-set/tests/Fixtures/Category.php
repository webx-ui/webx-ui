<?php

declare(strict_types=1);

namespace WebxUi\NestedSet\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\NestedSet\HasNestedSet;

/**
 * @property string $name
 * @property int|null $parent_id
 */
class Category extends Model
{
    use HasNestedSet;

    protected $table = 'categories';

    protected $guarded = [];
}
