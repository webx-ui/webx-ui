<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Localization\Locales;

/**
 * `wx-repeater`: a list of records, each edited with the node's children.
 *
 * It is the one type whose model is nested, so it is the one type that has to do to its items
 * what {@see ScreenValues} does to a screen: check every value with the
 * rules its own type declares, per language where the child is localized, keep only the keys the
 * tree names, and resolve the lot for the site.
 *
 * A row that fails says which row it was, because the whole list arrives under one field name and
 * "The City field is required" on its own tells nobody which city.
 *
 * @phpstan-type Node array<string, mixed>
 */
final class RepeaterType implements FieldType
{
    public function __construct(
        private readonly FieldTypes $types,
        private readonly Locales $locales,
        private readonly ValidationFactory $validator,
    ) {}

    /**
     * @param  Node  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $max = $node['props']['max'] ?? null;

        $rules = ['nullable', 'array'];

        if (is_int($max) || (is_string($max) && ctype_digit($max))) {
            $rules[] = 'max:'.(int) $max;
        }

        $rules[] = function (string $attribute, mixed $value, Closure $fail) use ($node): void {
            if (! is_array($value)) {
                return;
            }

            foreach (array_values($value) as $position => $item) {
                if (! is_array($item)) {
                    $fail($this->rowMessage($position, __('webx-admin::screens.row-shape')));

                    continue;
                }

                foreach ($this->itemFields($node) as $child) {
                    foreach ($this->messages($child, $item) as $message) {
                        $fail($this->rowMessage($position, $message));
                    }
                }
            }
        };

        return $rules;
    }

    /**
     * Keeps the keys the children name and nothing else, each cast by its own type — the same
     * bargain the screen makes with its own values.
     *
     * @param  Node  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        $items = [];

        foreach (array_values($value) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $stored = [];

            foreach ($this->itemFields($node) as $child) {
                /** @var string $name */
                $name = $child['name'];

                if (! array_key_exists($name, $item)) {
                    continue;
                }

                $stored[$name] = $this->each(
                    $child,
                    $item[$name],
                    fn (mixed $one): mixed => $this->types->get((string) $child['type'])?->store($one, $child) ?? $one,
                );
            }

            $items[] = $stored;
        }

        return $items;
    }

    /**
     * @param  Node  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        if (! is_array($stored)) {
            return [];
        }

        $items = [];

        foreach (array_values($stored) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $resolved = [];

            foreach ($this->itemFields($node) as $child) {
                /** @var string $name */
                $name = $child['name'];
                $value = $item[$name] ?? null;

                if (($child['localized'] ?? false) === true && is_array($value)) {
                    $value = $this->pick($value, $locale);
                }

                $resolved[$name] = $this->types->get((string) $child['type'])?->resolve($value, $child, $locale) ?? $value;
            }

            $items[] = $resolved;
        }

        return $items;
    }

    /**
     * The fields of one item. `Tree::fields` stops at a named node, so a repeater nested in a
     * repeater stays one field here rather than spilling its own children into this level.
     *
     * @param  Node  $node
     * @return list<Node>
     */
    private function itemFields(array $node): array
    {
        return Tree::fields(Tree::children($node));
    }

    /**
     * What is wrong with one value of one item, in words — empty when nothing is.
     *
     * @param  Node  $child
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function messages(array $child, array $item): array
    {
        /** @var string $name */
        $name = $child['name'];

        if (! array_key_exists($name, $item)) {
            return [];
        }

        $rules = $this->types->get((string) $child['type'])?->rules($child) ?? [];

        if ($rules === []) {
            return [];
        }

        $label = $this->label($child);
        $value = $item[$name];

        $validator = ($child['localized'] ?? false) === true
            ? $this->validator->make(
                ['value' => $this->localeKeys($value)],
                ['value' => ['nullable', 'array'], 'value.*' => $rules],
                [],
                ['value' => $label, 'value.*' => $label],
            )
            : $this->validator->make(['value' => $value], ['value' => $rules], [], ['value' => $label]);

        return $validator->fails() ? array_values(array_unique($validator->errors()->all())) : [];
    }

    /**
     * Runs a cast over one value, or over every language of a localized one.
     *
     * @param  Node  $child
     * @param  callable(mixed): mixed  $cast
     */
    private function each(array $child, mixed $value, callable $cast): mixed
    {
        if (($child['localized'] ?? false) !== true) {
            return $cast($value);
        }

        $value = $this->localeKeys($value);

        return is_array($value) ? array_map($cast, $value) : $cast($value);
    }

    /**
     * Only the site's content languages; a key that is not one is not a language it publishes in.
     */
    private function localeKeys(mixed $value): mixed
    {
        return is_array($value) ? array_intersect_key($value, array_flip($this->locales->codes())) : $value;
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

    private function rowMessage(int $position, string $message): string
    {
        return (string) __('webx-admin::screens.row', [
            'number' => $position + 1,
            'message' => $message,
        ]);
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
