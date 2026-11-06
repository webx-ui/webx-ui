<?php

declare(strict_types=1);

namespace WebxUi\Localization;

use Illuminate\Database\Eloquent\Model;

/**
 * The models with a slug that is no address, for `webx:slugs:latin` to walk.
 *
 * The ones with an address are known already, by their route type. A module with a slug of the
 * other kind — a filter's key — names the model from its provider:
 *
 *     $this->callAfterResolving(SlugModels::class, static function (SlugModels $models): void {
 *         $models->register(VacancyCategory::class);
 *     });
 */
final class SlugModels
{
    /** @var list<class-string<Model>> */
    private array $models = [];

    /**
     * @param  class-string<Model>  $model
     */
    public function register(string $model): void
    {
        if (! in_array($model, $this->models, true)) {
            $this->models[] = $model;
        }
    }

    /** @return list<class-string<Model>> */
    public function all(): array
    {
        return $this->models;
    }
}
