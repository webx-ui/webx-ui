<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A category model — what the shared code may ask of one. {@see IsCategory} answers all of it
 * but the first two, which are the module's.
 */
interface Category
{
    public static function categoryKind(): CategoryKind;

    /**
     * @return BelongsToMany<covariant Model, covariant Model>
     */
    public function items(): BelongsToMany;

    /**
     * @return list<string>
     */
    public function categoryFields(): array;

    public function categoryValue(string $field): mixed;

    public function writeCategoryValue(string $field, mixed $value): void;

    /**
     * @param  array<string, mixed>  $values
     */
    public function categorySaved(array $values): void;

    public function itemCount(): int;

    public function inUseMessage(int $count): string;

    public function displayName(string $locale): string;
}
