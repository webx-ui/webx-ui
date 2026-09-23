<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\Http\CategoryResource;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Admin\Screens\Types\SlugType;
use WebxUi\Localization\Locales;

/**
 * A category as its screen reads and saves it — the one path for the panel and for an agent.
 *
 * The screen is the module's (`blog.category-form`), so what a category is made of is decided by
 * the description: the module's own fields are {@see IsCategory::categoryFields()}, and a field a
 * project patched in goes into `extra` rather than nowhere. There is no draft — a category is
 * navigation, and a save is on the site at once.
 */
final class CategoryForm
{
    public function __construct(
        private readonly ScreenRecord $record,
        private readonly Locales $locales,
        private readonly ValidationFactory $validator,
    ) {}

    /**
     * A new category from a name, and an address made out of it where none was given.
     *
     * `Str::slug` transliterates, so a Russian title gives a Latin address rather than a
     * percent-encoded one — what a site wants and what nobody wants to type by hand. Whether the
     * address is free is not asked here: only the registry sees every kind of page at once, and
     * it answers by refusing the save with a 422 under `slug`.
     *
     * @param  class-string<Model&Category>  $model
     * @return Model&Category
     *
     * @throws ValidationException
     */
    public function create(string $model, mixed $title, mixed $slug = null): Model
    {
        $title = $this->map($title);
        $slug = $this->withAddresses($this->map($slug), $title);

        $this->validator->make(
            ['title' => $title, 'slug' => $slug],
            [
                'title' => ['required', 'array', 'min:1'],
                'title.*' => ['nullable', 'string', 'max:255'],
                'slug' => ['array'],
                'slug.*' => SlugType::checks(),
            ],
        )->validate();

        /** @var Model&Category $category */
        $category = new $model;
        $category->setAttribute('title', $title);
        $category->setAttribute('slug', $slug === [] ? null : $slug);
        $category->save();

        return $category;
    }

    /**
     * Everything the editor opens with: the record, the values of its screen and where its
     * addresses start.
     *
     * @param  Model&Category  $category
     * @return array<string, mixed>
     */
    public function describe(Model $category): array
    {
        $kind = $category::categoryKind();

        return [
            'category' => new CategoryResource($category),
            'values' => $this->values($category),
            // The form prints the whole address as it is typed, and a slug says nothing about
            // whether the module lives at the root of the site or under a prefix.
            'prefix' => $kind->prefix(),
        ];
    }

    /**
     * The values of the screen: the fields of the project, with the category's own over them.
     *
     * @param  Model&Category  $category
     * @return array<string, mixed>
     */
    public function values(Model $category): array
    {
        $values = method_exists($category, 'extraRaw') ? ($category->extraRaw() ?? []) : [];

        foreach ($category->categoryFields() as $field) {
            $values[$field] = $category->categoryValue($field);
        }

        return $values;
    }

    /**
     * Check what came in against the screen and write it.
     *
     * @param  Model&Category  $category
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Model $category, array $input, ?callable $can = null): Model
    {
        $screen = $category::categoryKind()->screen;
        $split = $this->record->split($screen, $input, $category->categoryFields(), [], $can);

        foreach ($split->own as $field => $value) {
            $category->writeCategoryValue($field, $value);
        }

        if (method_exists($category, 'mergeExtra')) {
            $category->mergeExtra($split->extra);
        }

        $category->save();
        $category->categorySaved($split->own);

        return $category->refresh();
    }

    /**
     * A map of languages, whatever shape the value arrived in: `useLocalized` switches itself off
     * on a site with one language, and a bare string then means that language.
     *
     * @return array<string, mixed>
     */
    private function map(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return is_string($value) && $value !== '' ? [$this->locales->defaultCode() => $value] : [];
    }

    /**
     * @param  array<string, mixed>  $slugs
     * @param  array<string, mixed>  $titles
     * @return array<string, mixed>
     */
    private function withAddresses(array $slugs, array $titles): array
    {
        foreach ($titles as $code => $title) {
            $given = $slugs[$code] ?? null;

            if ((is_string($given) && trim($given) !== '') || ! is_string($title)) {
                continue;
            }

            $made = Str::slug($title);

            if ($made !== '') {
                $slugs[$code] = $made;
            }
        }

        return $slugs;
    }
}
