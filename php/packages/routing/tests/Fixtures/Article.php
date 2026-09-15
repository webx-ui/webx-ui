<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use WebxUi\Routing\HasUrl;

/**
 * Soft-deleted, so that a restore has something to restore — and something to collide with, if
 * the address was taken while it was in the bin.
 *
 * @property string $slug
 */
class Article extends Model
{
    use HasUrl;
    use SoftDeletes;

    protected $table = 'articles';

    protected $fillable = ['title', 'slug'];
}
