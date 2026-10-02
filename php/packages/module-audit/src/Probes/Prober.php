<?php

declare(strict_types=1);

namespace WebxUi\Audit\Probes;

use Illuminate\Support\Str;
use WebxUi\Audit\Hosts\HostClassifier;

/**
 * The few dozen requests of stage 2 (§3): mirrors, scheme, index files, slashes, case, a
 * random address, the page's own static files, the certificate.
 *
 * Slashes, case and the trailing slash need an address that exists below the home page; the
 * first internal link of the home page is taken, and without one those probes are skipped
 * rather than guessed — a check that cannot be made is not a check that passed loudly.
 */
final class Prober
{
    /** The page's own static files asked for their caching — enough to see the rule. */
    private const ASSETS = 6;

    public function __construct(private readonly CertificateReader $certificates) {}

    public function collect(string $baseUrl, SiteClient $client, HostClassifier $hosts): ProbeSet
    {
        $base = rtrim($baseUrl, '/');
        $scheme = (string) (parse_url($base, PHP_URL_SCHEME) ?: 'https');
        $host = (string) parse_url($base, PHP_URL_HOST);
        $port = parse_url($base, PHP_URL_PORT);
        $authority = $host.(is_int($port) ? ':'.$port : '');

        $answers = [];
        $answers['home'] = $home = $client->get($base.'/');

        if ($scheme === 'https') {
            $answers['http'] = $client->get('http://'.$authority.'/');
        }

        $answers['mirror'] = $client->get($scheme.'://'.HostClassifier::mirrorOf($host).(is_int($port) ? ':'.$port : '').'/');

        foreach (['index.php', 'index.html', 'index.htm'] as $file) {
            $answers['index:/'.$file] = $client->get($base.'/'.$file);
        }

        $answers['random'] = $client->get($base.'/webx-audit-'.Str::lower(Str::random(12)));

        $inner = $this->innerPath($home->body, $base, $hosts);

        if ($inner !== null) {
            $answers['inner'] = $client->get($base.$inner);
            $answers['index:'.$inner.'/index.php'] = $client->get($base.$inner.'/index.php');
            $answers['slashes'] = $client->get($base.self::doubled($inner));
            $answers['trailing'] = $client->get($base.(str_ends_with($inner, '/') ? rtrim($inner, '/') : $inner.'/'));
            $answers['case'] = $client->get($base.self::capitalised($inner));
        }

        $assets = $this->assets($home->body, $base, $hosts);

        foreach ($assets as $asset) {
            $answers['asset:'.$asset] = $client->head($asset);
        }

        $certificate = $scheme === 'https'
            ? $this->certificates->read($host, $client->address(), is_int($port) ? $port : 443)
            : null;

        return new ProbeSet($answers, $certificate, $inner, $assets);
    }

    /** `/blog/post` → `/blog//post`; `/about` → `//about`. */
    public static function doubled(string $path): string
    {
        $trimmed = trim($path, '/');
        $at = strpos($trimmed, '/');

        return $at === false ? '//'.$trimmed : '/'.substr($trimmed, 0, $at).'//'.substr($trimmed, $at + 1);
    }

    /** `/about/team` → `/About/team`. */
    public static function capitalised(string $path): string
    {
        return '/'.ucfirst(ltrim($path, '/'));
    }

    /**
     * The first internal link of the home page that looks like a page: a path below the root,
     * no file extension, no query, lowercase ASCII — so the case probe has something to change.
     */
    private function innerPath(string $html, string $base, HostClassifier $hosts): ?string
    {
        foreach ($this->attributes($html, 'a', 'href') as $href) {
            $path = $this->ownPath($href, $base, $hosts);

            if ($path === null || $path === '/' || str_contains($path, '?') || str_contains($path, '#')) {
                continue;
            }

            if (preg_match('~^(/[a-z0-9][a-z0-9-]*)+/?$~', $path) === 1 && preg_match('~\.[a-z0-9]{2,4}$~', $path) !== 1) {
                return $path;
            }
        }

        return null;
    }

    /**
     * The page's own stylesheets, scripts and pictures, absolute.
     *
     * @return list<string>
     */
    private function assets(string $html, string $base, HostClassifier $hosts): array
    {
        $found = [];

        $stylesheets = preg_match_all('~<link\b[^>]*\brel=["\']?stylesheet[^>]*>~i', $html, $links) > 0 ? $links[0] : [];

        foreach ([
            ...$this->attributes(implode('', $stylesheets), 'link', 'href'),
            ...$this->attributes($html, 'script', 'src'),
            ...$this->attributes($html, 'img', 'src'),
        ] as $value) {
            $path = $this->ownPath($value, $base, $hosts);

            if ($path !== null && $path !== '/' && ! str_starts_with($path, '//')) {
                $found[$base.$path] = true;
            }

            if (count($found) >= self::ASSETS) {
                break;
            }
        }

        return array_keys($found);
    }

    /** The path of an address on the site itself, or null for anybody else's. */
    private function ownPath(string $value, string $base, HostClassifier $hosts): ?string
    {
        $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5);

        if ($value === '' || str_starts_with($value, '#') || preg_match('~^[a-z][a-z0-9+.-]*:(?!//)~i', $value) === 1) {
            return null;
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }

        if ($hosts->classify($value) !== HostClassifier::OWN) {
            return null;
        }

        $path = parse_url(str_starts_with($value, '//') ? 'http:'.$value : $value);

        return ($path['path'] ?? '/').(isset($path['query']) ? '?'.$path['query'] : '');
    }

    /**
     * @return list<string>
     */
    private function attributes(string $html, string $tag, string $attribute): array
    {
        $pattern = '~<'.$tag.'\b[^>]*?\b'.$attribute.'\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        return array_map(static fn (array $match): string => (string) ($match[3] ?? '') !== '' ? $match[3] : ((string) ($match[2] ?? '') !== '' ? $match[2] : $match[1]), $matches);
    }
}
