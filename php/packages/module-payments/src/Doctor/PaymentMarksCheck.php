<?php

declare(strict_types=1);

namespace WebxUi\Payments\Doctor;

use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Payments\Contracts\ProvidesPaymentMarks;
use WebxUi\Payments\Methods;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\PaymentMarks;

/**
 * A mark a type names and nobody registered (groups spec §3.1): the buyer simply does not see it,
 * so only this says so. Registered only where the panel's frame is installed.
 */
final class PaymentMarksCheck implements Check
{
    public function __construct(
        private readonly Methods $methods,
        private readonly MethodTypes $types,
        private readonly PaymentMarks $marks,
    ) {}

    public function run(): array
    {
        $found = [];

        foreach ($this->methods->enabled() as $method) {
            $type = $this->types->has($method->type) ? $this->types->get($method->type) : null;

            if (! $type instanceof ProvidesPaymentMarks) {
                continue;
            }

            foreach (array_diff($type->marks($method), $this->marks->keys()) as $key) {
                $found[] = Diagnosis::warn('Payment marks', "Method {$method->key} names the mark {$key}, which PaymentMarks does not know: the buyer does not see it. Register the mark or fix the type.");
            }
        }

        return $found;
    }
}
