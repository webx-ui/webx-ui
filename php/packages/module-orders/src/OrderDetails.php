<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * What `updateDetails()` changes (§5.3, §8): the contacts, the recipient, the buyer's comment, the
 * manager's note and the extra fields — never the lines, the money or the milestones. Only what was
 * named is touched, so «no recipient» (`recipient(null)`) and «the recipient as it is» (not named)
 * differ.
 *
 *     $orders->updateDetails($order, (new OrderDetails)->managerNote('Call after 6 pm'), 'panel');
 */
final class OrderDetails
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** The buyer's contacts, whole: the first name and a phone or an e-mail stay required. */
    public function customer(Contact $customer): self
    {
        return $this->with('customer', $customer);
    }

    /** Another person receives the order; null — the buyer does. */
    public function recipient(?Contact $recipient): self
    {
        return $this->with('recipient', $recipient);
    }

    public function comment(?string $comment): self
    {
        return $this->with('comment', $comment);
    }

    /** The manager's words the buyer never sees. */
    public function managerNote(?string $note): self
    {
        return $this->with('manager_note', $note);
    }

    /**
     * The answers to the extra fields (WEBX_UI_ORDER_FIELDS.md §6.1), `key => value | null`: only
     * the keys named change, null, an empty line or `false` takes a row away. The rows are made
     * under the order's lock, from the order as it is then.
     *
     * @param  array<string, mixed>  $answers  a line, a text, an option's value, a tick
     */
    public function fields(array $answers): self
    {
        return $this->with('fields', $answers);
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->values);
    }

    public function get(string $field): mixed
    {
        return $this->values[$field] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    private function with(string $field, mixed $value): self
    {
        $copy = clone $this;
        $copy->values[$field] = $value;

        return $copy;
    }
}
