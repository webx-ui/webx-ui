<?php

declare(strict_types=1);

namespace WebxUi\Admin\Testing;

use WebxUi\Admin\Remote\HostResolver;

/**
 * DNS for a test, without a network: the names a test gave, and `$otherwise` for the rest —
 * by default a public address, so that `Http::fake()` keeps answering `https://supplier.test/…`
 * while `RemoteFetcher` still checks every hop. Bind it as `HostResolver`:
 *
 *     $this->app->instance(HostResolver::class, $dns = new FakeHostResolver);
 *     $dns->answer('rebind.example', ['10.0.0.7']);
 */
class FakeHostResolver extends HostResolver
{
    /** An address of `example.com`: public, and nobody's in particular. */
    public const PUBLIC = '93.184.215.14';

    /**
     * @param  array<string, list<string>>  $hosts
     * @param  list<string>  $otherwise
     */
    public function __construct(
        private array $hosts = [],
        private readonly array $otherwise = [self::PUBLIC],
    ) {}

    /**
     * @param  list<string>  $addresses  empty — the name does not resolve
     */
    public function answer(string $host, array $addresses): self
    {
        $this->hosts[strtolower($host)] = $addresses;

        return $this;
    }

    public function resolve(string $host): array
    {
        return $this->hosts[strtolower($host)] ?? $this->otherwise;
    }
}
