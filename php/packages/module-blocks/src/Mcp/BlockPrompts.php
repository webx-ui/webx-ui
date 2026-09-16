<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Mcp;

use WebxUi\Mcp\Prompt;

/**
 * The one request worth packaging: "make me a block for this". It does not say how to write
 * one — the guidelines do — but it puts the loop in front of the agent so that a first
 * attempt is rendered and checked rather than published.
 */
final class BlockPrompts
{
    /**
     * @return list<Prompt>
     */
    public function all(): array
    {
        return [
            new Prompt(
                'design_block',
                'Design a new block type for this site from a short brief, following the house rules and the loop of render and check.',
                static fn (array $arguments): string => self::design($arguments),
                [
                    'brief' => 'What the block is for, in a sentence or two: what it shows, where it goes, what the editor fills in.',
                    'group' => 'The picker group it belongs to, if you know it.',
                ],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function design(array $arguments): string
    {
        $brief = is_string($arguments['brief'] ?? null) && trim($arguments['brief']) !== ''
            ? trim($arguments['brief'])
            : '(no brief given — ask what the block is for before anything else)';
        $group = is_string($arguments['group'] ?? null) && $arguments['group'] !== ''
            ? " Put it in the `{$arguments['group']}` group."
            : '';

        return <<<TEXT
        Design a block type for this site.

        Brief: {$brief}{$group}

        Work like this:
        1. Read blocks://guidelines, blocks://site and blocks://catalog. If an existing type already
           does this, say so and stop — do not make a near copy.
        2. Create the type with blocks_create: a kebab-case slug, a small schema of screen nodes
           (see blocks://fields), a Blade template whose root carries data-wx-block="{slug}",
           styles where every selector starts with .b-{slug}, a script only if it needs behaviour,
           and a sample that fills every field with believable text in the site's language.
        3. Render it with blocks_render on the sample and again with values {} — read the warnings,
           fix them with blocks_update, render again.
        4. Report the slug, the fields with their labels, and anything you were unsure about.

        Do not publish. A person looks at the draft in the panel first, unless they asked you to publish.
        TEXT;
    }
}
