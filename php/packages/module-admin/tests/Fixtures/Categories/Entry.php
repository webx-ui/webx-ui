<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures\Categories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use WebxUi\Admin\Categories\HasCategories;

/**
 * A record filed under sections, ordered by hand in the whole list.
 *
 * @property int $id
 * @property string $name
 * @property int $position
 */
final class Entry extends Model
{
    use HasCategories;

    protected $table = 'entries';

    protected $guarded = [];

    public function categoryRelation(): string
    {
        return 'sections';
    }

    /**
     * @return BelongsToMany<Section, $this>
     */
    public function sections(): BelongsToMany
    {
        /** @var BelongsToMany<Section, $this> $relation */
        $relation = $this->belongsToCategories(Section::class, 'entry_category', 'entry_id', 'category_id');

        return $relation;
    }
}
