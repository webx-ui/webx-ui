<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeEntry;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Exchange\ExchangeSecrets;
use WebxUi\Payments\Exchange\InProcessHosts;
use WebxUi\Payments\Exchange\JournaledPsrClient;

/**
 * The only HTTP client a satellite uses (spec §20.3, decision 37). Laravel's Http with the base's
 * timeouts and a middleware that journals every exchange: the request as sent, the answer as it
 * came, or the transport error — one event per attempt of a request, the same for every gateway.
 *
 * The base makes one for a call — gateway, method, attempt, kind, source, trace — and passes it to
 * the satellite's method. A failed write of the journal never fails the request: it is logged and
 * the payment goes on (decision 31).
 */
final class GatewayHttp
{
    public function __construct(
        private readonly ExchangeJournal $journal,
        private readonly Repository $config,
        private readonly ExchangeContext $context,
        private readonly ExchangeSecrets $secrets,
        private readonly ExchangeKind $kind = ExchangeKind::Other,
        private readonly ?InProcessHosts $hosts = null,
    ) {}

    /** `Http::…` with the journal's middleware and the base's timeouts. */
    public function request(): PendingRequest
    {
        return Http::timeout($this->seconds('timeout', 15))
            ->connectTimeout($this->seconds('connect_timeout', 5))
            ->withMiddleware($this->middleware())
            // Below the journal, so that an answer from inside the process is journaled like any.
            ->withMiddleware($this->inProcess());
    }

    /** The same client for another kind of request: `key` for a signing key, `status` inside readNotification(). */
    public function kind(ExchangeKind|string $kind): self
    {
        return new self($this->journal, $this->config, $this->context, $this->secrets, $kind instanceof ExchangeKind ? $kind : ExchangeKind::from($kind), $this->hosts);
    }

    /** A secret made at run time — an OAuth token — wiped from this and every later event of the trace (§20.5). */
    public function secret(string $value, string $name): self
    {
        $this->secrets->add($value, $name);

        return $this;
    }

    /** PSR-18 for a gateway's SDK, journaled the same way. */
    public function psr(): ClientInterface
    {
        return new JournaledPsrClient(
            $this->request()->buildClient(),
            $this->seconds('timeout', 15),
            $this->seconds('connect_timeout', 5),
        );
    }

    public function context(): ExchangeContext
    {
        return $this->context;
    }

    /** What the events of this trace wipe — for the base's own events of the same action (a handoff). */
    public function secrets(): ExchangeSecrets
    {
        return $this->secrets;
    }

    /** The kind its events are written with. */
    public function currentKind(): ExchangeKind
    {
        return $this->kind;
    }

    /**
     * Hosts served inside the process (`InProcessHosts`, «gateway A»): their answer, or the
     * failure they throw, instead of the network.
     *
     * @return callable(callable): callable
     */
    private function inProcess(): callable
    {
        $hosts = $this->hosts;

        return static fn (callable $handler): callable => static function (RequestInterface $request, array $options) use ($handler, $hosts): PromiseInterface {
            $server = $hosts?->for($request);

            if ($server === null) {
                return $handler($request, $options);
            }

            try {
                return Create::promiseFor($server($request));
            } catch (Throwable $e) {
                return Create::rejectionFor($e);
            }
        };
    }

    /** @return callable(callable): callable */
    private function middleware(): callable
    {
        return fn (callable $handler): callable => function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $started = CarbonImmutable::now();
            $clock = hrtime(true);
            $body = self::read($request);

            // A handler below may throw rather than reject (a fake, a stray request): the same failure.
            try {
                /** @var PromiseInterface $promise */
                $promise = $handler($request, $options);
            } catch (Throwable $e) {
                $promise = Create::rejectionFor($e);
            }

            return $promise->then(
                function (ResponseInterface $response) use ($request, $body, $started, $clock): ResponseInterface {
                    $this->write($request, $body, $started, $clock, $response, null);

                    return $response;
                },
                function (mixed $reason) use ($request, $body, $started, $clock): PromiseInterface {
                    // An answer that came with the failure: getResponse() of Guzzle 7's
                    // RequestException, of Guzzle 8's ResponseException.
                    $response = is_object($reason) && method_exists($reason, 'getResponse') ? $reason->getResponse() : null;
                    $response = $response instanceof ResponseInterface ? $response : null;
                    $error = $reason instanceof Throwable ? $reason->getMessage() : 'request failed';
                    $this->write($request, $body, $started, $clock, $response, $error);

                    return Create::rejectionFor($reason);
                },
            );
        };
    }

    private function write(RequestInterface $request, ?string $body, CarbonImmutable $started, int|float $clock, ?ResponseInterface $response, ?string $error): void
    {
        try {
            $this->journal->record(new ExchangeEntry(
                kind: $this->kind,
                trace: $this->context->trace,
                gateway: $this->context->gateway,
                methodKey: $this->context->methodKey,
                paymentId: $this->context->paymentId,
                orderId: $this->context->orderId,
                source: $this->context->source,
                httpMethod: $request->getMethod(),
                url: (string) $request->getUri(),
                requestHeaders: $request->getHeaders(),
                requestBody: $body,
                responseStatus: $response?->getStatusCode(),
                responseHeaders: $response?->getHeaders(),
                responseBody: $response === null ? null : self::read($response),
                transportError: $error,
                startedAt: $started,
                durationMs: (int) round((hrtime(true) - $clock) / 1_000_000),
            ), $this->secrets);
        } catch (Throwable) {
            // The journal logs its own failures; nothing here may fail the payment (decision 31).
        }
    }

    /** The body as it went or came, leaving the stream where it was for whoever reads it next. */
    private static function read(MessageInterface $message): ?string
    {
        $stream = $message->getBody();

        if (! $stream->isSeekable()) {
            return $stream->getSize() === 0 ? '' : null;
        }

        $position = $stream->tell();
        $stream->rewind();
        $contents = $stream->getContents();
        $stream->seek($position);

        return $contents;
    }

    private function seconds(string $key, int|float $default): int|float
    {
        $value = $this->config->get('webx-payments.http.'.$key, $default);

        return is_numeric($value) && $value > 0 ? $value + 0 : $default;
    }
}
