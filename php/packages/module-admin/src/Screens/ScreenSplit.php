<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * A saved screen, sorted by {@see ScreenRecord::split()}: every key is in exactly one of the
 * four, and only the keys that were sent are in any of them.
 */
final readonly class ScreenSplit
{
    /**
     * @param  array<string, mixed>  $own  The record's own columns.
     * @param  array<string, mixed>  $taken  What the form stores somewhere else.
     * @param  array<string, mixed>  $extra  Everything else: the project's fields.
     * @param  array<string, array{target: string, ids: list<int>}>  $relations  The `wx-relations` fields, by role — whatever list they were named in.
     */
    public function __construct(
        public array $own,
        public array $taken,
        public array $extra,
        public array $relations = [],
    ) {}
}
