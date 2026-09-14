<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;
use WebxUi\Localization\Locales;

/**
 * The write side of a screen, and the read side for the site.
 *
 * Saving by description: the same tree that was drawn decides what is accepted. A key the tree
 * does not name is dropped, a node the administrator may not see is closed for writing, and
 * every value is checked with the rules its type declares — per language when the field is
 * localized.
 *
 * @phpstan-type Node array<string, mixed>
 */
final class ScreenValues
{
    public function __construct(
        private readonly ScreenRegistry $screens,
        private readonly FieldTypes $types,
        private readonly Locales $locales,
        private readonly ValidationFactory $validator,
    ) {}

    /**
     * Checks and casts what came in. Keys are literal — `seo.robots-txt` is one key, not a
     * path — so each value is validated on its own rather than through one nested rule set.
     * Only keys that were sent are touched: a hidden field that did not travel is left alone.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     * @return array<string, mixed> What to store, keyed by field name.
     *
     * @throws ValidationException
     */
    public function validate(string $screen, array $input, ?callable $can = null): array
    {
        $root = $this->screens->tree($screen);

        if ($can !== null) {
            $root = Tree::filter($root, $can);
        }

        $stored = [];
        $errors = [];

        foreach (Tree::fields($root) as $node) {
            /** @var string $name */
            $name = $node['name'];

            if (! array_key_exists($name, $input)) {
                continue;
            }

            $value = $input[$name];
            $type = $this->types->get((string) $node['type']);
            $rules = $type?->rules($node) ?? [];
            $label = $this->label($node);
            $localized = ($node['localized'] ?? false) === true;

            if ($localized) {
                $value = $this->localeKeys($value);
                $validator = $this->validator->make(
                    ['value' => $value],
                    ['value' => ['nullable', 'array'], 'value.*' => $rules],
                    [],
                    ['value' => $label, 'value.*' => $label],
                );
            } else {
                $validator = $this->validator->make(['value' => $value], ['value' => $rules], [], ['value' => $label]);
            }

            if ($validator->fails()) {
                $errors[$name] = array_values(array_unique($validator->errors()->all()));

                continue;
            }

            $stored[$name] = $localized && is_array($value)
                ? array_map(static fn (mixed $one): mixed => $type?->store($one, $node) ?? $one, $value)
                : ($type?->store($value, $node) ?? $value);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $stored;
    }

    /**
     * What the site reads for one field: the current language of a localized value, with the
     * usual fallbacks, then whatever the type makes of it.
     *
     * @param  Node  $node
     */
    public function resolve(array $node, mixed $stored, ?string $locale = null): mixed
    {
        $type = $this->types->get((string) ($node['type'] ?? ''));

        if (($node['localized'] ?? false) === true && is_array($stored)) {
            $stored = $this->pick($stored, $locale);
        }

        return $type?->resolve($stored, $node) ?? $stored;
    }

    /**
     * The whole screen for the site, keyed by field name, as `resolve` sees it.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public function resolveAll(string $screen, array $stored, ?string $locale = null): array
    {
        $resolved = [];

        foreach ($this->screens->fields($screen) as $node) {
            /** @var string $name */
            $name = $node['name'];
            $resolved[$name] = $this->resolve($node, $stored[$name] ?? null, $locale);
        }

        return $resolved;
    }

    /**
     * Keeps only the site's content languages: a key that is not one is not a language the
     * site publishes in, and there is nothing to do with words in it.
     */
    private function localeKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return array_intersect_key($value, array_flip($this->locales->codes()));
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function pick(array $translations, ?string $locale): mixed
    {
        foreach ($this->locales->chain($locale) as $code) {
            $candidate = $translations[$code] ?? null;

            if ($candidate !== null && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  Node  $node
     */
    private function label(array $node): string
    {
        $label = $node['label'] ?? $node['name'] ?? '';
        $translated = Tree::translate($label, static fn (string $key): string => (string) __($key));

        return is_string($translated) ? $translated : (string) $node['name'];
    }
}
