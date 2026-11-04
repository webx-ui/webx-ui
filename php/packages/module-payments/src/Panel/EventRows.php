<?php

declare(strict_types=1);

namespace WebxUi\Payments\Panel;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Models\PaymentGatewayEvent;

/**
 * Events of the exchange journal as the panel shows them (spec §20.7).
 *
 * A line is what anybody with `payments.view` reads: time, direction, kind, code, signature,
 * decision, duration and the sizes of the bodies — never a header or a body. Those come with one
 * event, and only for `payments.exchange` (decision 47): without it the answer has no `request` /
 * `response` parts at all. A list never reads a body column: the columns are named.
 */
final class EventRows
{
    /** The columns of a line: no header, no body. */
    public const array LINE = [
        'id', 'trace', 'direction', 'kind', 'gateway', 'method_key', 'payment_id', 'order_id', 'source',
        'http_method', 'response_status', 'transport_error', 'request_size', 'response_size',
        'request_truncated', 'response_truncated', 'started_at', 'duration_ms', 'signature', 'reported',
        'status_before', 'status_after', 'outcome', 'message', 'created_at',
    ];

    public const string CAPTURE_REFUSED = 'capture_refused';

    public const string RELEASE_REFUSED = 'release_refused';

    /** Bodies larger than this are offered for download, not drawn (§20.7). */
    public const int SHOWN = 262144;

    /** What «errors» finds (§20.7): a refusal of ours, a gateway's error, a transport failure. */
    public const array ERRORS = ['rejected', 'unmatched', 'error'];

    /** @return Builder<PaymentGatewayEvent> */
    public static function query(): Builder
    {
        return PaymentGatewayEvent::query()->select(self::LINE);
    }

    /**
     * A capture or a release the gateway refused did not happen, and its line does not say it did.
     * The event keeps what it stored: only the screen changes.
     */
    private static function kind(PaymentGatewayEvent $event): string
    {
        return match (true) {
            $event->outcome !== 'rejected' => (string) $event->kind,
            $event->kind === ExchangeKind::Captured->value => self::CAPTURE_REFUSED,
            $event->kind === ExchangeKind::Released->value => self::RELEASE_REFUSED,
            default => (string) $event->kind,
        };
    }

    /** @return array<string, mixed> */
    public static function line(PaymentGatewayEvent $event): array
    {
        return [
            'id' => (int) $event->id,
            'trace' => $event->trace,
            'direction' => $event->direction,
            'kind' => self::kind($event),
            'gateway' => $event->gateway,
            'method_key' => $event->method_key,
            'payment_id' => $event->payment_id,
            'order_id' => $event->order_id,
            'source' => $event->source,
            'http_method' => $event->http_method,
            'status' => $event->response_status,
            'transport_error' => $event->transport_error,
            'request_size' => $event->request_size,
            'response_size' => $event->response_size,
            'truncated' => (bool) $event->request_truncated || (bool) $event->response_truncated,
            'at' => ($event->started_at ?? $event->created_at)?->toIso8601String(),
            'duration_ms' => $event->duration_ms,
            'signature' => $event->signature,
            'reported' => $event->reported,
            'status_before' => $event->status_before,
            'status_after' => $event->status_after,
            'outcome' => $event->outcome,
            'message' => $event->message,
            // A body that named this attempt under a signature that did not match (§9.2): linked
            // for the manager to see, believed in nothing.
            'claimed' => $event->outcome === 'rejected' && $event->signature === 'invalid' && $event->payment_id !== null,
            'error' => in_array($event->outcome, self::ERRORS, true)
                || ($event->response_status !== null && $event->response_status >= 400)
                || $event->transport_error !== null
                || $event->signature === 'invalid',
        ];
    }

    /**
     * One event: the line, the address and the masking — and, for whoever may read them, the
     * headers and the bodies of both halves.
     *
     * @return array<string, mixed>
     */
    public static function detail(PaymentGatewayEvent $event, bool $bodies): array
    {
        return [
            ...self::line($event),
            'url' => $event->url,
            'ip' => $event->ip,
            'event_key' => $event->event_key,
            'masked' => (object) ($event->masked ?? []),
            'request_sha256' => $event->request_sha256,
            'response_sha256' => $event->response_sha256,
            'bodies' => $bodies,
            'request' => $bodies ? self::half($event, 'request') : null,
            'response' => $bodies ? self::half($event, 'response') : null,
        ];
    }

    /**
     * The bytes of one body, as stored.
     */
    public static function body(PaymentGatewayEvent $event, string $part): ?string
    {
        $value = $event->getAttribute($part.'_body');

        return is_string($value) ? $value : null;
    }

    /** @return array<string, mixed> */
    private static function half(PaymentGatewayEvent $event, string $part): array
    {
        $body = self::body($event, $part);
        $text = $body !== null && strlen($body) <= self::SHOWN && mb_check_encoding($body, 'UTF-8');
        $headers = $event->getAttribute($part.'_headers');

        return [
            'headers' => is_array($headers) ? (object) $headers : (object) [],
            // Text is shown as it came; json and xml get their indents only on screen (§20.7).
            'body' => $text ? $body : null,
            'has_body' => $body !== null && $body !== '',
            'download' => $body !== null && $body !== '' && ! $text,
            'size' => $event->getAttribute($part.'_size'),
            'sha256' => $event->getAttribute($part.'_sha256'),
            'truncated' => (bool) $event->getAttribute($part.'_truncated'),
        ];
    }
}
