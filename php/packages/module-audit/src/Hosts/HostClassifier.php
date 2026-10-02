<?php

declare(strict_types=1);

namespace WebxUi\Audit\Hosts;

/**
 * Which side of the fence an absolute address is on (decision 12, §5.6): the site itself, the
 * site through its other mirror, a development stand, or somebody else.
 *
 * A stand is any host an administrator listed as another address of this site — dev, stage, the
 * old domain — and whatever has to be one: loopback and private addresses, the reserved zones,
 * and a first label such as `dev` or `stage`. The site's own host is never a stand, whatever it
 * looks like: a local run on `shop.local` would otherwise call the whole site an error.
 *
 * Shared on purpose: the editor's warning on save (§7, `module-admin`, later) is meant to be this
 * same class, so the panel and the audit never disagree about what a stand is.
 */
final class HostClassifier
{
    public const OWN = 'own';

    public const OWN_MIRROR = 'own_mirror';

    public const DEV = 'dev';

    public const EXTERNAL = 'external';

    /** @var list<string> */
    private array $own;

    /** @var list<string> */
    private array $others;

    /**
     * @param  list<string>  $own  The site's hosts: the one the run reaches and APP_URL's.
     * @param  list<string>  $others  "Other addresses of this site" — hosts or whole addresses.
     * @param  list<string>  $zones  Top-level zones that are always a stand.
     * @param  list<string>  $words  First labels that are always a stand.
     */
    public function __construct(
        array $own,
        array $others = [],
        private readonly array $zones = [],
        private readonly array $words = [],
    ) {
        $this->own = array_values(array_unique(array_filter(array_map(self::normalise(...), $own))));
        $this->others = array_values(array_unique(array_filter(array_map(self::normalise(...), $others))));
    }

    /** The class of an address, or null for one that names no host (relative, `mailto:`). */
    public function classify(string $url): ?string
    {
        $host = self::hostOf($url);

        return $host === null ? null : $this->classifyHost($host);
    }

    public function classifyHost(string $host): string
    {
        $host = self::normalise($host);

        if (in_array($host, $this->own, true)) {
            return self::OWN;
        }

        foreach ($this->own as $own) {
            if (self::mirrorOf($own) === $host) {
                return self::OWN_MIRROR;
            }
        }

        if (in_array($host, $this->others, true) || $this->looksLikeStand($host)) {
            return self::DEV;
        }

        return self::EXTERNAL;
    }

    /**
     * Whether a host has to be a stand by its shape alone — the own-host exemption left out, which
     * is what the production-only config checks want to know about the site itself.
     */
    public function looksLikeStand(string $host): bool
    {
        $host = self::normalise($host);

        if ($host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $host === '::1'
                || str_starts_with($host, '127.')
                || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        $labels = explode('.', $host);

        if (in_array(end($labels), $this->zones, true) || $host === 'localhost') {
            return true;
        }

        // Three labels at least: `dev.shop.com` is a stand, `demo.com` is somebody's domain.
        return count($labels) >= 3 && in_array($labels[0], $this->words, true);
    }

    /** The host of an absolute or protocol-relative address, lowercased. */
    public static function hostOf(string $url): ?string
    {
        $url = trim($url);

        if (str_starts_with($url, '//')) {
            $url = 'http:'.$url;
        }

        if (preg_match('~^https?://~i', $url) !== 1) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? self::normalise($host) : null;
    }

    /** `www.shop.com` for `shop.com` and the other way round. */
    public static function mirrorOf(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host;
    }

    /** A host, or a whole address an administrator pasted, as a bare lowercase host. */
    private static function normalise(string $value): string
    {
        $value = strtolower(trim($value));

        if ($value === '') {
            return '';
        }

        if (str_contains($value, '/')) {
            $value = self::hostOf(str_contains($value, '://') || str_starts_with($value, '//') ? $value : '//'.$value) ?? '';
        }

        return rtrim(trim($value, '[]'), '.');
    }
}
