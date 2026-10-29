<?php

declare(strict_types=1);

namespace WebxUi\Orders\Blocks;

use Closure;

/**
 * A block of the snapshot and the module that fills it (§6.4): the order checks the block's shape
 * by its owner's hand and shows its line in the list and letters without knowing the methods.
 */
final readonly class BlockDefinition
{
    /**
     * @param  string  $key  'delivery' | 'payment' — the column it lands in
     * @param  string  $module  'delivery'
     * @param  Closure(array<string, mixed>): list<string>  $validate  errors; empty — accepted
     * @param  (Closure(array<string, mixed>, string): string)|null  $summary  one line for the list and mail
     */
    public function __construct(
        public string $key,
        public string $module,
        public Closure $validate,
        public ?Closure $summary = null,
    ) {}
}
