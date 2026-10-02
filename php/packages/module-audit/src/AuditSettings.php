<?php

declare(strict_types=1);

namespace WebxUi\Audit;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Settings\Settings;

/**
 * The section's settings, read in one place: where the site is, where to connect, and which
 * other hosts are this site's stands. They live on the "Audit" tab of the site's settings, under
 * `audit.*`, saved and checked by the settings screen like every other tab.
 */
final readonly class AuditSettings
{
    public function __construct(
        private Container $container,
        private Config $config,
    ) {}

    /** The address the audit opens, `APP_URL` unless the setting says otherwise. */
    public function baseUrl(): string
    {
        $url = $this->text('audit.base-url') ?? (string) $this->config->get('app.url', 'http://localhost');

        return rtrim($url, '/');
    }

    /** Where the TCP connection goes instead of DNS — an IP, or a host — or null. */
    public function resolveTo(): ?string
    {
        return $this->text('audit.resolve-to');
    }

    /**
     * "Other addresses of this site": stands and old domains, one per line.
     *
     * @return list<string>
     */
    public function otherHosts(): array
    {
        $value = $this->settings('audit.other-hosts');
        $lines = is_array($value) ? $value : preg_split('/[\s,]+/', is_string($value) ? $value : '');

        return array_values(array_filter(array_map(
            static fn (mixed $line): string => is_string($line) ? trim($line) : '',
            $lines ?: [],
        )));
    }

    /** The classifier for a run aimed at `$baseUrl`. */
    public function classifier(?string $baseUrl = null): HostClassifier
    {
        $own = [
            (string) parse_url($baseUrl ?? $this->baseUrl(), PHP_URL_HOST),
            (string) parse_url((string) $this->config->get('app.url', ''), PHP_URL_HOST),
        ];

        /** @var list<string> $zones */
        $zones = (array) $this->config->get('webx-audit.dev_zones', []);
        /** @var list<string> $words */
        $words = (array) $this->config->get('webx-audit.dev_words', []);

        return new HostClassifier($own, $this->otherHosts(), $zones, $words);
    }

    private function text(string $key): ?string
    {
        $value = $this->settings($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function settings(string $key): mixed
    {
        // Through the container on every read: the settings cache is dropped on save, and a
        // value read once at boot would outlive the save it was meant to follow.
        return $this->container->make(Settings::class)->get($key);
    }
}
