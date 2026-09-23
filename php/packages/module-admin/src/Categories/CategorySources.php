<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories;

use Illuminate\Database\Eloquent\Model;

/**
 * Which model's categories a `wx-categories` field on a screen is about.
 *
 * The field names its categories by the path they answer at (`"source": "blog/rubrics"`), because
 * that is the one string the panel can use: it asks that path for the list to choose from. The
 * server reads the same string here to know which table the chosen ids have to exist in — so
 * both halves are told once, in the screen description, and neither guesses.
 *
 * Filled by each module from its provider, not by `CategoryRoutes::register()`: a cached route
 * table never runs the route file, and a registry filled from it would be empty in production.
 */
final class CategorySources
{
    /** @var array<string, array{class-string<Model&Category>, string}> */
    private array $sources = [];

    /**
     * @param  string  $source  The path under the panel's API: `blog/rubrics`.
     * @param  class-string<Model&Category>  $model
     * @param  string  $unknown  What to say about an id that is not one of them, in the module's
     *                           words — "one of these rubrics no longer exists".
     */
    public function register(string $source, string $model, string $unknown = 'webx-admin::categories.unknown'): void
    {
        $this->sources[self::normalise($source)] = [$model, $unknown];
    }

    /**
     * @return class-string<Model&Category>|null
     */
    public function model(string $source): ?string
    {
        return $this->sources[self::normalise($source)][0] ?? null;
    }

    /** The key of the refusal for an id that is not there. */
    public function unknown(string $source): string
    {
        return $this->sources[self::normalise($source)][1] ?? 'webx-admin::categories.unknown';
    }

    private static function normalise(string $source): string
    {
        return trim($source, '/');
    }
}
