<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/**
 * A group with the methods one list offers in it (groups spec §2.6). `Methods::grouped()` never
 * gives an empty one, and a single view means a flat list: no heads (decision 10).
 */
final readonly class MethodGroupView
{
    /**
     * @param  non-empty-list<MethodOption>  $options  by the position of the method
     * @param  list<string>  $marks  of all its methods, once each, in the order of `PaymentMarks`
     */
    public function __construct(
        public MethodGroup $group,
        public array $options,
        public array $marks = [],
    ) {}

    public function key(): string
    {
        return $this->group->key;
    }
}
