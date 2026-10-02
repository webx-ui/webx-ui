<?php

declare(strict_types=1);

namespace WebxUi\Audit;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Settings\Settings;

/**
 * The section's settings, read in one place: where the site is, where to connect, which other
 * hosts are this site's stands, the limits, the thresholds, the schedule and the history. Stored
 * with the site's settings under `audit.*`, edited on the section's own screen (`audit.settings`)
 * — the limits and the schedule are the audit's business, not the site's.
 */
final readonly class AuditSettings
{
    /** The screen of the section's settings (§8). */
    public const SCREEN = 'audit.settings';

    /** Number settings of the screen and the key of `config('webx-audit')` each one overrides. */
    private const NUMBERS = [
        'audit.pages-limit' => 'pages_limit',
        'audit.concurrency' => 'concurrency',
        'audit.resources-limit' => 'resources_limit',
        'audit.keep-runs' => 'keep_runs',
        'audit.keep-snapshots' => 'keep_snapshots',
        'audit.title-min' => 'thresholds.title_min',
        'audit.title-max' => 'thresholds.title_max',
        'audit.description-min' => 'thresholds.description_min',
        'audit.description-max' => 'thresholds.description_max',
        'audit.thin-words' => 'thresholds.thin_words',
        'audit.text-ratio' => 'thresholds.text_ratio',
        'audit.url-length' => 'thresholds.url_length',
        'audit.ttfb-ms' => 'thresholds.ttfb_ms',
        'audit.depth' => 'thresholds.depth',
        'audit.image-kb' => 'thresholds.image_kb',
        'audit.external-links' => 'thresholds.external_links',
    ];

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
        return $this->lines('audit.other-hosts');
    }

    /**
     * A setting written one item per line (commas and spaces split too).
     *
     * @return list<string>
     */
    private function lines(string $key): array
    {
        $value = $this->settings($key);
        $lines = is_array($value) ? $value : preg_split('/[\s,]+/', is_string($value) ? $value : '');

        return array_values(array_filter(array_map(
            static fn (mixed $line): string => is_string($line) ? trim($line) : '',
            $lines ?: [],
        )));
    }

    /**
     * The section's settings laid over `config('webx-audit')`, so that everything which reads
     * the config — the crawler, the checks' thresholds, the pruning — reads what an
     * administrator set, and a project that published the config keeps its file as the default.
     * Called where a run starts and before every piece of it.
     */
    public function apply(): void
    {
        // What the file said, kept aside the first time: a queue worker lives through many runs,
        // and a setting emptied between two of them has to fall back to the file, not to the
        // value laid over it before.
        if (! is_array($this->config->get('webx-audit.file'))) {
            $file = [];

            foreach (self::NUMBERS as $key) {
                $file[$key] = $this->config->get('webx-audit.'.$key);
            }

            $this->config->set('webx-audit.file', $file);
        }

        /** @var array<string, mixed> $file */
        $file = $this->config->get('webx-audit.file');
        $values = [];

        foreach (self::NUMBERS as $setting => $key) {
            $value = $this->settings($setting);
            $values['webx-audit.'.$key] = is_numeric($value) ? (int) $value : $file[$key] ?? null;
        }

        $values['webx-audit.excluded'] = array_values(array_unique([
            ...array_values(array_filter((array) $this->config->get('webx-audit.exclude', []), 'is_string')),
            ...$this->lines('audit.exclude'),
        ]));

        $this->config->set($values);
    }

    /**
     * The nightly run: off unless switched on (§8), at the hour given, of the scope given.
     *
     * @return array{scope: string, hour: int}|null
     */
    public function schedule(): ?array
    {
        if (! in_array($this->settings('audit.schedule'), [true, 1, '1'], true)) {
            return null;
        }

        $hour = $this->settings('audit.schedule-hour');

        return [
            'scope' => $this->settings('audit.schedule-scope') === 'quick' ? 'quick' : 'full',
            'hour' => is_numeric($hour) ? max(0, min(23, (int) $hour)) : 3,
        ];
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
