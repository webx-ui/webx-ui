<?php

declare(strict_types=1);

namespace WebxUi\NestedSet;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

class NestedSetServiceProvider extends ServiceProvider
{
    /**
     * Register `$table->nestedSet()` / `$table->dropNestedSet()` so a migration reads the way
     * the rest of a Laravel schema does.
     */
    public function boot(): void
    {
        if (! Blueprint::hasMacro('nestedSet')) {
            Blueprint::macro('nestedSet', function (
                string $lft = 'lft',
                string $rgt = 'rgt',
                string $parentId = 'parent_id',
                string $depth = 'depth',
            ): void {
                /** @var Blueprint $this */
                NestedSet::columns($this, $lft, $rgt, $parentId, $depth);
            });
        }

        if (! Blueprint::hasMacro('dropNestedSet')) {
            Blueprint::macro('dropNestedSet', function (
                string $lft = 'lft',
                string $rgt = 'rgt',
                string $parentId = 'parent_id',
                string $depth = 'depth',
            ): void {
                /** @var Blueprint $this */
                NestedSet::dropColumns($this, $lft, $rgt, $parentId, $depth);
            });
        }
    }
}
