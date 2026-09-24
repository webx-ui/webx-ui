<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Panel;

use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Recipes\Http\Resources\RecipeResource;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Seo\Fields;

/**
 * The editor's screen on the server side: what its fields hold, and what a save writes (§5.9,
 * §5.10).
 *
 * The screen is `recipes.form`, keyed by field name. The nutrition is five fields with literal
 * dotted names — `nutrition.calories` is one name, not a path (CLAUDE.md §4) — which this class
 * folds into the one column and unfolds back. The services and the similar recipes are
 * `wx-relations`, sorted out of the values by {@see ScreenRecord} itself; the SEO card is
 * `module-seo`'s; everything nobody here names is the project's and goes into `extra`.
 */
final class RecipeForm
{
    /** The prefix of the five nutrition fields on the screen. */
    public const NUTRITION = 'nutrition.';

    /**
     * The recipe's own text.
     *
     * @var list<string>
     */
    private const OWN = ['title', 'slug', 'lead', 'gallery', 'ingredients', 'method', 'total_minutes', 'servings'];

    /**
     * The screen's fields that are not the text: the two kinds of category, and the SEO card.
     *
     * @var list<string>
     */
    private const TAKEN = ['categories', 'nutrients', Fields::SCREEN];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly RecipeWriter $writer,
    ) {}

    /**
     * A recipe and everything its editor needs around it (§5.10): the record, the values of the
     * screen, the revision those values are, the prefix of its address and a link to the draft —
     * minted per response, because it is signed and short-lived. No link without `module-blocks`,
     * which draws previews.
     *
     * @return array<string, mixed>
     */
    public function describe(Recipe $recipe, ?int $adminId = null): array
    {
        return [
            'recipe' => new RecipeResource($recipe),
            'values' => $this->values($recipe),
            'revision' => Revision::of($recipe),
            'prefix' => (string) config('webx-recipes.prefix', 'recipes'),
            'preview_url' => class_exists(Preview::class) ? Preview::url($recipe, $adminId) : null,
        ];
    }

    /**
     * What the form opens with: the draft laid over the columns — what the editor was last
     * working on. The categories, the nutrients and the relations wait in the draft too.
     *
     * @return array<string, mixed>
     */
    public function values(Recipe $recipe): array
    {
        $shown = $recipe->hasDraft() ? $recipe->withDraft() : $recipe;

        $values = [
            // The project's fields first, so that none of them can stand in for one of the
            // recipe's own.
            ...($shown->extraRaw() ?? []),
            'title' => $shown->getTranslations('title'),
            'slug' => $shown->getTranslations('slug'),
            'lead' => $shown->getTranslations('lead'),
            'gallery' => is_array($shown->gallery) ? $shown->gallery : [],
            'ingredients' => $shown->getTranslations('ingredients'),
            'method' => $shown->getTranslations('method'),
            'total_minutes' => $shown->total_minutes,
            'servings' => $shown->servings,
        ];

        $nutrition = is_array($shown->nutrition) ? $shown->nutrition : [];

        foreach (Recipe::NUTRITION as $key) {
            $values[self::NUTRITION.$key] = $nutrition[$key] ?? [];
        }

        return [
            ...$values,
            'categories' => $recipe->draftedCategoryIds('categories'),
            'nutrients' => $recipe->draftedCategoryIds('nutrients'),
            ...$this->record->relationValues(Recipe::SCREEN, $recipe),
            Fields::SCREEN => $recipe->seoValue(),
        ];
    }

    /**
     * Check what came in against the screen and write it — the panel's door and an agent's alike.
     * The caller holds the transaction: a refusal half way must not leave half a save.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Recipe $recipe, array $input, ?callable $can = null, ?int $authorId = null): Recipe
    {
        $own = [...self::OWN];

        foreach (Recipe::NUTRITION as $key) {
            $own[] = self::NUTRITION.$key;
        }

        $split = $this->record->split(Recipe::SCREEN, $input, $own, self::TAKEN, $can);
        $stored = [...$split->own, ...$split->taken];

        $columns = [];

        // A field a project patched onto the screen goes into `extra`, and into the draft with the
        // text around it. Laid over what the editor is looking at, so a tab nobody opened keeps
        // its fields.
        if ($split->extra !== []) {
            $columns['extra'] = $this->record->merge(Recipe::SCREEN, $this->currentExtra($recipe), $split->extra);
        }

        foreach ($split->own as $field => $value) {
            if (str_starts_with($field, self::NUTRITION)) {
                $columns['nutrition'][substr($field, strlen(self::NUTRITION))] = $value;

                continue;
            }

            $columns[$field] = $field === 'gallery' && ! is_array($value) ? [] : $value;
        }

        $this->writer->save(
            $recipe,
            $columns,
            $this->ids($stored, 'categories'),
            $this->ids($stored, 'nutrients'),
            $authorId,
        );

        $this->record->saveRelations($recipe, $split);

        // Only when it travelled — a save of another tab must not empty a card nobody opened.
        if (array_key_exists(Fields::SCREEN, $stored)) {
            $value = $stored[Fields::SCREEN];

            $recipe->saveSeo(is_array($value) ? $value : null);
        }

        return $recipe->refresh();
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return list<int>|null
     */
    private function ids(array $stored, string $field): ?array
    {
        if (! array_key_exists($field, $stored)) {
            return null;
        }

        return is_array($stored[$field]) ? array_values(array_map(intval(...), $stored[$field])) : [];
    }

    /**
     * The project's fields as the editor last left them.
     *
     * @return array<string, mixed>|null
     */
    private function currentExtra(Recipe $recipe): ?array
    {
        $draft = $recipe->draftValues();

        if (array_key_exists('extra', $draft)) {
            return is_array($draft['extra']) ? $draft['extra'] : null;
        }

        return $recipe->extraRaw();
    }
}
