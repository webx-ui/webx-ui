<?php

declare(strict_types=1);

namespace WebxUi\Geography\Models;

/**
 * Fields an administrator changed by hand (decision 42): a carrier's sync reads `isManual()`
 * before it writes a field, the way a manual binding is never overwritten (§4.4).
 *
 * @property list<string>|null $manual_fields
 */
trait HasManualFields
{
    /** @param  list<string>  $fields */
    public function markManual(array $fields): static
    {
        $marked = array_values(array_unique([...($this->manual_fields ?? []), ...$fields]));
        sort($marked);
        $this->manual_fields = $marked;

        return $this;
    }

    public function isManual(string $field): bool
    {
        return in_array($field, $this->manual_fields ?? [], true);
    }
}
