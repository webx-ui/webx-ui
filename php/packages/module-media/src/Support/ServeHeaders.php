<?php

declare(strict_types=1);

namespace WebxUi\Media\Support;

/**
 * The headers every file a person put into the library is answered with, whoever serves it.
 *
 * `nosniff` on all of them: a browser that second-guesses the type turns a text file that looks
 * like HTML into a page. An SVG also gets a policy that lets it draw and nothing else — no
 * script, no request out, a sandbox — so even one that slipped past the sanitizer, or was
 * uploaded before there was one, cannot act on the site's origin. A type a browser would render
 * as a document of its own goes out as a download.
 *
 * The same rules for the web server in front of the disk are in the module's README and in
 * `php/site/docker/nginx.conf`; this class is for responses PHP writes itself.
 */
final class ServeHeaders
{
    public const SVG_POLICY = "default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox";

    /** Shown in the browser as what they are, not as a page: everything else is a download. */
    private const INLINE = ['image/', 'video/', 'audio/', 'application/pdf', 'text/plain', 'text/csv'];

    /**
     * @return array<string, string>
     */
    public static function for(string $mime): array
    {
        $headers = ['X-Content-Type-Options' => 'nosniff'];

        if ($mime === FileTypes::SVG) {
            $headers['Content-Security-Policy'] = self::SVG_POLICY;
        }

        return $headers;
    }

    /** `inline` or `attachment` — what `Filesystem::response()` takes, so the file name stays. */
    public static function disposition(string $mime): string
    {
        foreach (self::INLINE as $safe) {
            if (str_starts_with($mime, $safe)) {
                return 'inline';
            }
        }

        return 'attachment';
    }
}
