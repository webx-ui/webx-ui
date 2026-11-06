<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Localization\Slug;

/**
 * The part of an address a record names itself: Latin letters, digits, and single hyphens or
 * underscores between them.
 *
 * Checked as it will be saved: `HasLatinSlug` transliterates a slug in another alphabet on the
 * model, so «колодки» is no mistake of the editor's, and one that leaves nothing gets
 * `<type>-<id>`. What is refused is a Latin slug of a wrong shape, in words the form can show.
 *
 * Whether the address is free is not checked here — only the registry sees every kind of page
 * at once, and it answers by refusing the save with a 422 under the field.
 */
final class SlugType implements FieldType
{
    private const PATTERN = '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/';

    /**
     * The rules on their own, for a request that checks a slug outside a screen.
     *
     * @return list<mixed>
     */
    public static function checks(): array
    {
        return [
            'nullable',
            'string',
            'max:190',
            static function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || $value === '') {
                    return;
                }

                $latin = Slug::latin($value, 'en');

                if ($latin !== '' && preg_match(self::PATTERN, $latin) !== 1) {
                    $fail((string) __('webx-admin::categories.slug-shape'));
                }
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return self::checks();
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
