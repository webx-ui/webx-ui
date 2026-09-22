<?php

declare(strict_types=1);

namespace WebxUi\Menu\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Links\Link;
use WebxUi\Localization\HasTranslations;
use WebxUi\NestedSet\HasNestedSet;

/**
 * One item of one menu.
 *
 * The columns spell out the link value of `module-admin` one to one, which is deliberate: a menu
 * item and a link field of a block are the same choice made in two places, so they go through
 * one parse, one validation and one normalisation. The two methods below are that seam, and
 * nothing else in this package reads the six columns directly.
 *
 * @property int $id
 * @property int $menu_id
 * @property array<string, string>|string|null $title
 * @property string $target
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property string|null $url
 * @property string|null $hash
 * @property string $variant
 * @property bool $is_heading
 * @property bool $new_tab
 * @property list<string>|null $rel
 * @property list<string>|null $locales
 * @property bool $visible
 * @property int $lft
 * @property int $rgt
 * @property int $depth
 * @property int|null $parent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MenuItem extends Model
{
    use HasNestedSet;
    use HasTranslations;

    protected $table = 'menu_items';

    /** @var list<string> */
    protected $fillable = [
        'menu_id',
        'title',
        'target',
        'entity_type',
        'entity_id',
        'url',
        'hash',
        'variant',
        'is_heading',
        'new_tab',
        'rel',
        'locales',
        'visible',
    ];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title'];
    }

    /**
     * Every menu is its own tree in one table, so bounds are only ever compared inside a menu.
     *
     * @return list<string>
     */
    public function getNestedSetScopeAttributes(): array
    {
        return ['menu_id'];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    /** Where this item points, as the value every link field in the panel uses. */
    public function link(): Link
    {
        return Link::fromArray([
            'target' => $this->target,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'url' => $this->url,
            'hash' => $this->hash,
            'new_tab' => $this->new_tab,
            'rel' => $this->rel ?? [],
        ]);
    }

    /** The other direction: a chosen link written into the columns, normalised on the way. */
    public function fillLink(Link $link): static
    {
        return $this->forceFill($link->toArray());
    }

    /**
     * The key of the menu this item is in — the one thing the cache needs when an item changes,
     * and one query rather than a loaded relation because a save hands over nothing else.
     */
    public function menuKey(): ?string
    {
        $key = Menu::query()->whereKey($this->menu_id)->value('key');

        return is_string($key) ? $key : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'menu_id' => 'integer',
            'parent_id' => 'integer',
            'entity_id' => 'integer',
            'is_heading' => 'boolean',
            'new_tab' => 'boolean',
            'visible' => 'boolean',
            'rel' => 'array',
            'locales' => 'array',
        ];
    }
}
