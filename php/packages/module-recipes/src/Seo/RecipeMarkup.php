<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Seo;

use DOMDocument;
use DOMElement;
use DOMXPath;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Seo\Panel\DefaultsSource;

/**
 * schema.org `Recipe` for a recipe page (§5.7) — the markup Google shows as a rich result to any
 * site, unlike a FAQ.
 *
 * The ingredients and the method are documents the editor writes, not lists of fields (decision
 * 6), so what goes into `recipeIngredient` and `recipeInstructions` is read out of them: every
 * `<li>` is one item. A document with no list is taken paragraph by paragraph instead — an
 * editor who ignored the hint under the field still gets markup, just coarser.
 */
final class RecipeMarkup
{
    /** schema.org's names for the nutrition keys, in the order they are printed. */
    private const NUTRITION = [
        'calories' => 'calories',
        'protein' => 'proteinContent',
        'fat' => 'fatContent',
        'carbohydrates' => 'carbohydrateContent',
        'fiber' => 'fiberContent',
    ];

    public function __construct(private readonly DefaultsSource $defaults) {}

    /**
     * @return array<string, mixed>
     */
    public function of(Recipe $recipe, string $locale): array
    {
        $markup = [
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => (string) $recipe->getTranslation('title', $locale),
            'url' => $recipe->url($locale),
        ];

        $lead = $recipe->getTranslation('lead', $locale);

        if (is_string($lead) && trim($lead) !== '') {
            $markup['description'] = trim($lead);
        }

        $images = array_values(array_filter(array_map(
            static fn (array $picture): ?string => is_string($picture['url'] ?? null) ? $picture['url'] : null,
            $recipe->pictures($locale),
        )));

        if ($images !== []) {
            $markup['image'] = $images;
        }

        if ($recipe->total_minutes !== null && $recipe->total_minutes > 0) {
            $markup['totalTime'] = self::duration($recipe->total_minutes);
        }

        if ($recipe->servings !== null && $recipe->servings > 0) {
            $markup['recipeYield'] = (string) $recipe->servings;
        }

        $category = $recipe->mainRecipeCategory();

        if ($category instanceof RecipeCategory) {
            $name = $category->getTranslation('title', $locale);

            if (is_string($name) && trim($name) !== '') {
                $markup['recipeCategory'] = $name;
            }
        }

        $ingredients = self::items($recipe->html('ingredients', $locale));

        if ($ingredients !== []) {
            $markup['recipeIngredient'] = $ingredients;
        }

        $steps = self::items($recipe->html('method', $locale));

        if ($steps !== []) {
            $markup['recipeInstructions'] = array_map(
                static fn (string $step): array => ['@type' => 'HowToStep', 'text' => $step],
                $steps,
            );
        }

        $nutrition = [];

        foreach ($recipe->nutrition($locale) as $key => $value) {
            $nutrition[self::NUTRITION[$key]] = $value;
        }

        if ($nutrition !== []) {
            $markup['nutrition'] = ['@type' => 'NutritionInformation', ...$nutrition];
        }

        $organisation = $this->defaults->organizationId($locale);

        if ($organisation !== null) {
            $markup['author'] = ['@id' => $organisation];
        }

        return $markup;
    }

    /** `PT45M`, `PT1H15M`, `PT2H` — ISO 8601, which is what `totalTime` takes. */
    public static function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return 'PT'.($hours > 0 ? $hours.'H' : '').($rest > 0 || $hours === 0 ? $rest.'M' : '');
    }

    /**
     * The items of a document as plain text: every `<li>`, or with no list every paragraph.
     *
     * @return list<string>
     */
    public static function items(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = new DOMDocument;
        // The encoding declaration, or libxml reads the bytes as Latin-1 and every Cyrillic letter
        // comes out as two.
        @$document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($document);

        foreach (['//li', '//p'] as $query) {
            $found = [];
            $nodes = $xpath->query($query);

            foreach ($nodes === false ? [] : $nodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                // A nested list is its own items: the outer one keeps only its own words.
                $text = self::text($node);

                if ($text !== '') {
                    $found[] = $text;
                }
            }

            if ($found !== []) {
                return $found;
            }
        }

        return [];
    }

    private static function text(DOMElement $node): string
    {
        $copy = $node->cloneNode(true);

        if ($copy instanceof DOMElement) {
            foreach (iterator_to_array($copy->getElementsByTagName('ul')) as $inner) {
                $inner->parentNode?->removeChild($inner);
            }

            foreach (iterator_to_array($copy->getElementsByTagName('ol')) as $inner) {
                $inner->parentNode?->removeChild($inner);
            }
        }

        return trim((string) preg_replace('/\s+/u', ' ', $copy->textContent));
    }
}
