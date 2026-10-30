<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Steps;

/**
 * Why a step is not ready for an order (spec §20.2): under its field on the page, by `code` for an
 * agent (`contacts.phone_required`, `delivery.point_gone`).
 */
final readonly class StepError
{
    /**
     * @param  string|null  $field  the name of the form's field, with dots; null — the step as a whole
     * @param  string  $message  in the language of the request
     */
    public function __construct(
        public string $step,
        public ?string $field,
        public string $message,
        public string $code,
    ) {}

    /** @return array{step: string, field: string|null, message: string, code: string} */
    public function toArray(): array
    {
        return ['step' => $this->step, 'field' => $this->field, 'message' => $this->message, 'code' => $this->code];
    }
}
