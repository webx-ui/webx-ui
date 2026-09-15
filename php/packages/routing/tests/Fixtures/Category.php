<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\HasUrl;

/**
 * A plain, untranslated slug with the `suffix` policy — a catalogue section as an import
 * creates it, where a duplicate name must not stop the feed.
 *
 * @property string $slug
 */
class Category extends Model
{
    use HasUrl;

    protected $table = 'categories';

    protected $fillable = ['name', 'slug'];
}
