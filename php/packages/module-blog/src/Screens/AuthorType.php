<?php

declare(strict_types=1);

namespace WebxUi\Blog\Screens;

use Illuminate\Validation\Rule;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-article-author`: one administrator, or nobody (§2.14).
 *
 * An id and not a name, because the author is a row in `cms_users` rather than a string on the
 * article: renaming somebody renames their byline everywhere, and an account that is gone
 * leaves the article without an author instead of with a name nobody can look up.
 */
final class AuthorType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'integer', Rule::exists('cms_users', 'id')];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): ?int
    {
        return $this->id($value);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): ?int
    {
        return $this->id($stored);
    }

    private function id(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }
}
