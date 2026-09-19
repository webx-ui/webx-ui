<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use WebxUi\Blog\Mcp\TagTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * Tags: entered from the article's own form by the hundred, and raked through here — renamed,
 * merged, opened to the index or kept out of it (§2.8, §12).
 *
 * The permission is the one the rubrics use. Somebody who may rename a rubric may rename a tag:
 * it is the same job, and two permissions would be two places to forget.
 *
 * To an agent, two tools (§13): the list, sorted by use so that duplicates stand next to the
 * word they duplicate, and the merge that puts them back together. Raking a table of four
 * hundred words is exactly the job a person puts off, so `tags:write` exists — and covers one
 * irreversible operation and nothing else.
 */
final class TagsModule extends BlogModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly TagTools $tools) {}

    public function id(): string
    {
        return 'tags';
    }

    public function title(): string
    {
        return (string) __('webx-blog::module.tags');
    }

    public function icon(): string
    {
        return 'tag';
    }

    public function order(): int
    {
        return 320;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['blog.taxonomy.manage'];
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return $this->tools->all();
    }
}
