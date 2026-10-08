<?php

declare(strict_types=1);

namespace WebxUi\Admin\Shortcodes;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;
use Stringable;
use Throwable;

/**
 * Shortcodes: `[phone]`, `[dot]`, `[phone format=intl]` — a name in brackets that content keeps
 * as it was typed and the site prints as something else.
 *
 * What it is for. A phone number typed into forty blocks goes stale in thirty-nine of them; one
 * typed as `[phone]` changes everywhere when the setting does. A heading that needs an accented
 * full stop gets it from `[dot]` instead of a `<span>` an editor has to keep intact. Content
 * stores the bracket, never the result, so nothing is rewritten when a value changes.
 *
 * Where they come from. A site registers its own from a provider — `Shortcodes::register('dot',
 * '<span class="accent-dot">.</span>', plain: '.')` — and a package adds a source that answers
 * with a list on demand: the settings section defines data shortcodes in the panel (a name and
 * the setting or value it reads), so a new one needs no developer. A name registered in code wins
 * over one from a source: what the site's templates and CSS were written against stays put.
 *
 * The rules, the same everywhere:
 * - Only a registered name is replaced. `[1]`, `[sic]` and a typo stay as they were typed —
 *   the audit reports the typos ({@see self::names()}).
 * - `[[phone]]` is the literal `[phone]`, for a page that has to show the bracket itself.
 * - In HTML ({@see self::html()}) the editor's text is escaped and only the shortcode's own HTML
 *   is printed raw: the shortcode never makes the text around it trusted, and its output is
 *   never escaped a second time.
 * - In text ({@see self::plain()}) every shortcode prints its plain rendering and nothing is
 *   escaped — the caller prints text the way that place prints text.
 */
final class Shortcodes
{
    /** What a name may be: what fits in a bracket without being mistaken for prose. */
    public const NAME = '/^[a-z][a-z0-9_-]{0,63}$/';

    /**
     * A bracket with a name and optional `key=value` arguments, with one more bracket on either
     * side captured so that `[[name]]` can be told apart from `[name]`.
     */
    private const PATTERN = '/(\[?)\[([a-z][a-z0-9_-]{0,63})((?:\s+[a-z][a-z0-9_-]*=(?:"[^"\]]*"|\'[^\'\]]*\'|[^\s\]"\']+))*)\s*\](\]?)/';

    private const ARG = '/([a-z][a-z0-9_-]*)=(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+))/';

    /** Elements whose text is shown as written: a page about shortcodes shows them there. */
    private const VERBATIM = ['code', 'pre', 'script', 'style', 'textarea'];

    /** @var array<string, Shortcode> */
    private array $registered = [];

    /** @var list<Closure(): iterable<Shortcode>> */
    private array $sources = [];

    /**
     * A shortcode of the site's: its HTML, and its text where HTML cannot go.
     *
     * @param  Closure(array<string, string>): string|string  $html  Trusted HTML, printed as it is.
     * @param  Closure(array<string, string>): string|string|null  $plain  Left out: the HTML without its tags.
     */
    public function register(string $name, Closure|string $html, Closure|string|null $plain = null, ?string $description = null): void
    {
        if (preg_match(self::NAME, $name) !== 1) {
            throw new InvalidArgumentException(sprintf('A shortcode name is lowercase letters, digits, "-" and "_", starting with a letter; "%s" is not.', $name));
        }

        $this->registered[$name] = new Shortcode($name, $html, $plain, $description);
    }

    /**
     * Shortcodes worked out when they are needed rather than at boot — the ones the panel
     * defines, which live in the settings and change without a deploy.
     *
     * @param  Closure(): iterable<Shortcode>  $source
     */
    public function source(Closure $source): void
    {
        $this->sources[] = $source;
    }

    /**
     * Every shortcode by name: the sources' first, then the registered ones over them.
     *
     * A source that cannot answer — the settings table before its migration has run — adds
     * nothing rather than taking the page down with it.
     *
     * @return array<string, Shortcode>
     */
    public function all(): array
    {
        $all = [];

        foreach ($this->sources as $source) {
            try {
                foreach ($source() as $shortcode) {
                    if (preg_match(self::NAME, $shortcode->name) === 1) {
                        $all[$shortcode->name] = $shortcode;
                    }
                }
            } catch (Throwable) {
                continue;
            }
        }

        $all = [...$all, ...$this->registered];
        ksort($all);

        return $all;
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    public function get(string $name): ?Shortcode
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * What the panel and agents are shown: every shortcode with both its renderings.
     *
     * @return list<array{name: string, description: string|null, html: string, plain: string, origin: string}>
     */
    public function list(): array
    {
        return array_values(array_map(static fn (Shortcode $shortcode): array => $shortcode->toArray(), $this->all()));
    }

    /**
     * An editor's plain text as HTML: escaped, with every shortcode in it printed as its HTML.
     *
     * A value already resolved ({@see ShortcodeText}) is its HTML as it is.
     */
    public function html(string|Stringable|null $text): string
    {
        if ($text instanceof Htmlable) {
            return $text->toHtml();
        }

        return $this->walk((string) $text, e(...), static fn (Shortcode $code, array $args): string => $code->html($args))[0];
    }

    /**
     * Shortcodes inside HTML that is already trusted — a rich text field, sanitised when it was
     * stored. Only the text between tags is read: a bracket in an attribute stays as written,
     * and so does one inside `<code>` or `<pre>`, where a page shows what to type.
     */
    public function htmlIn(?string $html): string
    {
        $html ??= '';

        if (! str_contains($html, '[')) {
            return $html;
        }

        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $verbatim = 0;
        $out = '';

        foreach ($parts as $part) {
            if (str_starts_with($part, '<')) {
                if (preg_match('/^<(\/?)([a-z][a-z0-9]*)/i', $part, $tag) === 1 && in_array(strtolower($tag[2]), self::VERBATIM, true)) {
                    $verbatim = max(0, $verbatim + ($tag[1] === '/' ? -1 : 1));
                }

                $out .= $part;

                continue;
            }

            if ($verbatim > 0 || ! str_contains($part, '[')) {
                $out .= $part;

                continue;
            }

            // Read as the text it shows, so that `format=&quot;intl&quot;` is the argument it
            // looks like; written back escaped. A segment with nothing to replace is left byte
            // for byte as it was.
            $text = html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            [$resolved, $changed] = $this->walk($text, e(...), static fn (Shortcode $code, array $args): string => $code->html($args));
            $out .= $changed ? $resolved : $part;
        }

        return $out;
    }

    /**
     * Text with every shortcode in it as its plain rendering. Nothing is escaped: this is for a
     * title, a meta description, JSON-LD, a mail — whatever prints it escapes it its own way.
     */
    public function plain(string|Stringable|null $text): string
    {
        if ($text instanceof ShortcodeText) {
            return $text->plain();
        }

        return $this->walk((string) $text, static fn (string $literal): string => $literal, static fn (Shortcode $code, array $args): string => $code->plain($args))[0];
    }

    /**
     * Plain text from HTML: the tags out, the entities read, the shortcodes as text. What an
     * excerpt, a description or a search index takes from a rich text field.
     */
    public function text(string|Stringable|null $html): string
    {
        if ($html instanceof ShortcodeText) {
            return $html->plain();
        }

        return $this->plain(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * An editor's text the way a template wants it: the same string when there is nothing in it
     * to replace, and a {@see ShortcodeText} when there is — HTML to Blade's `{{ }}`, which does
     * not escape it again, with the text and the plain rendering still to hand.
     */
    public function resolve(?string $text): string|ShortcodeText|null
    {
        if ($text === null || ! str_contains($text, '[')) {
            return $text;
        }

        [$html, $changed] = $this->walk($text, e(...), static fn (Shortcode $code, array $args): string => $code->html($args));

        return $changed ? new ShortcodeText($text, $html, $this->plain($text)) : $text;
    }

    /**
     * Any text as a {@see ShortcodeText}: one already resolved as it is, a string resolved — and
     * one with nothing in it to replace wrapped too, escaped, so that a template changing a field
     * has one kind of value whichever arrived. What `wx_text()` is.
     */
    public function wrap(string|Stringable|null $text): ShortcodeText
    {
        if ($text instanceof ShortcodeText) {
            return $text;
        }

        $text = (string) $text;
        $resolved = $this->resolve($text);

        return $resolved instanceof ShortcodeText ? $resolved : new ShortcodeText($text, e($text), $text);
    }

    /**
     * Every bracket in a text that reads as a shortcode, registered or not — what the audit
     * looks through for typos.
     *
     * @return list<array{name: string, args: array<string, string>, known: bool, escaped: bool, raw: string}>
     */
    public function names(?string $text): array
    {
        if ($text === null || ! str_contains($text, '[')) {
            return [];
        }

        preg_match_all(self::PATTERN, $text, $matches, PREG_SET_ORDER);
        $all = $this->all();
        $found = [];

        foreach ($matches as $match) {
            $found[] = [
                'name' => $match[2],
                'args' => $this->args($match[3]),
                'known' => isset($all[$match[2]]),
                'escaped' => $match[1] === '[' && $match[4] === ']',
                'raw' => trim($match[0], '[]'),
            ];
        }

        return $found;
    }

    /**
     * The one walk all of the above share: the literal stretches through `$literal`, each known
     * shortcode through `$code`, and whether anything was replaced at all.
     *
     * @param  Closure(string): string  $literal
     * @param  Closure(Shortcode, array<string, string>): string  $code
     * @return array{0: string, 1: bool}
     */
    private function walk(string $text, Closure $literal, Closure $code): array
    {
        if (! str_contains($text, '[') || preg_match_all(self::PATTERN, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return [$literal($text), false];
        }

        $all = $this->all();
        $out = '';
        $position = 0;
        $changed = false;

        foreach ($matches as $match) {
            [$whole, $offset] = $match[0];
            $shortcode = $all[$match[2][0]] ?? null;

            // Not ours: it stays in the stretch of text around it, escaped with it.
            if ($shortcode === null) {
                continue;
            }

            $changed = true;
            $open = $match[1][0] === '[';
            $close = $match[4][0] === ']';
            $out .= $literal(substr($text, $position, $offset - $position));

            if ($open && $close) {
                // `[[name]]`: the bracket itself, as text.
                $out .= $literal(substr($whole, 1, -1));
            } else {
                $out .= $literal($open ? '[' : '').$code($shortcode, $this->args($match[3][0])).$literal($close ? ']' : '');
            }

            $position = $offset + strlen($whole);
        }

        return [$out.$literal(substr($text, $position)), $changed];
    }

    /**
     * @return array<string, string>
     */
    private function args(string $source): array
    {
        if (trim($source) === '') {
            return [];
        }

        preg_match_all(self::ARG, $source, $matches, PREG_SET_ORDER);
        $args = [];

        foreach ($matches as $match) {
            $args[$match[1]] = ($match[2] ?? '') !== '' ? $match[2] : (($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? ''));
        }

        return $args;
    }
}
