<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Contracts;

/**
 * Optional on top of CreatesWaybills: what the carrier's waybill asks beyond the base's request
 * (§11.2) — weight, seats, sizes, a description of the goods — and whether it takes cash on
 * delivery at all. The dialog «Create waybill» draws these fields with the form `Field` of the
 * settings, fills them from the carrier's setting of the same name or the field's `default`, and
 * sends them in `WaybillRequest::$extra`; the cash on delivery is hidden and refused when
 * `supportsCod()` says no.
 *
 * A type without it gets the base's dialog as it is, cash on delivery included.
 */
interface DescribesWaybill
{
    /**
     * The carrier's own fields of a waybill, each `['name', 'type', 'label', 'default'?, 'options'?,
     * 'required'?, 'min'?, 'max'?, 'step'?, 'precision'?]` — `type` is text, number, select or boolean.
     *
     * @return list<array<string, mixed>>
     */
    public function waybillFields(): array;

    /** Whether the carrier collects cash on delivery for this type; false — `cod` is refused. */
    public function supportsCod(): bool;
}
