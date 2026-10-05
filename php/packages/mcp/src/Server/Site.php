<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Server;

use Illuminate\Container\Container;

/**
 * Which site this server is the panel of.
 *
 * Every panel serves the same tools under the same names, so a client connected to three
 * sites has three servers that look alike in every way but this one. The name is what a
 * client shows in its list of connectors and what an agent reads first, so it is the address
 * of the site — `example.com` — rather than the product's: nobody has to tell "WebX UI" from
 * "WebX UI".
 */
final class Site
{
    /** `webx-mcp.name` when set, then the host of `app.url`, then `app.name`. */
    public static function name(): string
    {
        $config = Container::getInstance()->make('config');

        foreach ([$config->get('webx-mcp.name'), self::host(), $config->get('app.name')] as $name) {
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return 'WebX UI';
    }

    public static function url(): ?string
    {
        $url = Container::getInstance()->make('config')->get('app.url');

        return is_string($url) && $url !== '' ? rtrim($url, '/') : null;
    }

    /** The host alone: `www.` is the same site, and a port is not part of its name. */
    public static function host(): ?string
    {
        $host = parse_url((string) self::url(), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    public static function environment(): string
    {
        $environment = Container::getInstance()->make('config')->get('app.env');

        return is_string($environment) && $environment !== '' ? $environment : 'production';
    }
}
