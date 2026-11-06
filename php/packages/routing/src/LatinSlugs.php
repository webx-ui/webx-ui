<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use WebxUi\Localization\Slug;
use WebxUi\Localization\SlugModels;
use WebxUi\Routing\Contracts\AddressRedirects;
use WebxUi\Routing\Models\Route;

/**
 * The slugs saved before the rule: finding them and moving them, addresses and all.
 *
 * `HasLatinSlug` looks after what is being written; this is for what already lies in the tables.
 * The table is read raw: a model's own read would answer with a fallback language, and a value
 * that is not there is not a slug to move.
 *
 * @phpstan-type Found array{model: class-string<Model>, id: int|string, locale: string|null, from: string, to: string}
 */
final class LatinSlugs
{
    public function __construct(
        private readonly RouteTypes $types,
        private readonly SlugModels $models,
    ) {}

    /**
     * Every slug still not in ASCII, one entry a language. `to` is before any suffix: which one
     * is free is known only to the save that takes it.
     *
     * @return Generator<int, Found>
     */
    public function found(): Generator
    {
        foreach ($this->all() as $class) {
            $model = new $class;

            if (! method_exists($model, 'latinSlugAttribute') || ! Schema::hasTable($model->getTable())) {
                continue;
            }

            $attribute = $model->latinSlugAttribute();
            $key = $model->getKeyName();

            foreach ($model->newQueryWithoutScopes()->toBase()->select([$key, $attribute])->orderBy($key)->lazyById(500, $key) as $row) {
                $row = (array) $row;

                foreach ($this->values($model, $attribute, $row[$attribute] ?? null) as $locale => $slug) {
                    if ($slug === '' || Slug::isLatin($slug)) {
                        continue;
                    }

                    $locale = is_string($locale) ? $locale : null;
                    $to = Slug::latin($slug, $locale ?? 'en');

                    yield [
                        'model' => $class,
                        'id' => $row[$key],
                        'locale' => $locale,
                        'from' => $slug,
                        'to' => $to !== '' ? $to : $this->fallback($model, $row[$key]),
                    ];
                }
            }
        }
    }

    /** How many slugs are still to move. */
    public function count(): int
    {
        return iterator_count($this->found());
    }

    /**
     * Moves one record's slugs.
     *
     * Through a real save, so that the registry leaves its aliases and a tree follows; the
     * addresses are compared around it, so a child that moved with its parent gets its redirect
     * too. A record in the bin has no address to move: only its slug changes.
     *
     * @param  class-string<Model>  $class
     * @return array{moved: array<string, string>, refused: string|null}
     */
    public function move(string $class, int|string $id): array
    {
        $entity = (new $class)->newQueryWithoutScopes()->findOrFail($id);
        $before = $this->addresses($entity);

        if (method_exists($entity, 'latinizeSlug')) {
            $entity->latinizeSlug();
        }

        try {
            $this->trashed($entity) ? $entity->saveQuietly() : $entity->save();
        } catch (ValidationException $refused) {
            return ['moved' => [], 'refused' => $refused->getMessage()];
        }

        $after = $this->addresses($entity);
        $moved = [];

        foreach ($before as $key => $from) {
            $to = $after[$key] ?? null;

            if ($to !== null && $to !== $from) {
                $moved[$from] = $to;
            }
        }

        return ['moved' => $moved, 'refused' => null];
    }

    /**
     * Writes the redirects of what moved. False when the site has nobody to keep them.
     *
     * @param  array<string, string>  $moved
     */
    public function redirect(array $moved): bool
    {
        $container = Container::getInstance();

        if (! $container->bound(AddressRedirects::class)) {
            return false;
        }

        $redirects = $container->make(AddressRedirects::class);

        foreach ($moved as $from => $to) {
            $redirects->moved($from, $to);
        }

        return true;
    }

    /** @return list<class-string<Model>> */
    private function all(): array
    {
        $routed = array_map(static fn (RouteType $type): string => $type->model, array_values($this->types->all()));

        return array_values(array_unique([...$routed, ...$this->models->all()]));
    }

    /**
     * @return array<int|string, string>
     */
    private function values(Model $model, string $attribute, mixed $raw): array
    {
        if (! is_string($raw)) {
            return [];
        }

        if (! method_exists($model, 'isTranslatableAttribute') || ! $model->isTranslatableAttribute($attribute)) {
            return [$raw];
        }

        $values = json_decode($raw, true);

        return is_array($values) ? array_filter($values, is_string(...)) : [];
    }

    /**
     * Every live address of the entity and of whatever hangs below it, as a browser asks for it:
     * percent-encoded, which is what a redirect is compared with.
     *
     * @return array<string, string>
     */
    private function addresses(Model $entity): array
    {
        if (! method_exists($entity, 'routeCanonical')) {
            return [];
        }

        $addresses = [];
        $below = method_exists($entity, 'descendants') ? $entity->descendants()->get()->all() : [];

        foreach ([$entity, ...$below] as $one) {
            foreach (Route::query()->forEntity($one)->where('kind', Route::CANONICAL)->pluck('locale') as $locale) {
                $path = (string) parse_url($one->url((string) $locale), PHP_URL_PATH);
                $addresses[$one->getKey().':'.$locale] = implode('/', array_map(
                    static fn (string $part): string => rawurlencode(rawurldecode($part)),
                    explode('/', $path),
                ));
            }
        }

        return $addresses;
    }

    private function fallback(Model $model, int|string $id): string
    {
        return method_exists($model, 'latinSlugFallback') ? $model->latinSlugFallback().'-'.$id : (string) $id;
    }

    private function trashed(Model $entity): bool
    {
        return method_exists($entity, 'trashed') && $entity->trashed();
    }
}
