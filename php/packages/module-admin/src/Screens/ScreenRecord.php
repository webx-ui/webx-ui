<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

use Illuminate\Validation\ValidationException;

/**
 * What a saved screen is made of, sorted by where each part goes.
 *
 * A form used to keep the fields it knew by name and drop the rest without a word — so a field a
 * project patched onto the screen was drawn, filled in and never saved. Here nothing is dropped:
 * a value is the record's own column, a field somebody else takes care of (a relation, the SEO
 * card), or a field of the project, and that last kind goes into `extra`. Which is which is the
 * form's to say, because only the form knows its model; that the rest is kept is this class's.
 *
 *     $split = $record->split('blog.article-form', $input, own: ['title', 'slug'], taken: ['rubrics', 'seo']);
 *     $article->extra = $record->merge('blog.article-form', $article->extraRaw(), $split->extra);
 *
 * @phpstan-type Node array<string, mixed>
 */
final class ScreenRecord
{
    public function __construct(
        private readonly ScreenValues $values,
        private readonly ScreenRegistry $screens,
    ) {}

    /**
     * Check what came in against the screen and sort it.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>  $own  The record's own fields.
     * @param  list<string>  $taken  Fields the form stores somewhere else: relations, other modules' cards.
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function split(string $screen, array $input, array $own, array $taken = [], ?callable $can = null): ScreenSplit
    {
        $stored = $this->values->validate($screen, $input, $can);

        return new ScreenSplit(
            array_intersect_key($stored, array_flip($own)),
            array_intersect_key($stored, array_flip($taken)),
            array_diff_key($stored, array_flip([...$own, ...$taken])),
        );
    }

    /**
     * The project's fields as they will be stored: what arrived laid over what was there.
     *
     * A merge rather than a replacement, for the rule the SEO card keeps too: saving one tab
     * must not empty the fields of a tab nobody opened, and a field the patch no longer draws
     * keeps its value rather than being wiped by a form that does not know it ever existed.
     *
     * A localized value is merged language by language, for the reason `ArticleWriter` gives
     * about translated columns: the form sends the whole map, an agent sends the words it has,
     * and neither means to delete the languages it did not mention.
     *
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>|null Null when nothing is left, so the column stays empty rather than `{}`.
     */
    public function merge(string $screen, ?array $current, array $incoming): ?array
    {
        $values = $current ?? [];
        $localized = $this->localized($screen);

        foreach ($incoming as $name => $value) {
            $before = $values[$name] ?? null;

            $values[$name] = in_array($name, $localized, true) && is_array($value) && is_array($before)
                ? [...$before, ...$value]
                : $value;
        }

        return $values === [] ? null : $values;
    }

    /**
     * The node that draws one field, or null when the screen has no such field (any more).
     *
     * @return Node|null
     */
    public function field(string $screen, string $name): ?array
    {
        if (! $this->screens->has($screen)) {
            return null;
        }

        foreach ($this->screens->fields($screen) as $node) {
            if (($node['name'] ?? null) === $name) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function localized(string $screen): array
    {
        if (! $this->screens->has($screen)) {
            return [];
        }

        $names = [];

        foreach ($this->screens->fields($screen) as $node) {
            if (($node['localized'] ?? false) === true) {
                $names[] = (string) $node['name'];
            }
        }

        return $names;
    }
}
