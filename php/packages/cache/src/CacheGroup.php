<?php

declare(strict_types=1);

namespace WebxUi\Cache;

use Closure;

/**
 * One group of cached data: a name, the models whose every save moves its version, and what
 * else to do when it moves. Declared by the package that owns the data (WEBX_UI_CACHE.md §2).
 */
final class CacheGroup
{
    /** @var list<class-string> */
    private array $models = [];

    /** @var list<Closure(): void> */
    private array $hooks = [];

    public function __construct(
        public readonly string $id,
        /** A translation key, or the words themselves; the panel shows it beside the id. */
        private ?string $title = null,
    ) {}

    public function title(): string
    {
        if ($this->title === null) {
            return $this->id;
        }

        $words = __($this->title);

        return is_string($words) ? $words : $this->id;
    }

    /** The title stays the first one given: the owner names the group, a borrower only uses it. */
    public function entitle(?string $title): void
    {
        $this->title ??= $title;
    }

    /**
     * @param  class-string  $model
     * @return bool whether it was new to the group
     */
    public function addModel(string $model): bool
    {
        if (in_array($model, $this->models, true)) {
            return false;
        }

        $this->models[] = $model;

        return true;
    }

    /** @return list<class-string> */
    public function models(): array
    {
        return $this->models;
    }

    /** @param  Closure(): void  $hook */
    public function addHook(Closure $hook): void
    {
        $this->hooks[] = $hook;
    }

    /** @return list<Closure(): void> */
    public function hooks(): array
    {
        return $this->hooks;
    }
}
