<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A record of some module, for the cache groups: saved, deleted, restored. */
final class Widget extends Model
{
    use SoftDeletes;

    protected $table = 'widgets';

    protected $guarded = [];
}
