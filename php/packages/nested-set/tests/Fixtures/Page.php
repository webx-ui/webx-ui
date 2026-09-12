<?php

declare(strict_types=1);

namespace WebxUi\NestedSet\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\NestedSet\HasNestedSet;

/**
 * One table, one tree per site.
 *
 * @property string $name
 * @property int $site_id
 * @property int|null $parent_id
 */
class Page extends Model
{
    use HasNestedSet;

    protected $table = 'pages';

    protected $guarded = [];

    /**
     * @return list<string>
     */
    public function getNestedSetScopeAttributes(): array
    {
        return ['site_id'];
    }
}
