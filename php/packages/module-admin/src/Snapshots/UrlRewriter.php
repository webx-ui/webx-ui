<?php

declare(strict_types=1);

namespace WebxUi\Admin\Snapshots;

/**
 * The source stand's address in content becomes this stand's.
 *
 * An editor who pasted a link on local wrote `http://site.local/about` into a page; on dev that
 * link has to say dev. Only absolute addresses with `//` in front are touched — the bare host
 * also occurs in e-mail addresses and in prose, and those are not addresses of the stand. The
 * JSON-escaped spelling (`https:\/\/site.local`) is rewritten too: translated fields and block
 * content are stored as JSON, and `json_encode` escapes slashes by default.
 *
 * Not the audit's host classifier: that one guesses which hosts in content are development ones
 * on a site that does not know where its content came from. A restore knows exactly — the
 * manifest says it — so there is nothing to guess.
 */
final class UrlRewriter
{
    private ?string $pattern = null;

    private string $scheme = 'https:';

    private string $authority = '';

    public function __construct(string $from, string $to)
    {
        $source = parse_url($from);
        $target = parse_url($to);

        if (! is_array($source) || ! is_array($target) || ! isset($source['host'], $target['host'])) {
            return;
        }

        $sourceAuthority = strtolower($source['host']).(isset($source['port']) ? ':'.$source['port'] : '');
        $this->authority = strtolower($target['host']).(isset($target['port']) ? ':'.$target['port'] : '');
        $this->scheme = ($target['scheme'] ?? 'https').':';

        if ($sourceAuthority === $this->authority && ($source['scheme'] ?? '') === ($target['scheme'] ?? '')) {
            return;
        }

        // A source without a port also takes the ports written after it: `site.local:8000` is
        // the same development stand.
        $port = isset($source['port']) ? '' : '(?::\d+)?';

        $this->pattern = '~(https?:)?(//|\\\\/\\\\/)'.preg_quote($sourceAuthority, '~').$port.'(?![A-Za-z0-9.\-])~i';
    }

    public function active(): bool
    {
        return $this->pattern !== null;
    }

    public function rewrite(string $value): string
    {
        if ($this->pattern === null || stripos($value, '/') === false) {
            return $value;
        }

        return (string) preg_replace_callback($this->pattern, function (array $match): string {
            $scheme = $match[1] === '' ? '' : $this->scheme;

            return $scheme.$match[2].$this->authority;
        }, $value);
    }
}
