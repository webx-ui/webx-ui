<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Http\Controllers\MethodProbeController;

/**
 * Whether the web server lets PUT and DELETE through to Laravel.
 *
 * Some shared hosts refuse every method but GET and POST before PHP is ever asked — mod_security,
 * `<LimitExcept GET POST>`. The panel then opens and reads, and every save and every delete gets
 * a 403 or a 405 that looks like a permission. The cure is on the front end — `methodOverride` in
 * `createAdmin()` sends them as POST — so this says so rather than failing a deploy.
 *
 * The site is asked over the network at APP_URL, the way a browser asks it. A refusal counts
 * only when the probe's mark is missing: Laravel's own 403 or 405 carries it, the web server's
 * cannot. A site the command line cannot reach — a container that does not know its own public
 * name, a certificate it does not trust — is a reason not to know, not a finding.
 */
final class Methods implements Check
{
    private const SUBJECT = 'PUT and DELETE';

    /** What a web server answers when it will not pass a method on. */
    private const REFUSALS = [403, 405, 501];

    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
        private readonly Filesystem $files,
        private readonly Factory $http,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $base = rtrim((string) $this->config->get('app.url'), '/');

        if (preg_match('#^https?://#i', $base) !== 1) {
            return [Diagnosis::ok(self::SUBJECT, 'not checked: APP_URL is not an http(s) address.')];
        }

        $url = $base.'/'.trim((string) $this->config->get('webx-admin.api_path'), '/').'/method-probe';
        $refused = [];

        foreach (['PUT', 'DELETE'] as $method) {
            try {
                $response = $this->http
                    ->connectTimeout(3)
                    ->timeout(5)
                    ->withoutRedirecting()
                    ->acceptJson()
                    ->send($method, $url);
            } catch (ConnectionException $failure) {
                return [Diagnosis::ok(self::SUBJECT, "not checked: {$url} cannot be reached from here ({$failure->getMessage()}).")];
            }

            if ($response->header(MethodProbeController::HEADER) === $method) {
                continue;
            }

            $status = $response->status();

            if (! in_array($status, self::REFUSALS, true)) {
                // A redirect, a 404 from a route cache older than the probe, a site down for
                // maintenance: none of them says who would have refused the method.
                return [Diagnosis::ok(self::SUBJECT, "not checked: {$method} {$url} answered {$status} without reaching the probe.")];
            }

            $refused[] = "{$method} {$status}";
        }

        if ($refused === []) {
            return [Diagnosis::ok(self::SUBJECT, 'the web server passes them on to Laravel.')];
        }

        $what = 'the web server refuses '.implode(', ', $refused).' before Laravel sees the request';

        $entry = $this->overriding();

        if ($entry !== null) {
            return [Diagnosis::ok(self::SUBJECT, "{$what}, and {$entry} sends them as POST with `methodOverride`.")];
        }

        return [Diagnosis::warn(
            self::SUBJECT,
            "{$what}, so the panel reads but cannot save or delete — pass `methodOverride: true` to createAdmin() in the panel's entry file, or `methodOverride: 'field'` if the host strips the X-HTTP-Method-Override header too.",
        )];
    }

    /** The first panel entry file that already asks for the override, or null. */
    private function overriding(): ?string
    {
        foreach (array_filter((array) $this->config->get('webx-admin.vite', []), is_string(...)) as $entry) {
            $path = $this->app->basePath($entry);

            if ($this->files->isFile($path) && preg_match('/\bmethodOverride\s*:\s*(?!false\b)/', $this->files->get($path)) === 1) {
                return $entry;
            }
        }

        return null;
    }
}
