<?php

declare(strict_types=1);

namespace WebxUi\Pages\Mcp;

use WebxUi\Mcp\Prompt;

/**
 * The one request worth packaging: "make me a page about this" (§13).
 *
 * The tools each do one thing; what an agent gets wrong is the order. A page is started, then
 * filled with blocks that already exist, then looked at, and only then published by a person —
 * and the second step belongs to another module's tool, which is precisely the thing a first
 * attempt does not guess.
 */
final class PagePrompts
{
    /**
     * @return list<Prompt>
     */
    public function all(): array
    {
        return [
            new Prompt(
                'build_page',
                'Build a page of this site from a brief: start it in the right place, fill it with the block '
                .'types the site already has, and leave it as a draft for a person to look at.',
                static fn (array $arguments): string => self::build($arguments),
                [
                    'brief' => 'What the page is for and what should be on it, in a few sentences.',
                    'parent' => 'The address of the page it goes under, "/catalog" say; the home page when you do not know.',
                    'title' => 'What the page is called, if it is already decided.',
                ],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function build(array $arguments): string
    {
        $brief = is_string($arguments['brief'] ?? null) && trim($arguments['brief']) !== ''
            ? trim($arguments['brief'])
            : '(no brief given — ask what the page is for before anything else)';
        $parent = is_string($arguments['parent'] ?? null) && $arguments['parent'] !== ''
            ? " It goes under `{$arguments['parent']}`."
            : '';
        $title = is_string($arguments['title'] ?? null) && $arguments['title'] !== ''
            ? " Call it \"{$arguments['title']}\"."
            : '';

        return <<<TEXT
        Build a page for this site.

        Brief: {$brief}{$parent}{$title}

        Work like this:
        1. Read pages://sitemap to see where the page belongs and whether something like it is already
           there — if it is, say so and stop rather than making a second one. Read blocks://catalog for
           the block types this site has: build the page out of those, and do not invent a type unless
           the brief cannot be told without one.
        2. Start it with pages_create: a title, and a slug only if the one made from the title is wrong.
           Write the title in the language the rest of the site is written in, and in every language the
           sitemap lists if you can.
        3. Fill it with blocks_edit_content — `add` one node per block, in the order they should read,
           with the values each type's schema asks for. That tool, not pages_update: content does not
           travel through the page tools.
        4. Look at what you made: blocks_preview_url gives a link to the page as the site would print
           it, and pages_get shows the values that are actually saved.
        5. Write the SEO card with pages_update — the `seo` field — once the text exists, so the title
           and description say what the page ended up saying.
        6. Report the address it will answer at, the blocks you used and anything the brief did not
           settle.

        Do not publish. The page stays a draft until a person has read it, unless they asked you to
        publish it.
        TEXT;
    }
}
