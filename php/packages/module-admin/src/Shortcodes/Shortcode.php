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
 *
 * A block shortcode prints a section of a page rather than a few words in a line. It only exists in
 * a rich text ({@see Shortcodes::htmlIn()}), which takes it out of the paragraph it was typed in; a
 * line of text prints nothing for it, and neither does plain text unless it says otherwise.
 */
final readonly class Shortcode
{
    /**
     * @param  Closure(array<string, string>): string|string  $html
     * @param  Closure(array<string, string>): string|string|null  $plain
     * @param  string  $origin  `code` for one a provider registered, `settings` for one the panel defines.
     * @param  bool  $block  A section of a page, not words in a line.
     * @param  (Closure(): iterable<array{insert: string, description: string|null}>)|null  $menu  What the panel's `[` offers in its place: ready brackets with their arguments.
     */
    public function __construct(
        public string $name,
        private Closure|string $html,
        private Closure|string|null $plain = null,
        public ?string $description = null,
        public string $origin = 'code',
        public bool $block = false,
        private ?Closure $menu = null,
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
            // The words of a whole section are no sentence, and working them out costs a render.
            if ($this->block) {
                return '';
            }

            return html_entity_decode(strip_tags($this->html($args)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return is_string($this->plain) ? $this->plain : (string) ($this->plain)($args);
    }

    /**
     * What the panel and agents are shown about it.
     *
     * @return array{name: string, description: string|null, html: string, plain: string, origin: string, block: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'html' => $this->html(),
            'plain' => $this->plain(),
            'origin' => $this->origin,
            'block' => $this->block,
        ];
    }

    /**
     * The rows of the panel's `[`: itself, or — one that is nothing without its arguments — a ready
     * bracket for each thing it can print. Nothing to print is no row at all: the bare name would
     * insert a bracket that prints nothing.
     *
     * @return list<array{name: string, description: string|null, html: string, plain: string, origin: string, block: bool, insert: string|null}>
     */
    public function menu(): array
    {
        if ($this->menu === null) {
            return [[...$this->toArray(), 'insert' => null]];
        }

        $rows = [];

        foreach (($this->menu)() as $row) {
            $rows[] = [
                'name' => $this->name,
                'description' => $row['description'],
                'html' => '',
                'plain' => '',
                'origin' => $this->origin,
                'block' => $this->block,
                'insert' => $row['insert'],
            ];
        }

        return $rows;
    }
}
