<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/**
 * A group of the site as `MethodGroups` merged it (groups spec §2.1): the config's words under the
 * manager's edits. Immutable, built from the config and the cached rows, so reading it costs no query.
 */
final readonly class MethodGroup
{
    /**
     * @param  string  $configTitle  a key of a dictionary, or the words themselves
     * @param  array<string, string>  $titles  the manager's, by language
     * @param  array<string, string>  $descriptions  the manager's, by language
     * @param  string|null  $pictogram  a name of the set; null — the group goes without one
     * @param  int|null  $position  the manager's order; null — after those, as in the config
     */
    public function __construct(
        public string $key,
        public string $configTitle,
        public array $titles = [],
        public array $descriptions = [],
        public ?string $pictogram = null,
        public ?int $position = null,
    ) {}

    /** What stands for the group where the site has none: nobody reads it, the list is flat. */
    public static function none(): self
    {
        return new self('', '');
    }

    /**
     * The manager's title in exactly this language, else the config's: unlike a method's title it
     * does not walk the fallback chain, because the config's title speaks every language itself.
     */
    public function title(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->titles[$locale] ?? $this->configWords($locale);
    }

    /** What the config calls the group: the title a manager's empty field falls back to. */
    public function configWords(?string $locale = null): string
    {
        $words = trans($this->configTitle, [], $locale ?? app()->getLocale());

        return is_string($words) ? $words : $this->key;
    }

    /** The line under the title; the config has none, so null is what most groups say. */
    public function description(?string $locale = null): ?string
    {
        return Words::pick($this->descriptions, $locale);
    }
}
