<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use WebxUi\Admin\Categories\HasCategories;
use WebxUi\Admin\Relations\HasRelations;
use WebxUi\Admin\Tests\Fixtures\Categories\Section;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;

/**
 * A record the way a recipe will be one: drafted, filed under two kinds of category, pointing at
 * chefs and at dishes like it.
 *
 * Not final, for the reason `Section` gives: PHPStan does not take `$this` of a final class for
 * the `$this` a trait's relation promises.
 *
 * @property int $id
 * @property string $title
 * @property int $position
 * @property array<string, mixed>|null $draft
 */
class Dish extends Model
{
    use HasCategories;
    use HasDraft;
    use HasRelations;
    use HasVersions;
    use SoftDeletes;

    protected $table = 'dishes';

    protected $guarded = [];

    public function relationKey(): string
    {
        return 'dish';
    }

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
        $relation = $this->belongsToCategories(Section::class, 'dish_section', 'dish_id', 'category_id');

        return $relation;
    }

    /**
     * The second kind: what the dish is rich in, filed the same way.
     *
     * @return BelongsToMany<Section, $this>
     */
    public function flavours(): BelongsToMany
    {
        /** @var BelongsToMany<Section, $this> $relation */
        $relation = $this->belongsToCategories(Section::class, 'dish_flavour', 'dish_id', 'category_id');

        return $relation;
    }

    public function isVisible(?string $locale = null): bool
    {
        return $this->isPublished() && ! $this->trashed();
    }
}
