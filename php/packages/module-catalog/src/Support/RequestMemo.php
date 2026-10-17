<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Support;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * What one page worked out once and is asked for again before it is sent: the brands and the
 * stock of the products on it (asked by every part that prints them, and by the purchase rule),
 * the categories behind a facet's values (asked for their names, their slugs and their parents),
 * the subtree of the page's category (asked by every facet counter).
 *
 * Kept on the request rather than in a singleton: the next request is another page, and a worker
 * that serves many requests must not print the first one's brands on the second. A queue worker
 * has one request for all its jobs, so the module forgets everything here when a job starts.
 * A write in the same request — the form saving a product, a bulk action — forgets what it made
 * stale through the model's own events or the writer's call to {@see forget()}.
 */
final class RequestMemo
{
    private const PREFIX = 'webx.catalog.memo.';

    /**
     * @template T
     *
     * @param  Closure(): T  $compute
     * @return T
     */
    public static function remember(string $key, Closure $compute): mixed
    {
        $request = self::request();

        if ($request === null) {
            return $compute();
        }

        $name = self::PREFIX.$key;

        if ($request->attributes->has($name)) {
            /** @var T $known */
            $known = $request->attributes->get($name);

            return $known;
        }

        $value = $compute();
        $request->attributes->set($name, $value);

        return $value;
    }

    /**
     * The rows behind these ids, from a pool the request fills as it goes: a filter asks for the
     * names of six brands, then for the slug of each one by itself, and the pool answers the
     * six single questions from the first one. `$fetch` is asked only for the ids not in the pool
     * yet; the answer is in the order of the ids asked.
     *
     * @template TModel of Model
     *
     * @param  list<int>  $ids
     * @param  Closure(list<int>): iterable<TModel>  $fetch
     * @return list<TModel>
     */
    public static function pool(string $key, array $ids, Closure $fetch): array
    {
        $ids = array_values(array_unique($ids));
        $request = self::request();
        $name = self::PREFIX.$key;
        /** @var array<int, TModel|null> $pool null for an id that was asked and not found */
        $pool = $request instanceof Request && is_array($request->attributes->get($name)) ? $request->attributes->get($name) : [];
        $missing = array_values(array_filter($ids, static fn (int $id): bool => ! array_key_exists($id, $pool)));

        if ($missing !== []) {
            foreach ($missing as $id) {
                $pool[$id] = null;
            }

            foreach ($fetch($missing) as $model) {
                $pool[(int) $model->getKey()] = $model;
            }

            $request?->attributes->set($name, $pool);
        }

        $found = [];

        foreach ($ids as $id) {
            if ($pool[$id] !== null) {
                $found[] = $pool[$id];
            }
        }

        return $found;
    }

    /** Put a value under a key, over whatever was there — for a memo the caller grows as it goes. */
    public static function put(string $key, mixed $value): void
    {
        self::request()?->attributes->set(self::PREFIX.$key, $value);
    }

    /** Forget what was remembered under keys starting with the prefix — everything, by default. */
    public static function forget(string $prefix = ''): void
    {
        $request = self::request();

        if ($request === null) {
            return;
        }

        foreach (array_keys($request->attributes->all()) as $name) {
            if (str_starts_with((string) $name, self::PREFIX.$prefix)) {
                $request->attributes->remove((string) $name);
            }
        }
    }

    /**
     * A key for a set of ids, whatever their order.
     *
     * @param  list<int|string>  $ids
     */
    public static function ids(array $ids): string
    {
        $ids = array_map('intval', $ids);
        sort($ids);

        return md5(implode(',', $ids));
    }

    private static function request(): ?Request
    {
        $app = Container::getInstance();

        if (! $app->bound('request')) {
            return null;
        }

        $request = $app->make('request');

        return $request instanceof Request ? $request : null;
    }
}
