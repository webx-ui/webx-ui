<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Probes\ProbeSet;
use WebxUi\Audit\Probes\SiteClient;
use WebxUi\Audit\Runs\AuditRun;

/**
 * What a check gets to look at (§6): the run, what has been collected so far, a client with the
 * same settings as the run's, the host classifier and the thresholds.
 *
 * The probes are here only for the stage that took them — their bodies are kept in memory, not
 * in the database — which is why the checks that read them run in that stage.
 */
final readonly class AuditContext
{
    public function __construct(
        public AuditRun $run,
        public HostClassifier $hosts,
        public SiteClient $client,
        public ProbeSet $probes,
        private Config $config,
    ) {}

    public function threshold(string $key, int $default): int
    {
        $value = $this->config->get('webx-audit.thresholds.'.$key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    /** The site's own host, as the run reaches it. */
    public function host(): string
    {
        return (string) parse_url($this->run->base_url, PHP_URL_HOST);
    }

    public function scheme(): string
    {
        return (string) (parse_url($this->run->base_url, PHP_URL_SCHEME) ?: 'https');
    }

    /** The base address without a trailing slash: `https://shop.com`. */
    public function base(): string
    {
        return rtrim($this->run->base_url, '/');
    }

    /**
     * Whether the run is aimed at what looks like a working domain rather than a stand — the
     * production-only config checks keep quiet on `shop.local`, where `APP_DEBUG` is the point.
     */
    public function production(): bool
    {
        return ! $this->hosts->looksLikeStand($this->host());
    }
}
