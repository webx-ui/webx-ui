<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\URL;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;

/**
 * An entity that has a public address.
 *
 * The trait is the whole integration: add it, register the type (`RouteTypes::register`), and
 * the registry follows every save, move and delete. What it does not do is decide the address —
 * that is the type's formatter, so that the same code answers for a save, for a preview in the
 * form and for `webx:routes:rebuild`.
 *
 *     class Page extends Model
 *     {
 *         use HasNestedSet, HasTranslations, HasUrl;
 *     }
 *
 *     $page->routePath();   // 'about/mission' — what the formatter says right now
 *     $page->routes;        // every row of the registry for this entity, aliases included
 *     $page->url();         // 'https://example.test/uk/about/mission'
 *
 * @mixin Model
 */
trait HasUrl
{
    /**
     * The events by hand rather than `static::observe()`: `observe()` builds an instance of the
     * model to ask it which events it has, and doing that while the model is still booting is a
     * `LogicException`. A closure per event costs nothing and keeps the observer a class worth
     * reading.
     */
    public static function bootHasUrl(): void
    {
        foreach (['created', 'updated', 'deleted', 'moved'] as $event) {
            static::registerModelEvent($event, static function (Model $entity) use ($event): void {
                Container::getInstance()->make(RouteObserver::class)->{$event}($entity);
            });
        }
    }

    /** Which attribute holds the slug. Override in a model that spells it differently. */
    public function routeSlugAttribute(): string
    {
        return 'slug';
    }

    /** @return MorphMany<Route, $this> */
    public function routes(): MorphMany
    {
        return $this->morphMany(Route::class, 'entity', 'entity_type', 'entity_id');
    }

    /** What the formatter says the address is — computed, not read from the registry. */
    public function routePath(?string $locale = null): string
    {
        $locale ??= $this->routeLocale();

        $types = Container::getInstance()->make(RouteTypes::class);

        return $types->forEntity($this)->formatter()->format($this, $locale);
    }

    /** The row the registry actually holds, if the entity has been saved. */
    public function routeCanonical(?string $locale = null): ?Route
    {
        return $this->routes()
            ->where('locale', $locale ?? $this->routeLocale())
            ->where('kind', Route::CANONICAL)
            ->first();
    }

    /**
     * The public address, with the language prefix when the site uses one.
     *
     * Read from the registry rather than computed: the registry is what the site answers by,
     * and after a collision was settled with a suffix the two can differ until the entity is
     * refreshed.
     */
    public function url(?string $locale = null): string
    {
        $locale ??= $this->routeLocale();
        $canonical = $this->routeCanonical($locale);
        $path = $canonical instanceof Route ? $canonical->path : $this->routePath($locale);

        return URL::to(UrlNormaliser::join($this->localePrefix($locale), $path));
    }

    private function routeLocale(): string
    {
        return Container::getInstance()->make(Locales::class)->current();
    }

    /** Empty unless the site puts the language in the path, and unless this language needs it. */
    private function localePrefix(string $locale): string
    {
        $config = Container::getInstance()->make('config');

        if ((string) $config->get('webx-localization.strategy', 'prefix') !== 'prefix') {
            return '';
        }

        $locales = Container::getInstance()->make(Locales::class);

        if ($locale === $locales->defaultCode() && ! (bool) $config->get('webx-localization.prefix_default', false)) {
            return '';
        }

        return $locale;
    }
}
