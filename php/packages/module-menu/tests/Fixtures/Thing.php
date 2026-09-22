<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use WebxUi\Localization\HasTranslations;

/**
 * Something a menu item can point at, standing in for a page or an article.
 *
 * A fixture rather than `module-pages`, because what is under test here is the menu: the three
 * facts a menu needs from an entity are its name, its address and whether the site would show
 * it, and a content module is a great deal of machinery to install in order to supply three.
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property string $slug
 * @property bool $published
 */
class Thing extends Model
{
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'things';

    /** @var list<string> */
    protected $fillable = ['title', 'slug', 'published'];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }
}
