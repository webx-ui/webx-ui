<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Writes the journal of the exchange with gateways (spec §20, decision 31): every event in one row,
 * the raw half by one insert before anything is parsed, the decision by one update after.
 *
 * Through its own connection (decision 38): an event commits by itself and outlives the status
 * change it led to, rolled back or not. A failed write does not stop a payment — it is logged, and
 * {@see self::record()} answers null; only an incoming notification needs the event first, and asks
 * with {@see self::recordOrFail()} (decision 48).
 */
final class ExchangeJournal
{
    public const string TABLE = 'payment_gateway_events';

    /** The column is a MEDIUMBLOB: a limit above it would be cut by the database instead (§20.4). */
    public const int MAX_BODY_LIMIT = 16 * 1024 * 1024 - 1;

    public function __construct(
        private readonly ConnectionResolverInterface $db,
        private readonly ExchangeConnection $connection,
        private readonly ExchangeMasker $masker,
        private readonly Repository $config,
        private readonly LoggerInterface $log,
    ) {}

    /** Writes the raw half of an event; null — the write failed and was logged, the caller goes on. */
    public function record(ExchangeEntry $entry, ExchangeSecrets $secrets): ?int
    {
        try {
            return $this->insert($entry, $secrets);
        } catch (Throwable $e) {
            $this->failed($entry->kind, $e);

            return null;
        }
    }

    /**
     * The same for an event that must be written before it is acted on: a notification that is
     * not in the journal is not read (decision 48).
     *
     * @throws JournalUnavailable
     */
    public function recordOrFail(ExchangeEntry $entry, ExchangeSecrets $secrets): int
    {
        try {
            return $this->insert($entry, $secrets);
        } catch (Throwable $e) {
            $this->failed($entry->kind, $e);

            throw new JournalUnavailable('The payment exchange journal could not write the event.', 0, $e);
        }
    }

    /**
     * Adds the decision to an event, once: false — there is no such event, it has its decision
     * already, or the write failed (logged).
     */
    public function decide(int $id, ExchangeDecision $decision, ExchangeSecrets $secrets): bool
    {
        try {
            $counts = [];
            $values = [
                'payment_id' => $decision->paymentId,
                'order_id' => $decision->orderId,
                'signature' => $decision->signature?->value,
                'reported' => $decision->reported,
                'status_before' => $decision->statusBefore,
                'status_after' => $decision->statusAfter,
                'outcome' => $decision->outcome->value,
                'message' => $this->cut($this->masker->text($decision->message, $secrets, $counts), 500),
            ];

            if ($decision->eventKey !== null) {
                $values['event_key'] = $decision->eventKey;
            }

            if ($decision->responseStatus !== null || $decision->responseBody !== null || $decision->responseHeaders !== null) {
                $values = [
                    ...$values,
                    'response_status' => $decision->responseStatus,
                    'response_headers' => $this->json($this->masker->headers($decision->responseHeaders, $secrets, $counts)),
                    ...$this->body('response', $decision->responseBody, $secrets, $counts),
                ];
            }

            $query = $this->table()->where('id', $id)->whereNull('outcome');

            if ($counts !== []) {
                $before = $this->table()->where('id', $id)->value('masked');
                $values['masked'] = $this->json($this->merge(is_string($before) ? json_decode($before, true) : null, $counts));
            }

            return $query->update($values) === 1;
        } catch (Throwable $e) {
            $this->failed(null, $e);

            return false;
        }
    }

    /** The bytes kept of one body (decision 50). */
    public function bodyLimit(): int
    {
        $limit = $this->config->get('webx-payments.exchange.body_limit', 1024 * 1024);

        return max(1, min(self::MAX_BODY_LIMIT, is_numeric($limit) ? (int) $limit : 1024 * 1024));
    }

    private function insert(ExchangeEntry $entry, ExchangeSecrets $secrets): int
    {
        $counts = [];
        $now = CarbonImmutable::now();

        $row = [
            'trace' => $entry->trace,
            'direction' => $entry->direction()->value,
            'kind' => $entry->kind->value,
            'gateway' => $entry->gateway,
            'method_key' => $entry->methodKey,
            'payment_id' => $entry->paymentId,
            'order_id' => $entry->orderId,
            'source' => $entry->source,
            'http_method' => $entry->httpMethod,
            'url' => $this->masker->text($entry->url, $secrets, $counts),
            'ip' => $entry->ip,
            'request_headers' => $this->json($this->masker->headers($entry->requestHeaders, $secrets, $counts)),
            ...$this->body('request', $entry->requestBody, $secrets, $counts),
            'response_status' => $entry->responseStatus,
            'response_headers' => $this->json($this->masker->headers($entry->responseHeaders, $secrets, $counts)),
            ...$this->body('response', $entry->responseBody, $secrets, $counts),
            'transport_error' => $this->cut($this->masker->text($entry->transportError, $secrets, $counts), 500),
            'started_at' => ($entry->startedAt ?? $now)->format('Y-m-d H:i:s.v'),
            'duration_ms' => $entry->durationMs,
            'event_key' => $entry->eventKey,
            'created_at' => $now->format('Y-m-d H:i:s'),
        ];
        $row['masked'] = $this->json($this->merge(null, $counts));

        return (int) $this->table()->insertGetId($row);
    }

    /**
     * A body after masking: kept whole up to the limit, else its first bytes with the mark; the
     * size and the hash are of the whole masked body either way (§20.4).
     *
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function body(string $side, ?string $body, ExchangeSecrets $secrets, array &$counts): array
    {
        if ($body === null) {
            return ["{$side}_body" => null, "{$side}_size" => null, "{$side}_sha256" => null, "{$side}_truncated" => false];
        }

        $masked = (string) $this->masker->text($body, $secrets, $counts, body: true);
        $limit = $this->bodyLimit();
        $truncated = strlen($masked) > $limit;

        return [
            "{$side}_body" => $truncated ? substr($masked, 0, $limit) : $masked,
            "{$side}_size" => strlen($masked),
            "{$side}_sha256" => hash('sha256', $masked),
            "{$side}_truncated" => $truncated,
        ];
    }

    /**
     * @param  mixed  $before  the counts already written
     * @param  array<string, int>  $counts
     * @return array<string, int>|null
     */
    private function merge(mixed $before, array $counts): ?array
    {
        $merged = is_array($before) ? array_map(intval(...), $before) : [];

        foreach ($counts as $key => $count) {
            $merged[$key] = ($merged[$key] ?? 0) + $count;
        }

        ksort($merged);

        return $merged === [] ? null : $merged;
    }

    private function json(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    private function cut(?string $text, int $length): ?string
    {
        return $text === null ? null : mb_strcut($text, 0, $length);
    }

    private function table(): Builder
    {
        return $this->db->connection($this->connection->name())->table(self::TABLE);
    }

    /**
     * The bodies are in the event, not in the log (§15.2): only what failed and why. A query
     * error's own message carries the bound values, so its driver's message is logged instead.
     */
    private function failed(?ExchangeKind $kind, Throwable $e): void
    {
        $reason = $e instanceof QueryException && $e->getPrevious() !== null ? $e->getPrevious() : $e;

        $this->log->error('webx-payments: journal write failed', [
            'kind' => $kind?->value,
            'exception' => $reason::class,
            'message' => mb_strcut($reason->getMessage(), 0, 300),
        ]);
    }
}
