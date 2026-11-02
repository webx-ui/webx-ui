<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Delivery\Sync\SyncFailure;

/**
 * The thin client of the carrier's JSON API (§3.4): one address, one `call(model, method,
 * properties)`, the answer read by its `success` and not by the HTTP code, failures in the base's
 * words — `CarrierUnavailable` with `NoAnswer`, `KeyRejected` or `BadResponse`.
 *
 * Two guards before any request:
 *
 * - **read only** (`webx-delivery-novaposhta.read_only`): a writing method ({@see Methods::WRITE})
 *   throws {@see WriteRefused} and nothing is sent — for a key that belongs to somebody else's
 *   account;
 * - **known methods only**: a method on neither list is refused the same way.
 *
 * The key goes into the body of the request and nowhere else: not into the log, the journal, an
 * exception or the answer. The log and the journal get the model, the method, the page and the
 * outcome ({@see Journal}).
 */
final class Client
{
    public function __construct(
        private readonly Factory $http,
        private readonly Journal $journal,
        private readonly string $url,
        private readonly int $timeout = 10,
        private readonly bool $readOnly = false,
    ) {}

    public function readOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * Calls one method of the API with the account's key.
     *
     * @param  array<string, mixed>  $properties  `methodProperties` of the request
     *
     * @throws WriteRefused a writing method in read-only mode, or a method the client does not know
     * @throws CarrierUnavailable no answer, the key refused, an answer of another shape, or `success: false`
     *                            (then with an {@see ApiError} as the previous exception)
     */
    public function call(string $apiKey, string $model, string $method, array $properties = []): ApiResponse
    {
        $page = self::page($properties);
        $started = hrtime(true);
        $response = $this->send($apiKey, $model, $method, $properties, $page, $started);

        return $this->read($response, $model, $method, $page, $started);
    }

    /**
     * Calls one method and hands back the carrier's body as it came, without reading it — for
     * recording answers (`webx:novaposhta:record`, §13.1). The same guards as {@see call()}:
     * read only, known methods, the journal, the key nowhere but the request.
     *
     * @param  array<string, mixed>  $properties
     * @return array{status: int, redirected: bool, body: mixed}
     *
     * @throws WriteRefused
     * @throws CarrierUnavailable no answer at all
     */
    public function raw(string $apiKey, string $model, string $method, array $properties = []): array
    {
        $page = self::page($properties);
        $started = hrtime(true);
        $response = $this->send($apiKey, $model, $method, $properties, $page, $started);
        $body = json_decode($response->body(), true);
        $outcome = match (true) {
            ! $response->successful() => 'no_answer',
            ! is_array($body) || ! array_key_exists('success', $body) => 'bad_response',
            $body['success'] !== true => 'error',
            default => 'ok',
        };

        $this->journal->record($model, $method, $outcome, $page, self::ms($started));
        // A fake answer has no transfer stats, hence no effective address.
        $uri = $response->effectiveUri();

        return [
            'status' => $response->status(),
            'redirected' => $uri !== null && (string) $uri !== $this->url,
            'body' => $body,
        ];
    }

    /**
     * The guards and the request itself.
     *
     * @param  array<string, mixed>  $properties
     */
    private function send(string $apiKey, string $model, string $method, array $properties, ?int $page, int $started): Response
    {
        $name = Methods::name($model, $method);

        if (! Methods::reads($model, $method) && ! Methods::writes($model, $method)) {
            $this->journal->record($model, $method, 'refused', $page);

            throw WriteRefused::unknown($name);
        }

        if ($this->readOnly && Methods::writes($model, $method)) {
            $this->journal->record($model, $method, 'refused', $page);

            throw WriteRefused::readOnly($name);
        }

        if (trim($apiKey) === '') {
            $this->journal->record($model, $method, 'key', $page);

            throw CarrierUnavailable::keyRejected("Nova Poshta: {$name} without an API key.");
        }

        try {
            return $this->http
                ->timeout($this->timeout)
                ->connectTimeout(min(5, $this->timeout))
                ->acceptJson()
                ->asJson()
                ->post($this->url, [
                    'apiKey' => $apiKey,
                    'modelName' => $model,
                    'calledMethod' => $method,
                    // An empty list would go as [] — the carrier expects an object.
                    'methodProperties' => $properties === [] ? (object) [] : self::wire($properties),
                ]);
        } catch (ConnectionException) {
            // The exception's own text is dropped: nothing of the request may reach the log.
            throw $this->fail($model, $method, $page, $started, 'no_answer', new CarrierUnavailable("Nova Poshta: no answer to {$name}."));
        }
    }

    /**
     * Numbers go as strings: the carrier refuses `"Page": 1` with «Page is invalid format» and
     * takes `"Page": "1"` (checked on the key, stage 3).
     *
     * @param  array<mixed>  $properties
     * @return array<mixed>
     */
    private static function wire(array $properties): array
    {
        return array_map(static fn (mixed $value): mixed => match (true) {
            is_array($value) => self::wire($value),
            is_int($value), is_float($value) => (string) $value,
            default => $value,
        }, $properties);
    }

    /** @param  array<string, mixed>  $properties */
    private static function page(array $properties): ?int
    {
        return isset($properties['Page']) && is_numeric($properties['Page']) ? (int) $properties['Page'] : null;
    }

    private function read(Response $response, string $model, string $method, ?int $page, int $started): ApiResponse
    {
        $name = Methods::name($model, $method);

        if (! $response->successful()) {
            throw $this->fail($model, $method, $page, $started, 'no_answer', new CarrierUnavailable("Nova Poshta: HTTP {$response->status()} to {$name}."), $response->status());
        }

        $body = json_decode($response->body(), true);

        if (! is_array($body) || ! array_key_exists('success', $body)) {
            throw $this->fail($model, $method, $page, $started, 'bad_response', CarrierUnavailable::badResponse("Nova Poshta: {$name} answered with something other than its JSON."), $response->status());
        }

        $errors = self::strings($body['errors'] ?? []);
        $codes = self::strings($body['errorCodes'] ?? []);

        if ($body['success'] !== true || $errors !== []) {
            $error = new ApiError($name, $errors, $codes);

            if (self::keyRefused($errors)) {
                throw $this->fail($model, $method, $page, $started, 'key', new CarrierUnavailable("Nova Poshta: the API key was refused on {$name}.", SyncFailure::KeyRejected, $error));
            }

            throw $this->fail($model, $method, $page, $started, 'error', new CarrierUnavailable($error->getMessage(), SyncFailure::NoAnswer, $error));
        }

        if (! array_key_exists('data', $body) || ! is_array($body['data'])) {
            throw $this->fail($model, $method, $page, $started, 'bad_response', CarrierUnavailable::badResponse("Nova Poshta: {$name} answered without its data."), $response->status());
        }

        $this->journal->record($model, $method, 'ok', $page, self::ms($started));
        $info = is_array($body['info'] ?? null) ? $body['info'] : [];

        return new ApiResponse(
            data: array_values($body['data']),
            warnings: self::strings($body['warnings'] ?? []),
            errors: $errors,
            errorCodes: $codes,
            totalCount: isset($info['totalCount']) && is_numeric($info['totalCount']) ? (int) $info['totalCount'] : null,
        );
    }

    private function fail(string $model, string $method, ?int $page, int $started, string $outcome, CarrierUnavailable $exception, ?int $status = null): CarrierUnavailable
    {
        $this->journal->record($model, $method, $outcome, $page, self::ms($started));

        Log::warning('Nova Poshta: a call failed.', array_filter([
            'model' => $model,
            'method' => $method,
            'page' => $page,
            'outcome' => $outcome,
            'status' => $status,
        ], static fn (mixed $value): bool => $value !== null));

        return $exception;
    }

    /**
     * Whether the carrier refused the key rather than the request. Checked on stage 3: a key the
     * carrier does not know gets «API key expired» (code 20000201204) with HTTP 200.
     *
     * @param  list<string>  $errors
     */
    private static function keyRefused(array $errors): bool
    {
        foreach ($errors as $error) {
            if (preg_match('/api\s*key|apikey|api auth/i', $error) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        array_walk_recursive($value, static function (mixed $item) use (&$strings): void {
            if (is_scalar($item) && (string) $item !== '') {
                $strings[] = (string) $item;
            }
        });

        return $strings;
    }

    private static function ms(int $started): int
    {
        return intdiv(hrtime(true) - $started, 1_000_000);
    }
}
