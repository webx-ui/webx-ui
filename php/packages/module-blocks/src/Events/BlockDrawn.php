<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Events;

/**
 * A block drawn on its own for the panel — the editor's stage, a block swapped into the preview
 * of a page, `blocks_render` — that goes into a page printed before it existed.
 *
 * A page gathers what its content needs once it has rendered: the widgets a component claimed
 * get their stylesheets and scripts (`webx-ui/widgets`, `Widgets::finish()`). A block drawn
 * alone, answered as JSON, was never such a page, and the map in it came without its look. So
 * whoever gathers for pages gathers for this piece too: rewrites `html` if it must and adds
 * the tags the panel puts into the page — `head` to `<head>`, `body` before `</body>`. The
 * panel skips what the page already has.
 */
final class BlockDrawn
{
    /** @var list<string> */
    public array $head = [];

    /** @var list<string> */
    public array $body = [];

    public function __construct(public string $html) {}

    /** @return array{head: list<string>, body: list<string>} */
    public function assets(): array
    {
        return ['head' => array_values($this->head), 'body' => array_values($this->body)];
    }
}
