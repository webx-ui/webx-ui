<?php

declare(strict_types=1);

namespace WebxUi\Blog\Mcp;

use WebxUi\Mcp\Prompt;

/**
 * The one request worth packaging: "write us a piece about this" (§13).
 *
 * The tools each do one thing; what an agent gets wrong is the order, and in a blog it gets one
 * more thing wrong than in a tree of pages. Two of the steps below are there because of it:
 * the body of an article belongs to another module's tool, and the date is the publication — an
 * agent that sets `published_at` while filling in the settings has put a half-written article on
 * the site without ever calling anything named "publish".
 */
final class BlogPrompts
{
    /**
     * @return list<Prompt>
     */
    public function all(): array
    {
        return [
            new Prompt(
                'write_article',
                'Write an article for this blog from a brief: read what the blog already publishes, draft it out '
                .'of the block types the site has, file it under a rubric with tags that already exist, and '
                .'leave it as a draft for a person.',
                static fn (array $arguments): string => self::write($arguments),
                [
                    'brief' => 'What the article is about and what it should cover, in a few sentences.',
                    'rubric' => 'The rubric it belongs in — its slug, "repairs" say; ask rubrics_list if you do not know.',
                    'title' => 'What it is called, if that is already decided.',
                ],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function write(array $arguments): string
    {
        $brief = is_string($arguments['brief'] ?? null) && trim($arguments['brief']) !== ''
            ? trim($arguments['brief'])
            : '(no brief given — ask what the article is for and who reads it before anything else)';
        $rubric = is_string($arguments['rubric'] ?? null) && $arguments['rubric'] !== ''
            ? " File it under `{$arguments['rubric']}`."
            : '';
        $title = is_string($arguments['title'] ?? null) && $arguments['title'] !== ''
            ? " Call it \"{$arguments['title']}\"."
            : '';

        return <<<TEXT
        Write an article for this blog.

        Brief: {$brief}{$rubric}{$title}

        Work like this:
        1. Read `blog://feed` first. It is the last thirty articles as a reader sees them: whether this
           has been written already — if it has, say so and stop rather than publishing it twice — and
           how this blog writes, which is how long a lead runs, whether titles are questions, and what
           kind of thing goes in which rubric. Then `rubrics_list` and `tags_list` for the words that
           exist, and `blocks://catalog` for the block types the site has: build the article out of
           those, and do not invent a type unless the brief cannot be told without one.
        2. Start it with articles_create: a title, and a slug only if the one made from the title is
           wrong. Write it in the language the rest of the blog is written in, and in every language the
           feed lists if you can. An address another article, rubric or page holds is refused — pick a
           different one rather than working around it.
        3. Fill it with blocks_edit_content — `add` one node per block, in the order they should read,
           with the values each type's schema asks for. That tool, not articles_update: the body does
           not travel through the article tools.
        4. Settle the rest with articles_update: the lead, the cover if the brief gave you one, the
           rubrics — the first one is the main one and goes in the breadcrumbs — and tags that already
           exist. Do not invent tags: a blog collects three spellings of one word that way, and
           tags_merge exists because somebody has to untangle them later.
        5. Look at what you made: the `preview_url` articles_get returns is the article as the site
           would print it, and the values it returns are what is actually saved.
        6. Write the SEO card with articles_update — the `seo` field — once the text exists, so the
           title and the description say what the article ended up saying.
        7. Report the address it will answer at, the blocks you used, the rubric and tags you chose,
           and anything the brief did not settle.

        Do not publish, and do not set `published_at`. In this module the date *is* the publication:
        writing one puts the article on the site, or schedules it to appear there, without anything
        named "publish" being called. The article stays a draft until a person has read it — and when
        they ask for it to go out, that is articles_publish, with `at` if they named a day.
        TEXT;
    }
}
