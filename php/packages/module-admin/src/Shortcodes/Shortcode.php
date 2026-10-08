<?php

declare(strict_types=1);

namespace WebxUi\Admin\Shortcodes;

use Closure;

/**
 * One shortcode: a name an editor types in brackets, and the two things it becomes.
 *
 * Two renderings because a page has two kinds of place for text. The body of a page takes HTML —
 * `[dot]` is a span the site colours, `[phone]` a link that dials. A `<title>`, a meta
 * description, JSON-LD and a mail's subject take text, and a span there is either printed as
 * tags or stripped to nothing. So every shortcode says what it is in plain words as well; one
 * that does not is its HTML with the tags taken out.
 *
 * The HTML is trusted: it is written by the site's developer or built here from a setting, and
 * printed as it is. The text around it is the editor's and is escaped ({@see Shortcodes::html()}).
 */
final readonly class Shortcode
{
    /**
     * @param  Closure(array<string, string>): string|string  $html
     * @param  Closure(array<string, string>): string|string|null  $plain
     * @param  string  $origin  `code` for one a provider registered, `settings` for one the panel defines.
     */
    public function __construct(
        public string $name,
        private Closure|string $html,
        private Closure|string|null $plain = null,
        public ?string $description = null,
        public string $origin = 'code',
    ) {}

    /**
     * @param  array<string, string>  $args
     */
    public function html(array $args = []): string
    {
        return is_string($this->html) ? $this->html : (string) ($this->html)($args);
    }

    /**
     * @param  array<string, string>  $args
     */
    public function plain(array $args = []): string
    {
        if ($this->plain === null) {
            return html_entity_decode(strip_tags($this->html($args)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return is_string($this->plain) ? $this->plain : (string) ($this->plain)($args);
    }

    /**
     * What the panel and agents are shown about it.
     *
     * @return array{name: string, description: string|null, html: string, plain: string, origin: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'html' => $this->html(),
            'plain' => $this->plain(),
            'origin' => $this->origin,
        ];
    }
}
