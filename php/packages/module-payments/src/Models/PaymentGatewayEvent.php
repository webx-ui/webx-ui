<?php

declare(strict_types=1);

namespace WebxUi\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use WebxUi\Payments\Exchange\AppendOnly;
use WebxUi\Payments\Exchange\ExchangeConnection;
use WebxUi\Payments\Exchange\ExchangeJournal;

/**
 * One event of the exchange with a gateway (spec §20.2), read through the journal's own connection.
 *
 * Append-only (decision 31): the model writes nothing at all — {@see ExchangeJournal} inserts the raw
 * half and adds the decision once — and refuses to change or delete a row, through itself or its
 * query builder. The only removal is `webx:payments:exchange-prune`, of events older than a year.
 *
 * @property int $id
 * @property string $trace
 * @property string $direction
 * @property string $kind
 * @property string|null $gateway
 * @property string|null $method_key
 * @property int|null $payment_id
 * @property int|null $order_id
 * @property string|null $source
 * @property string|null $http_method
 * @property string|null $url
 * @property string|null $ip
 * @property array<string, list<string>>|null $request_headers
 * @property string|null $request_body
 * @property int|null $request_size
 * @property string|null $request_sha256
 * @property bool $request_truncated
 * @property int|null $response_status
 * @property array<string, list<string>>|null $response_headers
 * @property string|null $response_body
 * @property int|null $response_size
 * @property string|null $response_sha256
 * @property bool $response_truncated
 * @property string|null $transport_error
 * @property Carbon|null $started_at
 * @property int|null $duration_ms
 * @property array<string, int>|null $masked
 * @property string|null $event_key
 * @property string|null $signature
 * @property string|null $reported
 * @property string|null $status_before
 * @property string|null $status_after
 * @property string|null $outcome
 * @property string|null $message
 * @property Carbon|null $created_at
 */
final class PaymentGatewayEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = ExchangeJournal::TABLE;

    // Nothing is written through the model at all: save() refuses, so there is nothing to guard.
    protected $guarded = [];

    public function getConnectionName(): string
    {
        return app(ExchangeConnection::class)->name();
    }

    public function newEloquentBuilder($query): ExchangeEventBuilder
    {
        return new ExchangeEventBuilder($query);
    }

    public function save(array $options = []): never
    {
        throw new AppendOnly('Exchange events are written by the journal only: '.ExchangeJournal::class.'.');
    }

    public function delete(): never
    {
        throw new AppendOnly('Exchange events are never deleted one by one: webx:payments:exchange-prune removes those older than a year.');
    }

    public function forceDelete(): never
    {
        $this->delete();
    }

    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'order_id' => 'integer',
            'request_headers' => 'array',
            'request_size' => 'integer',
            'request_truncated' => 'boolean',
            'response_status' => 'integer',
            'response_headers' => 'array',
            'response_size' => 'integer',
            'response_truncated' => 'boolean',
            'started_at' => 'datetime',
            'duration_ms' => 'integer',
            'masked' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
