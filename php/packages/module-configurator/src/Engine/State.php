<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Engine;

/**
 * What a schema and a visitor's values resolve to (§6.1): the value of every visible step and
 * field, in the order of the document, and what is hidden.
 *
 * Only what is visible has a value: a hidden step is not priced and does not go into a request,
 * a hidden field is 0 in the formula. `errors` are the values that were given and refused — a
 * size outside its limits is an error, not something the server quietly clamps (§6.4).
 */
final class State
{
    /**
     * @param  array<string, string|float>  $values  step key → option value, field key → number
     * @param  list<string>  $hidden  keys of hidden steps and fields, in the order of the document
     * @param  array<string, list<string>>  $options  visible step → its visible options
     * @param  array<string, array{0: float, 1: float}>  $limits  visible field → [min, max] in force
     * @param  list<array{path: string, code: string}>  $errors
     */
    public function __construct(
        public readonly array $values,
        public readonly array $hidden,
        public readonly array $options,
        public readonly array $limits,
        public readonly array $errors = [],
    ) {}

    /** The chosen value of a visible step, or null. */
    public function choice(string $step): ?string
    {
        return isset($this->options[$step]) && is_string($this->values[$step] ?? null) ? $this->values[$step] : null;
    }

    /**
     * The numbers of the visible fields — what a formula reads.
     *
     * @return array<string, float>
     */
    public function sizes(): array
    {
        $sizes = [];

        foreach ($this->limits as $field => $limit) {
            $sizes[$field] = (float) $this->values[$field];
        }

        return $sizes;
    }

    /**
     * The values as JSON shows them: a size that is a whole number without its `.0`.
     *
     * @return array<string, string|int|float>
     */
    public function normalized(): array
    {
        return array_map(Engine::number(...), $this->values);
    }
}
