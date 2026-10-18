<?php

declare(strict_types=1);

namespace WebxUi\Admin\Remote;

/**
 * A compressed body undone with a count of what comes out, not of what went in.
 *
 * Left to curl (`decode_content`), a body is decoded before anybody counts it: `Content-Length`
 * and the `progress` callback see the compressed bytes, so 300 KB of gzip become 300 MB in memory
 * or on disk before a limit is looked at. Here the body arrives as it was sent and is inflated a
 * small piece at a time, and the count of decoded bytes stops it — a gzip bomb costs the limit
 * and no more.
 *
 * `gzip`, `x-gzip` and `deflate` are decoded (`deflate` with or without the zlib wrapper, as
 * servers send both); `identity` or no encoding is the body as it is; anything else is not
 * `supports()`ed — the caller asked for none of it.
 */
final class BoundedDecoder
{
    /**
     * Compressed bytes fed to zlib at a time: deflate expands at most ~1032 times, so one piece
     * never decodes to more than a few megabytes before the count is looked at again.
     */
    private const PIECE = 4096;

    private function __construct() {}

    /** Whether the encoding is one this class decodes or passes through. */
    public static function supports(?string $encoding): bool
    {
        return in_array(self::normal($encoding), ['', 'identity', 'gzip', 'x-gzip', 'deflate'], true);
    }

    /** Whether there is anything to undo. */
    public static function encoded(?string $encoding): bool
    {
        return ! in_array(self::normal($encoding), ['', 'identity'], true);
    }

    /**
     * The decoded body, at most `$limit` bytes — with `$truncate` the first `$limit` of them,
     * without it a refusal. Null when the body does not decode.
     *
     * @throws FetchRefused past the limit, unless `$truncate`
     */
    public static function decode(string $body, ?string $encoding, int $limit, bool $truncate = false): ?string
    {
        if (! self::encoded($encoding)) {
            if (strlen($body) > $limit && ! $truncate) {
                throw self::tooLarge();
            }

            return substr($body, 0, $limit);
        }

        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            return null;
        }

        fwrite($stream, $body);
        rewind($stream);
        $out = '';

        try {
            $decoded = self::inflate($stream, $encoding, $limit, $truncate, static function (string $piece) use (&$out): void {
                $out .= $piece;
            });
        } finally {
            fclose($stream);
        }

        return $decoded === null ? null : $out;
    }

    /**
     * The file at `$path` decoded in place, at most `$limit` bytes; the decoded size, or null
     * when it does not decode. On a refusal or null the file is left as it came, for the caller
     * to remove — nothing past the limit is ever written.
     *
     * @throws FetchRefused past the limit
     */
    public static function decodeFile(string $path, ?string $encoding, int $limit): ?int
    {
        if (! self::encoded($encoding)) {
            clearstatcache(true, $path);
            $size = is_file($path) ? (int) filesize($path) : 0;

            if ($size > $limit) {
                throw self::tooLarge();
            }

            return $size;
        }

        $in = @fopen($path, 'rb');
        $temporary = $path.'.decoding';
        $out = @fopen($temporary, 'wb');

        if ($in === false || $out === false) {
            is_resource($in) && fclose($in);
            is_resource($out) && fclose($out);
            @unlink($temporary);

            return null;
        }

        try {
            $size = self::inflate($in, $encoding, $limit, false, static function (string $piece) use ($out): void {
                fwrite($out, $piece);
            });
        } catch (FetchRefused $refused) {
            @unlink($temporary);

            throw $refused;
        } finally {
            fclose($in);
            fclose($out);
        }

        if ($size === null || ! @rename($temporary, $path)) {
            @unlink($temporary);

            return null;
        }

        return $size;
    }

    /**
     * @param  resource  $in
     * @param  callable(string): void  $write
     *
     * @throws FetchRefused
     */
    private static function inflate($in, ?string $encoding, int $limit, bool $truncate, callable $write): ?int
    {
        $first = (string) fread($in, 2);
        rewind($in);

        $format = match (true) {
            str_starts_with($first, "\x1f\x8b") => ZLIB_ENCODING_GZIP,
            // A zlib header: CMF 0x78 for deflate with a 32 KB window, and a checksum that divides.
            strlen($first) === 2 && (ord($first[0]) & 0x0F) === 8 && ((ord($first[0]) << 8) | ord($first[1])) % 31 === 0 => ZLIB_ENCODING_DEFLATE,
            in_array(self::normal($encoding), ['gzip', 'x-gzip'], true) => ZLIB_ENCODING_GZIP,
            default => ZLIB_ENCODING_RAW,
        };

        $context = @inflate_init($format);

        if ($context === false) {
            return null;
        }

        $size = 0;

        while (! feof($in)) {
            $piece = (string) fread($in, self::PIECE);

            if ($piece === '') {
                break;
            }

            $decoded = @inflate_add($context, $piece, ZLIB_SYNC_FLUSH);

            if ($decoded === false) {
                return null;
            }

            if ($size + strlen($decoded) > $limit) {
                if (! $truncate) {
                    throw self::tooLarge();
                }

                $write(substr($decoded, 0, $limit - $size));

                return $limit;
            }

            $size += strlen($decoded);
            $write($decoded);

            if (inflate_get_status($context) === ZLIB_STREAM_END) {
                break;
            }
        }

        return $size;
    }

    private static function tooLarge(): FetchRefused
    {
        return new FetchRefused('The file is larger than this site accepts.', FetchRefused::TOO_LARGE);
    }

    private static function normal(?string $encoding): string
    {
        return strtolower(trim((string) $encoding));
    }
}
