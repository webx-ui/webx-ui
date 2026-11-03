<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Psr\Log\LoggerInterface;
use Throwable;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Cart\Totals\ResolvedLine;
use WebxUi\Delivery\Contracts\MethodType;

/**
 * The price of a method, the way the base gives it (§9.2, §9.4): the type's own quote, kept for a
 * while when a satellite asks its carrier's API, and «Free» with the old price when the cart's
 * rules gave `shipping.paid_by_store` to a method that honours it — the type does nothing for it.
 *
 * The shop's own methods are priced by their row, so they are never cached: a price changed in
 * the panel shows at once. A satellite's quote is cached for `webx-delivery.quotes.ttl` by
 * method, the carrier's locality and what travels; one that throws is «By carrier rates» with a
 * line in the log, and the method stays. Making an order (`Purpose::Order`) neither reads the cache
 * nor falls back: a failure there is {@see QuoteFailed}, and no order is made.
 */
final class Quotes
{
    public const string CACHE_PREFIX = 'delivery.quotes:';

    public function __construct(
        private readonly Cache $cache,
        private readonly Config $config,
        private readonly LoggerInterface $log,
    ) {}

    public function quote(Method $method, MethodType $type, DeliveryContext $context): Quote
    {
        $quote = $this->own($method, $type, $context);

        if ($quote->available() && $method->honoursPaidByStore && $context->paidByStore()) {
            return $quote->paidByStore();
        }

        return $quote;
    }

    /**
     * Seconds a satellite gives its carrier's API for one price — what it passes to its HTTP
     * client (`Http::timeout()`), since PHP cannot cut a call short from outside.
     */
    public function timeout(): float
    {
        $timeout = $this->config->get('webx-delivery.quotes.timeout');

        return is_numeric($timeout) && $timeout > 0 ? (float) $timeout : 2.0;
    }

    /** The cache key of a satellite's quote: the method as it is now, the carrier's locality, and the cart's goods. */
    public function key(Method $method, DeliveryContext $context): string
    {
        $lines = array_map(static fn (ResolvedLine $line): array => [
            $line->type, $line->refId, $line->effectiveQty, $line->price, $line->options,
        ], $context->shippableLines());

        return self::CACHE_PREFIX.hash('xxh128', (string) json_encode([
            $method->toArray(),
            $context->locality->id,
            $context->carrierLocality,
            $context->currency,
            $context->cart->subtotal,
            $lines,
        ]));
    }

    private function own(Method $method, MethodType $type, DeliveryContext $context): Quote
    {
        if ($type->carrier() === Carriers::STORE) {
            return $type->quote($method, $context);
        }

        if ($context->purpose === Purpose::Order) {
            try {
                return $type->quote($method, $context);
            } catch (Throwable $e) {
                throw QuoteFailed::for($method->key, $context->locale, $e);
            }
        }

        $key = $this->key($method, $context);
        $stored = $this->cache->get($key);

        if (is_array($stored)) {
            return Quote::fromStored($stored);
        }

        $started = microtime(true);

        try {
            $quote = $type->quote($method, $context);
        } catch (Throwable $e) {
            // Not cached: the next showing of the step asks again.
            $this->log->warning("Delivery: the price of the method {$method->key} failed; shown «By carrier rates».", ['exception' => $e]);

            return Quote::carrierRates();
        }

        $took = microtime(true) - $started;

        if ($took > $this->timeout()) {
            $this->log->warning(sprintf('Delivery: the price of the method %s took %.1f s, over the timeout of %.1f s.', $method->key, $took, $this->timeout()));
        }

        $ttl = $this->config->get('webx-delivery.quotes.ttl');
        $this->cache->put($key, $quote->toStored(), is_numeric($ttl) ? max(1, (int) $ttl) : 600);

        return $quote;
    }
}
