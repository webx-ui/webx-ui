<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Localization\HasTranslations;
use WebxUi\NestedSet\HasNestedSet;
use WebxUi\Routing\HasUrl;

/**
 * A tree of pages with a translatable slug — the shape `module-pages` will have.
 *
 * @property array<string, string>|string $slug
 */
class Page extends Model
{
    use HasNestedSet;
    use HasTranslations;
    use HasUrl;

    protected $table = 'pages';

    protected $fillable = ['title', 'slug', 'published'];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug'];
    }
}
