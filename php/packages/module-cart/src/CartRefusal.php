<?php

declare(strict_types=1);

namespace WebxUi\Cart;

use Illuminate\Validation\ValidationException;

/**
 * A batch refused, as any {@see ValidationException}, plus the lines of the cart that are already
 * at their limit: a buy button on an item already there at its maximum. That is not a mistake of
 * the visitor's, and the widget opens the cart with a notice on the line instead of an error
 * under the button (decision of 28.10.2026, §9.3).
 */
final class CartRefusal extends ValidationException
{
    /** @var array<int, int> the batch's index => the id of the line already at its limit */
    public array $atLimit = [];

    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<int, int>  $atLimit
     */
    public static function of(array $errors, array $atLimit = []): self
    {
        $refusal = self::withMessages($errors);
        $refusal->atLimit = $atLimit;

        return $refusal;
    }

    /**
     * The errors other than the «at its limit» ones: what the panel says as a warning.
     *
     * @return array<string, array<int, string>>
     */
    public function others(): array
    {
        $at = array_map(static fn (int $index): string => "items.{$index}.qty", array_keys($this->atLimit));

        return array_diff_key($this->errors(), array_flip($at));
    }
}
