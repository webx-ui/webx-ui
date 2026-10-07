<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Server;

use Illuminate\Contracts\Container\Container;
use Throwable;
use WebxUi\Admin\Screens\ScreenRegistry;

/**
 * An empty language map goes out as `{}`, not as `[]`.
 *
 * PHP has one empty array for both, and `json_encode` writes it as a list — so a lead nobody
 * wrote yet came out as `"lead": []`, and an agent reasonably took the field for a list and
 * wrote one back. A handler cannot fix that without every module remembering to, and the
 * modules build their answers in sixty different places; so the answer is mended here, once,
 * on its way out.
 *
 * Only an empty array is ever touched, and only under a key known to hold a map: a field a
 * screen of this panel marks `localized` (of a type whose value in one language is a single
 * value — a localized list would be a list in a summary), the SEO card, and anything the tool's
 * own input schema says is an object. A real list keeps its brackets, because no list is under
 * one of those names.
 */
final class EmptyMaps
{
    /** Types whose value in one language is one value, so their localized value is a map of those. */
    private const SINGLE_VALUE_TYPES = [
        'wx-input', 'wx-textarea', 'wx-rich-text', 'wx-code-editor',
        'wx-slug', 'wx-article-slug', 'wx-category-slug',
    ];

    /** Types that keep a map whether localized or not. */
    private const MAP_TYPES = ['wx-seo'];

    /**
     * Keys that hold a map wherever a module writes them: the SEO card, and the words of an inbox
     * form's fields, which are edited in a dialog rather than on a screen.
     */
    private const ALWAYS = ['seo', 'help', 'placeholder'];

    /** @var array<string, true>|null */
    private ?array $screenKeys = null;

    public function __construct(private readonly Container $container) {}

    /**
     * @param  array<string, mixed>  $inputSchema  The tool's, when it is a tool's answer.
     */
    public function apply(mixed $result, array $inputSchema = []): mixed
    {
        if (! is_array($result) || $result === []) {
            return $result;
        }

        return $this->walk($result, [...$this->screenKeys(), ...self::schemaKeys($inputSchema)]);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  array<string, true>  $keys
     * @return array<array-key, mixed>
     */
    private function walk(array $value, array $keys): array
    {
        foreach ($value as $key => $item) {
            if (! is_array($item)) {
                continue;
            }

            if ($item === [] && is_string($key) && isset($keys[$key])) {
                $value[$key] = (object) [];

                continue;
            }

            $value[$key] = $this->walk($item, $keys);
        }

        return $value;
    }

    /**
     * Every field name the panel's screens keep as a map, read once.
     *
     * @return array<string, true>
     */
    private function screenKeys(): array
    {
        if ($this->screenKeys !== null) {
            return $this->screenKeys;
        }

        $keys = array_fill_keys(self::ALWAYS, true);

        if ($this->container->bound(ScreenRegistry::class)) {
            $screens = $this->container->make(ScreenRegistry::class);

            foreach ($screens->names() as $name) {
                try {
                    $fields = $screens->fields($name);
                } catch (Throwable) {
                    // A screen that does not build is the screen's own error to raise, on its
                    // own form; an answer is not the place to fail for it.
                    continue;
                }

                foreach ($fields as $node) {
                    $type = (string) ($node['type'] ?? '');
                    $field = $node['name'] ?? null;

                    if (! is_string($field) || $field === '') {
                        continue;
                    }

                    if (in_array($type, self::MAP_TYPES, true)
                        || (($node['localized'] ?? false) === true && in_array($type, self::SINGLE_VALUE_TYPES, true))) {
                        $keys[$field] = true;
                    }
                }
            }
        }

        return $this->screenKeys = $keys;
    }

    /**
     * The properties a JSON Schema says are objects and never lists, at any depth.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, true>
     */
    private static function schemaKeys(array $schema): array
    {
        $keys = [];

        foreach (is_array($schema['properties'] ?? null) ? $schema['properties'] : [] as $name => $property) {
            if (! is_array($property)) {
                continue;
            }

            $types = (array) ($property['type'] ?? []);

            if (is_string($name) && in_array('object', $types, true) && ! in_array('array', $types, true)) {
                $keys[$name] = true;
            }

            $keys = [...$keys, ...self::schemaKeys($property)];

            if (is_array($property['items'] ?? null)) {
                $keys = [...$keys, ...self::schemaKeys($property['items'])];
            }
        }

        return $keys;
    }
}
