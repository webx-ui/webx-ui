<?php

declare(strict_types=1);

namespace WebxUi\Services\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Categories\Category;
use WebxUi\Admin\Categories\CategoryKind;
use WebxUi\Admin\Categories\IsCategory;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Blocks\HasBlocks;
use WebxUi\Localization\HasTranslations;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\HasSeo;
use WebxUi\Services\Seo\Trail;

/**
 * A category of services: a page of the site with its own address, SEO and fields (§4.2).
 *
 * Flat, several per service, and ordered by hand, like every module's categories. No draft and
 * no history — a rubric has none either, and a category is edited in place. What it has instead
 * is `is_visible`: hidden, it answers 404 and drops out of the index, while its services go on
 * answering at their own addresses, because they are not its property.
 *
 * The blocks are a column rather than a promise: the panel shows the tab only when
 * `webx-services.categories.blocks` is on, because the site's own view may not print them.
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $lead
 * @property int|null $cover_id
 * @property bool $is_visible
 * @property int $position
 * @property list<array<string, mixed>>|null $blocks
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ServiceCategory extends Model implements Category, HasBreadcrumbs, Visible
{
    use HasBlocks;
    use HasCover;
    use HasExtra;
    use HasSeo;
    use HasTranslations;
    use HasUrl;
    use IsCategory {
        categoryValue as sharedCategoryValue;
        writeCategoryValue as writeSharedCategoryValue;
    }
    use SoftDeletes;

    /** The screen a category is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'services.category-form';

    /** @var list<string> */
    protected $fillable = ['title', 'slug', 'lead', 'cover_id', 'is_visible', 'position', 'blocks'];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug', 'lead'];
    }

    /** A category has an address in a language when it names a slug in it (§4.10). */
    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /** Shown, and not in the bin — the handler's 404 and the sitemap's line alike. */
    public function isVisible(?string $locale = null): bool
    {
        return $this->is_visible && ! $this->trashed();
    }

    /** A category has no publication of its own: its page changes when it is saved. */
    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->updated_at;
    }

    /**
     * Index → the category (§4.5).
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        return Trail::of($locale, new Crumb((string) $this->getTranslation('title', $locale), $this->url($locale)));
    }

    /**
     * The introduction as a page prints it: the document the editor wrote, with every library
     * picture pointed at where it lives now — the document holds keys, not addresses.
     */
    public function leadHtml(?string $locale = null): string
    {
        $stored = $this->getTranslation('lead', $locale ?? app()->getLocale());

        if (! is_string($stored) || trim($stored) === '') {
            return '';
        }

        $type = app(FieldTypes::class)->get('wx-rich-text');
        $resolved = $type === null ? $stored : $type->resolve($stored, [], $locale);

        return is_string($resolved) ? $resolved : $stored;
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_category', 'category_id', 'service_id')
            ->withPivot(['position', 'item_position']);
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->services();
    }

    /**
     * What a category of services is to the code every module's categories share.
     */
    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['services.view', 'services.manage', 'services.categories.manage'],
            manage: 'services.categories.manage',
            prefix: static fn (): string => (string) config('webx-services.prefix', 'services'),
            noun: 'category',
            plural: 'categories',
            items: 'services',
        );
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /**
     * The shared fields and the ones a page of the site needs: the introduction, the picture, the
     * blocks when the site draws them, and the SEO card `module-seo` patches onto the screen.
     *
     * @return list<string>
     */
    public function categoryFields(): array
    {
        return ['title', 'slug', 'is_visible', 'lead', 'cover', 'blocks', 'seo'];
    }

    public function categoryValue(string $field): mixed
    {
        return match ($field) {
            'cover' => $this->coverValue(),
            'blocks' => $this->blocksTree(),
            'seo' => $this->seoValue(),
            default => $this->sharedCategoryValue($field),
        };
    }

    public function writeCategoryValue(string $field, mixed $value): void
    {
        match ($field) {
            'cover' => $this->cover_id = self::coverIdOf($value),
            'blocks' => $this->setAttribute('blocks', is_iterable($value) ? $this->storeBlocks($value) : null),
            // Into its own table, after the save: see categorySaved().
            'seo' => null,
            default => $this->writeSharedCategoryValue($field, $value),
        };
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function categorySaved(array $values): void
    {
        // Only when the card travelled: a save of another tab must not empty a card nobody opened.
        if (array_key_exists('seo', $values)) {
            $this->saveSeo(is_array($values['seo']) && $values['seo'] !== [] ? $values['seo'] : null);
        }
    }

    /** A category with services in it (§4.13): deleting it would leave a hole in the catalogue. */
    public function inUseMessage(int $count): string
    {
        return (string) trans('webx-services::errors.category-in-use', ['count' => $count]);
    }
}
