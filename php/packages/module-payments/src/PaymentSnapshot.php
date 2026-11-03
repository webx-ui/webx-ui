<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/**
 * The `payment` block of an order (spec §10.4): the common part every block has (§6.4 of the
 * orders spec) — the method's key, its title in every language of the site, no price in v1 — and
 * the type's own details, copied, so that a later edit of the method does not change the order.
 */
final readonly class PaymentSnapshot
{
    /**
     * @param  array<string, string>  $title  by language
     * @param  array<string, mixed>  $details  the type's own: `instructions` of a bank transfer
     */
    public function __construct(
        public string $method,
        public string $type,
        public Kind $kind,
        public array $title,
        public array $details = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'type' => $this->type,
            'kind' => $this->kind->value,
            'title' => $this->title,
            'price' => null,
            ...$this->details,
        ];
    }

    /**
     * The block's own rules (§6.4): what the order accepts as `payment`.
     *
     * @param  array<string, mixed>  $block
     * @return list<string> errors; empty — accepted
     */
    public static function validate(array $block): array
    {
        $errors = [];

        foreach (['method', 'type'] as $field) {
            if (! is_string($block[$field] ?? null) || $block[$field] === '') {
                $errors[] = "payment.{$field} is a non-empty string";
            }
        }

        if (! in_array($block['kind'] ?? null, array_column(Kind::cases(), 'value'), true)) {
            $errors[] = 'payment.kind is online or offline';
        }

        if (! is_array($block['title'] ?? null) || Words::clean($block['title']) === []) {
            $errors[] = 'payment.title is a text by language';
        }

        if (isset($block['instructions']) && ! is_array($block['instructions'])) {
            $errors[] = 'payment.instructions is an object';
        }

        return $errors;
    }

    /**
     * One line for the list and the letters: the method's title in the order's language.
     *
     * @param  array<string, mixed>  $block
     */
    public static function summary(array $block, string $locale): string
    {
        return (string) (Words::pick(is_array($block['title'] ?? null) ? $block['title'] : null, $locale) ?? ($block['method'] ?? ''));
    }
}
