<?php

declare(strict_types=1);

namespace WebxUi\Admin\Mail;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use WebxUi\Admin\Doctor\QueueBacklog;

/**
 * The one place that says where the site's letters go, as the application sees it right now.
 *
 * On a client's site letters seemed to go nowhere in every mode, and the panel showed nothing of
 * the three reasons: a config cached before `.env` was edited kept sending by the old transport
 * with a status of `sent`; a queue run by the scheduler sent each letter a minute later; and the
 * mail pointed at a test sandbox. The code that sends was fine all along. So the doctor, the
 * panel and the agent ask here, and say the same thing:
 *
 *     $routes->site();                          // the site's mailer: transport, host, trap
 *     $routes->smtp('sandbox.smtp.mailtrap.io', 2525);  // a channel's own server
 *     $routes->warnings($route);                // trap and stale config, in words
 *
 * A *trap* takes a letter and delivers it to nobody: the `log` and `array` transports, a test
 * sandbox, Mailpit or MailHog on a developer's machine. The list is `webx-admin.mail.traps`, and a
 * site adds its own there. Sending through one is not an error — it is what a staging site wants
 * — but nobody should be told such a letter was delivered.
 */
final class MailRoutes
{
    /** @var list<string> */
    public const TRAP_TRANSPORTS = ['log', 'array'];

    /**
     * Hosts, or `host:port`, matched with `Str::is`. Mailtrap's sandbox (not its sending
     * service, `live.smtp.mailtrap.io`, which delivers), and Mailpit and MailHog — by their
     * service names and by the port both listen on.
     *
     * @var list<string>
     */
    public const TRAP_HOSTS = ['sandbox.smtp.mailtrap.io', 'smtp.mailtrap.io', 'mailpit', 'mailhog', '*:1025'];

    public function __construct(
        private readonly Config $config,
        private readonly Application $app,
        private readonly QueueBacklog $queue,
    ) {}

    /** The site's own mail: the default mailer, the first of a failover's. */
    public function site(): MailRoute
    {
        $mailer = (string) $this->config->get('mail.default', 'smtp');
        $settings = (array) $this->config->get("mail.mailers.{$mailer}", []);
        $transport = (string) ($settings['transport'] ?? $mailer);

        // A failover or a round robin sends by its first mailer while that one works; that is
        // the one a person has to know about.
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $first = (string) (((array) ($settings['mailers'] ?? []))[0] ?? '');
            $settings = (array) $this->config->get("mail.mailers.{$first}", []);
            $transport = (string) ($settings['transport'] ?? $transport);
        }

        $host = is_string($settings['host'] ?? null) && $settings['host'] !== '' ? $settings['host'] : null;
        $port = is_numeric($settings['port'] ?? null) ? (int) $settings['port'] : null;

        // Only an SMTP mailer talks to a host; a `log` mailer with a leftover MAIL_HOST does not.
        if ($transport !== 'smtp') {
            [$host, $port] = [null, null];
        }

        return new MailRoute($mailer, $transport, $host, $port, $this->trap($transport, $host, $port));
    }

    /** A channel's own SMTP server. */
    public function smtp(string $host, int|string|null $port): MailRoute
    {
        $port = is_numeric($port) ? (int) $port : null;
        $host = trim($host);

        return new MailRoute('channel', 'smtp', $host === '' ? null : $host, $port, $this->trap('smtp', $host, $port));
    }

    /**
     * Whether `.env` was changed after the config was cached: what it says is then not what the
     * site does, and nothing else shows it — letters keep leaving by the old transport as `sent`.
     */
    public function staleConfig(): bool
    {
        $env = $this->app->environmentFilePath();
        $cached = $this->app->getCachedConfigPath();

        // Asked of the disk each time: a worker runs for days, and PHP would keep the first answer.
        clearstatcache(true, $env);
        clearstatcache(true, $cached);

        // No cached file is a config read from `.env` on every request, which is never stale.
        return is_file($env) && is_file($cached) && (int) filemtime($env) > (int) filemtime($cached);
    }

    /**
     * What a person should be told about a route, in the panel's language: a trap, and settings
     * that are not in effect yet. Empty for a real server and a fresh cache.
     *
     * @return list<array{code: string, message: string}>
     */
    public function warnings(MailRoute $route): array
    {
        $warnings = [];

        if ($route->isTrap()) {
            $warnings[] = [
                'code' => 'trap',
                'message' => $this->say(
                    $route->host === null ? 'trap-transport' : 'trap-host',
                    ['transport' => $route->transport, 'host' => (string) $route->trap],
                ),
            ];
        }

        if ($this->staleConfig()) {
            $warnings[] = ['code' => 'stale_config', 'message' => $this->say('stale-config')];
        }

        return $warnings;
    }

    /**
     * How letters wait to leave: `sync` sends them in the request, anything else through a
     * worker — and a worker that the scheduler starts every minute sends a letter up to a
     * minute after the form was sent. `hint` says so, for the places that promise a letter.
     *
     * @return array{driver: string, sync: bool, hint: string|null}
     */
    public function queue(): array
    {
        $driver = $this->queue->driver();
        $sync = $driver === 'sync';

        return ['driver' => $driver, 'sync' => $sync, 'hint' => $sync ? null : $this->say('queue-delay')];
    }

    /**
     * Whether a letter queued at this moment has waited longer than a working queue takes —
     * {@see QueueBacklog::MINUTES}, what the doctor counts a stalled job by.
     */
    public function stalled(?Carbon $queuedAt): bool
    {
        return $queuedAt !== null && $queuedAt->lt(Carbon::now()->subMinutes(QueueBacklog::MINUTES));
    }

    /** The words for a letter that has waited too long: is the queue running at all? */
    public function stalledWarning(): string
    {
        return $this->say('queue-stalled', ['minutes' => (string) QueueBacklog::MINUTES]);
    }

    /** The status of a letter a trap took: accepted, and delivered to nobody. */
    public function trappedLabel(): string
    {
        return $this->say('trapped');
    }

    /** What makes this way out a trap, or null when it delivers. */
    private function trap(string $transport, ?string $host, ?int $port): ?string
    {
        $traps = $this->config->get('webx-admin.mail.traps');
        $traps = is_array($traps) ? $traps : [];

        // Read with `??`: a config published before this key has no `mail` block at all.
        $transports = array_values(array_filter((array) ($traps['transports'] ?? self::TRAP_TRANSPORTS), 'is_string'));
        $hosts = array_values(array_filter((array) ($traps['hosts'] ?? self::TRAP_HOSTS), 'is_string'));

        if (in_array($transport, $transports, true)) {
            return $transport;
        }

        if ($host === null || $host === '') {
            return null;
        }

        $host = mb_strtolower($host);

        foreach ([$host, $host.':'.($port ?? '')] as $candidate) {
            if (Str::is($hosts, $candidate)) {
                return $port === null ? $host : $host.':'.$port;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function say(string $key, array $replace = []): string
    {
        $line = __('webx-admin::mail.'.$key, $replace);

        return is_string($line) ? $line : $key;
    }
}
