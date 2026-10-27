<?php

declare(strict_types=1);

namespace WebxUi\Debug\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Debug\Access;
use WebxUi\Debug\Panel\DebugModule;
use WebxUi\Debug\Reports;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * `debug_page` (§13 of the debug bar spec): the report of the debug bar for a page, to an agent.
 *
 * The page is asked for over HTTP, as a visitor's browser would ask — not handled inside this
 * process, where the singletons, the cache memo and the application already booted would give
 * other numbers than the ones the person sees, and the same numbers are the point. The request
 * carries a one-time token instead of a session; the page answers as it would a visitor, and the
 * report it leaves is read back from the store. Only reading: there is nothing to rehearse.
 */
final class DebugTools
{
    /** The sections of a report an agent may ask for. */
    private const SECTIONS = ['queries', 'repeated', 'timeline', 'blocks', 'views', 'cache', 'request', 'other', 'log'];

    /** What is left out unless asked: long, and rarely what a question is about. */
    private const QUIET = ['views', 'log'];

    public function __construct(
        private readonly Access $access,
        private readonly Reports $reports,
        private readonly Container $container,
        private readonly Config $config,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        return Arguments::strictAll([
            Tool::read(
                'page',
                'Open a page of this site as a visitor would and return what the server did for it — the report of the debug bar: '
                .'every SQL statement with its values (secrets masked), time and the file:line that issued it; repeated statements '
                .'grouped by shape (count ≥ thresholds.repeat is a likely N+1, with where it came from); the timeline (boot, '
                .'middleware, resolve, handler, render, each region and block); the regions and blocks drawn with their time, '
                .'statements and theme layer; the versioned cache groups read (memo / store / miss) and their versions; what the '
                .'resolver found; outgoing HTTP, queued jobs, mail, log. Use it before and after an optimisation or a change of '
                .'layout, and compare summary.queries, summary.repeated and summary.sql_ms. On a production site only where it opened the '
                .'tool (WEBX_DEBUG_MCP_IN_PRODUCTION), and there every value is masked: shapes, counts, times and places only.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->page($arguments, $user),
                ['properties' => [
                    'url' => ['type' => 'string', 'description' => 'A path of this site, with its language prefix and query: "/", "/blog/news?page=2". No scheme, no host.'],
                    'include' => [
                        'type' => 'array',
                        'items' => ['type' => 'string', 'enum' => self::SECTIONS],
                        'description' => 'Sections to return besides the summary. Everything but views and log when omitted.',
                    ],
                    'locale' => ['type' => 'string', 'description' => 'Sent as Accept-Language. The language of the page itself is its prefix.'],
                ], 'required' => ['url']],
                scope: 'debug:read',
                permission: DebugModule::PERMISSION,
            ),
        ], 'debug_');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function page(array $arguments, ?Authenticatable $user): array
    {
        if (! $user instanceof Model || ! $this->access->allows($user)) {
            throw new ToolFailure('debug_page needs an active administrator with the permission debug.view.');
        }

        $path = $this->path($arguments['url'] ?? null);
        $include = $this->include($arguments['include'] ?? null);
        $issued = $this->access->issue($user);

        $headers = [Access::HEADER => $issued['token']];
        $locale = $arguments['locale'] ?? null;

        if (is_string($locale) && $locale !== '') {
            $headers['Accept-Language'] = $locale;
        }

        $url = rtrim((string) $this->config->get('app.url'), '/').$path;
        // Asked for at the call, not when the module was built: a site or a test may bind its own.
        $answer = $this->container->make(PageFetcher::class)->fetch($url, $headers);

        if ($answer['status'] === null) {
            throw new ToolFailure("The site did not answer {$url}: {$answer['error']}. If it cannot reach itself by its own name, set WEBX_DEBUGBAR_RESOLVE_TO to the address to connect to.");
        }

        $report = $this->reports->find($issued['id'], (string) $user->getKey());

        if ($report === null) {
            throw new ToolFailure("{$url} answered {$answer['status']}, but no report was made: only a GET of a page of the site is collected — not the panel, its API, a preview or a file. Check the path, or that this request reached this site and not another one.");
        }

        $out = [
            'id' => $report['id'],
            'url' => $report['url'],
            'status' => $answer['status'],
            'fetch_ms' => $answer['ms'],
            'early' => $report['early'],
            'truncated' => $report['truncated'],
            'summary' => $report['summary'],
            'thresholds' => $report['thresholds'],
        ];

        foreach (['queries', 'repeated', 'timeline', 'blocks', 'views', 'cache', 'request', 'other'] as $section) {
            if (in_array($section, $include, true)) {
                $out[$section] = $report[$section] ?? null;
            }
        }

        if (isset($out['other']) && is_array($out['other']) && ! in_array('log', $include, true)) {
            unset($out['other']['log']);
        }

        if (in_array('log', $include, true) && ! isset($out['other'])) {
            $out['log'] = $report['other']['log'] ?? [];
        }

        return $out;
    }

    private function path(mixed $url): string
    {
        $url = is_string($url) ? trim($url) : '';

        // A path of this site only: a host, a scheme or `//` is refused rather than stripped — a
        // path cut out of somebody else's address is a question nobody asked.
        if ($url === '' || ! str_starts_with($url, '/') || str_starts_with($url, '//') || str_contains($url, '\\') || preg_match('/[\x00-\x20]/', $url) === 1) {
            throw new ToolFailure('url is a path of this site starting with one /: "/", "/blog/news?page=2". A scheme, a host and "//" are refused.');
        }

        return $url;
    }

    /**
     * @return list<string>
     */
    private function include(mixed $include): array
    {
        if ($include === null) {
            return array_values(array_diff(self::SECTIONS, self::QUIET));
        }

        if (! is_array($include)) {
            throw new ToolFailure('include is a list of sections: '.implode(', ', self::SECTIONS).'.');
        }

        $unknown = array_values(array_diff(array_map('strval', $include), self::SECTIONS));

        if ($unknown !== []) {
            throw new ToolFailure('Unknown sections ['.implode(', ', $unknown).']; known: '.implode(', ', self::SECTIONS).'.');
        }

        return array_values(array_map('strval', $include));
    }
}
