<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Api;

use WebxUi\Payments\GatewayUnavailable;

/**
 * What LiqPay said: an answer of the API or the `data` of a notification, the same fields (§3.3).
 *
 * A failed request and a payment with the status `error` look alike. Only `payment_id` tells them
 * apart (§9.1).
 */
final readonly class Answer
{
    /** @param  array<array-key, mixed>  $fields */
    public function __construct(public array $fields) {}

    /** @throws GatewayUnavailable the body is not JSON: the words carry nothing of it */
    public static function of(string $body): self
    {
        $fields = json_decode($body, true);

        return is_array($fields) ? new self($fields) : throw GatewayUnavailable::malformed();
    }

    /** The string `data` of a notification, or null when it is not base64 of a JSON object. */
    public static function decode(string $data): ?self
    {
        $json = base64_decode($data, true);
        $fields = $json === false ? null : json_decode($json, true);

        return is_array($fields) ? new self($fields) : null;
    }

    public function isPayment(): bool
    {
        return $this->paymentId() !== null;
    }

    public function paymentId(): ?string
    {
        return $this->text('payment_id');
    }

    /** Our uuid of the attempt. */
    public function orderId(): ?string
    {
        return $this->text('order_id');
    }

    public function action(): ?string
    {
        return $this->text('action');
    }

    public function status(): ?string
    {
        return $this->text('status');
    }

    public function errorCode(): ?string
    {
        return $this->text('err_code');
    }

    /** LiqPay writes some of its fields as numbers and some as strings. */
    public function text(string $field): ?string
    {
        $value = $this->fields[$field] ?? null;

        return (is_string($value) && $value !== '') || is_int($value) ? (string) $value : null;
    }
}
