<?php

declare(strict_types=1);

namespace WebxUi\Media\Remote;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use WebxUi\Admin\Remote\FetchRefused as Refused;
use WebxUi\Admin\Remote\RemoteFetcher as Fetcher;

/**
 * Fetches a file from an address an agent named, and only from the public internet — through
 * `module-admin`'s {@see Fetcher}, which every module downloads with: public addresses after the
 * name is resolved and on every redirect, the connection pinned to the checked address.
 *
 * What is the library's own: the body stops at the library's upload limit, and
 * `webx-media.remote.allow_hosts` (older than `webx-admin.remote.trusted_hosts`, and kept) names
 * hosts on the site's own network that skip the address check and nothing else.
 */
final class RemoteFetcher
{
    public function __construct(
        private readonly Fetcher $fetcher,
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    /**
     * @throws FetchRefused
     */
    public function fetch(string $url): Fetched
    {
        $limit = max(1, (int) $this->config->get('webx-media.upload.max_size', 51200)) * 1024;

        /** @var list<string> $trusted */
        $trusted = array_values(array_map('strval', (array) $this->config->get('webx-media.remote.allow_hosts', [])));
        $fetcher = $this->fetcher->trusting($trusted);

        // A test of the library's that still binds the old name answers for the library's fetches.
        if ($this->container->bound(HostResolver::class)) {
            $fetcher = $fetcher->resolvingWith($this->container->make(HostResolver::class));
        }

        try {
            $fetched = $fetcher->get($url, $limit);
        } catch (Refused $refused) {
            throw new FetchRefused($refused->reason === Refused::TOO_LARGE ? 'The file is larger than the library accepts.' : $refused->getMessage());
        }

        return new Fetched($fetched->body, $fetched->contentType, $fetched->url);
    }

    /** @see Fetcher::isPublic() */
    public static function isPublic(string $address): bool
    {
        return Fetcher::isPublic($address);
    }
}
