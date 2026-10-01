<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The server, over its HTTP JSON API (decision 2 of the Manticore spec): SQL in raw mode for what
 * reads and shapes tables, `/bulk` for what writes documents.
 *
 * A server that does not answer is remembered for `down_for` seconds after the first failure
 * (decision 14): everybody who asks in that time is told at once, instead of each waiting for a
 * timeout of their own. A server that answers with an error is not down — the question was wrong.
 *
 * The HTTP API has no statements in a batch, so what has to be asked several times is asked at
 * once, side by side ({@see sqlMany()}): one round trip, not one per question.
 */
class Manticore
{
    private const DOWN = 'webx-catalog-manticore.down-until';

    public function __construct(
        private readonly Config $config,
        private readonly Cache $cache,
        private readonly Http $http,
    ) {}

    public function prefix(): string
    {
        return TablePrefix::of($this->config->get('webx-catalog-manticore.table_prefix'));
    }

    /** The table of one language, or the one a rebuild fills beside it. */
    public function table(string $locale, bool $next = false): string
    {
        return $this->prefix().'_catalog_products_'.$locale.($next ? '_next' : '');
    }

    public function address(): string
    {
        return (string) $this->config->get('webx-catalog-manticore.host', '127.0.0.1').':'.(int) $this->config->get('webx-catalog-manticore.port', 9308);
    }

    /** Whether the server failed lately enough not to be asked again yet. */
    public function down(): bool
    {
        $until = $this->cache->get(self::DOWN);

        return is_int($until) && $until > time();
    }

    /** Seconds until the server is asked again; what a 503 tells to come back after. */
    public function retryAfter(): int
    {
        $until = $this->cache->get(self::DOWN);

        return is_int($until) ? max(1, $until - time()) : $this->downFor();
    }

    /**
     * One statement; its result sets — a `SELECT` with facets has one per `FACET`.
     *
     * @return list<array<string, mixed>>
     */
    public function sql(string $query): array
    {
        return $this->sqlMany([$query])[0];
    }

    /**
     * Several statements at once, side by side; the answers in the order asked.
     *
     * @param  list<string>  $queries
     * @return list<list<array<string, mixed>>>
     */
    public function sqlMany(array $queries): array
    {
        if ($queries === []) {
            return [];
        }

        $this->guard();

        try {
            if (count($queries) === 1) {
                $responses = [$this->request()->asForm()->post($this->url('/sql?mode=raw'), ['query' => $queries[0]])];
            } else {
                $responses = $this->http->pool(fn (Pool $pool): array => array_map(
                    fn (string $query) => $this->options($pool->asForm())->post($this->url('/sql?mode=raw'), ['query' => $query]),
                    $queries,
                ));
            }
        } catch (ConnectionException $failure) {
            throw $this->fell($failure);
        }

        $answers = [];

        // The pool hands the answers back as they came, keyed by the order asked.
        foreach ($queries as $index => $query) {
            $answers[] = $this->sets($responses[$index] ?? null, $query);
        }

        return $answers;
    }

    /**
     * Documents in one request: `replace` and `delete` lines of `/bulk`. A table that is not there
     * is an error, never a table made on the spot.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function bulk(array $lines): void
    {
        if ($lines === []) {
            return;
        }

        $this->guard();
        $body = implode("\n", array_map(static fn (array $line): string => (string) json_encode($line, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), $lines))."\n";

        try {
            $response = $this->request()->withBody($body, 'application/x-ndjson')->post($this->url('/bulk'));
        } catch (ConnectionException $failure) {
            throw $this->fell($failure);
        }

        $answer = $response->json();

        if (! is_array($answer)) {
            throw $this->fell(new ManticoreUnavailable("Manticore at {$this->address()} answered /bulk with HTTP {$response->status()} and no JSON."));
        }

        if (($answer['errors'] ?? false) === true || ($answer['error'] ?? '') !== '') {
            throw new ManticoreError('Manticore refused a write: '.(is_string($answer['error'] ?? null) && $answer['error'] !== '' ? $answer['error'] : json_encode($answer['items'] ?? [])));
        }
    }

    /** `'…'` for a string inside a statement. */
    public static function quote(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sets(mixed $response, string $query): array
    {
        if ($response instanceof Throwable) {
            throw $this->fell($response);
        }

        if (! $response instanceof Response) {
            throw $this->fell(new ManticoreUnavailable("Manticore at {$this->address()} gave no answer."));
        }

        $answer = $response->json();

        if (! is_array($answer)) {
            throw $this->fell(new ManticoreUnavailable("Manticore at {$this->address()} answered with HTTP {$response->status()} and no JSON."));
        }

        if (isset($answer['error']) && is_string($answer['error'])) {
            throw new ManticoreError("Manticore refused [{$query}]: {$answer['error']}");
        }

        $sets = [];

        foreach ($answer as $set) {
            if (is_array($set) && is_string($set['error'] ?? null) && $set['error'] !== '') {
                throw new ManticoreError("Manticore refused [{$query}]: {$set['error']}");
            }

            if (is_array($set)) {
                /** @var array<string, mixed> $set */
                $sets[] = $set;
            }
        }

        return $sets;
    }

    private function guard(): void
    {
        if ($this->down()) {
            throw new ManticoreUnavailable("Manticore at {$this->address()} failed less than {$this->downFor()} seconds ago.");
        }
    }

    private function fell(Throwable $failure): ManticoreUnavailable
    {
        $this->cache->put(self::DOWN, time() + $this->downFor(), $this->downFor());
        Log::warning("Manticore at {$this->address()} does not answer; the catalogue is not asking it for {$this->downFor()} seconds.", ['exception' => $failure]);

        return $failure instanceof ManticoreUnavailable
            ? $failure
            : new ManticoreUnavailable("Manticore at {$this->address()} does not answer: {$failure->getMessage()}", previous: $failure);
    }

    private function request(): PendingRequest
    {
        return $this->options($this->http->asForm());
    }

    private function options(PendingRequest $request): PendingRequest
    {
        return $request
            ->connectTimeout((int) ceil((float) $this->config->get('webx-catalog-manticore.connect_timeout', 1.5)))
            ->timeout((int) $this->config->get('webx-catalog-manticore.timeout', 5))
            ->withOptions(['connect_timeout' => (float) $this->config->get('webx-catalog-manticore.connect_timeout', 1.5)]);
    }

    private function url(string $path): string
    {
        return 'http://'.$this->address().$path;
    }

    private function downFor(): int
    {
        return max(1, (int) $this->config->get('webx-catalog-manticore.down_for', 30));
    }
}
