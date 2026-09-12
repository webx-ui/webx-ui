<?php

declare(strict_types=1);

namespace WebxUi\NestedSet;

use Illuminate\Database\Schema\Blueprint;

/**
 * Schema helpers for tables that hold a nested set.
 */
final class NestedSet
{
    /**
     * Add the four columns a nested set needs.
     *
     * lft and rgt are **signed** on purpose: moving a subtree parks it in negative bounds
     * before putting it back, and an unsigned column would reject that halfway through.
     */
    public static function columns(
        Blueprint $table,
        string $lft = 'lft',
        string $rgt = 'rgt',
        string $parentId = 'parent_id',
        string $depth = 'depth',
    ): void {
        $table->unsignedBigInteger($parentId)->nullable()->index();
        $table->integer($lft)->default(0);
        $table->integer($rgt)->default(0);
        $table->unsignedSmallInteger($depth)->default(0);
        $table->index([$lft, $rgt]);
    }

    /**
     * Drop what {@see self::columns()} added.
     */
    public static function dropColumns(
        Blueprint $table,
        string $lft = 'lft',
        string $rgt = 'rgt',
        string $parentId = 'parent_id',
        string $depth = 'depth',
    ): void {
        $table->dropIndex([$lft, $rgt]);
        $table->dropIndex([$parentId]);
        $table->dropColumn([$lft, $rgt, $parentId, $depth]);
    }
}
