<?php

declare(strict_types=1);

namespace WebxUi\Services\Panel;

use WebxUi\Localization\Locales;
use WebxUi\Services\Models\Service;

/**
 * Where a saved service goes — the draft for its text, the link table for its categories.
 *
 * The same split the blog makes, for the same reason: a category is a row in a pivot, and there is
 * no such thing as half a row. Drafting it would need a second pivot with a flag on it and every
 * listing on the site remembering to ask for the published half. So the title, the address, the
 * announcement, the cover, the blocks and the project's fields wait in the draft, and the
 * categories take effect when they are saved — the panel says so beside the field.
 */
final class ServiceWriter
{
    /** The fields that are a map of languages rather than a value (§4.10). */
    private const TRANSLATED = ['title', 'slug', 'lead'];

    public function __construct(private readonly Locales $locales) {}

    /**
     * @param  array<string, mixed>  $columns  The service's own fields, only the ones that were sent.
     * @param  list<int>|null  $categories  Null leaves them alone; an empty list clears them.
     */
    public function save(Service $service, array $columns, ?array $categories = null, ?int $authorId = null): Service
    {
        if ($columns !== []) {
            $service->saveDraft($this->draft($service, $columns), $authorId);
        }

        if ($categories !== null) {
            // Through the shared code, which keeps the service's place inside a category it was
            // already in and gives it one by the whole list in a category it was not (decision 5).
            $service->syncCategories($categories);
        }

        return $service->refresh();
    }

    /**
     * The whole draft, with what was sent laid over what the editor is looking at.
     *
     * A translated field travels as its whole map from the form and as one string from an agent
     * speaking one language; neither is ever written over the whole field. A language the editor
     * emptied comes back as `''`, which is a key like any other; a language nobody mentioned is a
     * language nobody meant to delete.
     *
     * @param  array<string, mixed>  $columns
     * @return array<string, mixed>
     */
    private function draft(Service $service, array $columns): array
    {
        $locale = $this->locales->current();
        $values = $service->hasDraft() ? $service->draftValues() : $this->published($service);

        foreach ($columns as $field => $value) {
            if (! in_array($field, self::TRANSLATED, true)) {
                $values[$field] = $value;

                continue;
            }

            if (is_array($value)) {
                $current = $values[$field] ?? [];
                $values[$field] = is_array($current) ? [...$current, ...$value] : $value;

                continue;
            }

            $map = $values[$field] ?? [];
            $map = is_array($map) ? $map : [$locale => $map];
            $map[$locale] = $value;

            $values[$field] = $map;
        }

        return $values;
    }

    /**
     * The service as the site has it — the starting point for a draft that does not exist yet.
     *
     * @return array<string, mixed>
     */
    private function published(Service $service): array
    {
        $values = [
            'blocks' => $service->getAttribute('blocks'),
            'cover_id' => $service->cover_id,
            'extra' => $service->extraRaw(),
        ];

        foreach (self::TRANSLATED as $field) {
            $values[$field] = $service->getTranslations($field);
        }

        return $values;
    }
}
