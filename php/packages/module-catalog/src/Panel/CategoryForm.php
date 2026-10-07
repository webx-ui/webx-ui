<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Exceptions\CatalogException;
use WebxUi\Catalog\Facets\CategoryFacets;
use WebxUi\Catalog\Http\Resources\CategoryResource;
use WebxUi\Catalog\Models\Category;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Seo\Fields;

/**
 * The category editor on the server side (§6, §11.1).
 *
 * The slug is checked twice over, by two owners. Its shape here: no `_`, because `_` is what tells
 * a filter segment from a category in an address (§8.1 of the architecture). Whether it is free,
 * by `routing` on save: a slug held by a page or another category is a 422 naming the holder.
 *
 * Publishing and unpublishing a category changes what is visible under it, so it marks every
 * product of the branch for the index in one statement ({@see Catalog::touchCategory()}).
 */
final class CategoryForm
{
    private const OWN = ['name', 'slug', 'description', 'cover', 'is_published'];

    private const TAKEN = ['facets', Fields::SCREEN];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly Catalog $catalog,
        private readonly Locales $locales,
        private readonly MediaFiles $media,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function describe(Category $category): array
    {
        $category->loadMissing('routes');

        return [
            'category' => new CategoryResource($category),
            'values' => $this->values($category),
            'facets_from' => $this->facetsFrom($category),
        ];
    }

    /**
     * Whose facet settings a category without its own shows (§6.2): the nearest configured
     * ancestor, or null for "every facet by default" — and null as well when it has its own.
     *
     * @return array{id: int, name: string}|null
     */
    private function facetsFrom(Category $category): ?array
    {
        if (FacetSettings::of($category) !== null) {
            return null;
        }

        $from = app(CategoryFacets::class)->resolve($category)['from'];
        $owner = $from === null ? null : Category::withTrashed()->find($from);

        return $owner instanceof Category ? ['id' => (int) $owner->id, 'name' => $owner->displayName()] : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function values(Category $category): array
    {
        return [
            'name' => $category->getTranslations('name'),
            'slug' => $category->getTranslations('slug'),
            'description' => $category->getTranslations('description'),
            'cover' => $this->cover($category->cover_id),
            'is_published' => $category->is_published,
            'facets' => FacetSettings::of($category),
            Fields::SCREEN => $category->seoValue(),
        ];
    }

    /**
     * A new category, at the end of its parent's children — or of the top level.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     */
    public function create(array $input, ?int $parentId, ?callable $can = null): Category
    {
        $parent = null;

        if ($parentId !== null) {
            $parent = Category::query()->find($parentId);

            if (! $parent instanceof Category) {
                throw CatalogException::unknownCategory('parent_id');
            }
        }

        return $this->write(new Category, $input, $can, $parent);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     */
    public function save(Category $category, array $input, ?callable $can = null): Category
    {
        return $this->write($category, $input, $can);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     */
    private function write(Category $category, array $input, ?callable $can, ?Category $parent = null): Category
    {
        $split = $this->record->split(Category::SCREEN, $input, self::OWN, self::TAKEN, $can);
        $own = $split->own;
        $taken = $split->taken;

        DB::transaction(function () use ($category, $own, $taken, $parent): void {
            $created = ! $category->exists;
            $wasPublished = (bool) $category->getOriginal('is_published');

            $this->fill($category, $own);
            $this->fillSlugs($category);
            $this->assertShape($category);

            if ($created) {
                $parent === null ? $category->saveAsRoot() : $category->appendTo($parent);
                $category->refresh();
            } else {
                $category->save();
            }

            $changes = $created ? [] : $category->takeHistoryChanges();

            if (array_key_exists('facets', $taken)) {
                $facets = $taken['facets'];
                $change = FacetSettings::write($category, is_array($facets) ? $facets : null);

                if ($change !== null) {
                    $changes[] = $change;
                }
            }

            if (array_key_exists(Fields::SCREEN, $taken)) {
                $value = $taken[Fields::SCREEN];
                $category->saveSeo(is_array($value) ? $value : null);
            }

            $published = $category->is_published;

            if ($published !== $wasPublished) {
                $this->catalog->touchCategory($category);
            }

            if ($created) {
                return;
            }

            $category->recordHistory(match (true) {
                $published && ! $wasPublished => HistoryEntry::PUBLISHED,
                ! $published && $wasPublished => HistoryEntry::UNPUBLISHED,
                default => HistoryEntry::UPDATED,
            }, $changes);
        });

        return $category->refresh();
    }

    /**
     * @param  array<string, mixed>  $own
     */
    private function fill(Category $category, array $own): void
    {
        foreach ($own as $field => $value) {
            if ($field === 'cover') {
                $category->cover_id = $this->coverId($value);

                continue;
            }

            if ($category->isTranslatableAttribute($field)) {
                is_array($value)
                    ? $category->setTranslations($field, [...$category->getTranslations($field), ...$value])
                    : $category->setTranslation($field, $this->locales->content(), $value);

                continue;
            }

            $category->setAttribute($field, $field === 'is_published' ? (bool) $value : $value);
        }
    }

    /**
     * A slug in every language that has a name and no slug: the editor may write their own, and
     * the address is the name until they do.
     */
    private function fillSlugs(Category $category): void
    {
        $slugs = $category->getTranslations('slug');

        foreach ($category->getTranslations('name') as $locale => $name) {
            $current = $slugs[$locale] ?? null;

            if ((! is_string($current) || trim($current) === '') && is_string($name) && trim($name) !== '') {
                $slugs[$locale] = str($name)->slug('-', (string) $locale)->toString();
            }
        }

        $category->setTranslations('slug', $slugs);
    }

    /**
     * @throws ValidationException
     */
    private function assertShape(Category $category): void
    {
        $named = false;

        foreach ($category->getTranslations('name') as $name) {
            $named = $named || (is_string($name) && trim($name) !== '');
        }

        if (! $named) {
            throw ValidationException::withMessages(['name' => [(string) __('webx-catalog::errors.name-required')]]);
        }

        foreach ($category->getTranslations('slug') as $slug) {
            if (is_string($slug) && str_contains($slug, '_')) {
                throw CatalogException::slugHasUnderscore();
            }
        }
    }

    private function coverId(mixed $value): ?int
    {
        $path = is_array($value) ? ($value['path'] ?? null) : null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        $file = $this->media->find($path);

        return $file === null ? null : (int) $file->getKey();
    }

    /**
     * @return array{path: string}|null
     */
    private function cover(?int $id): ?array
    {
        $file = $id === null ? null : MediaFile::query()->find($id);

        return $file instanceof MediaFile ? ['path' => $file->path] : null;
    }
}
