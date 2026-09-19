<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use WebxUi\Blog\Mcp\RubricTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * The sections of the blog: flat, ordered by hand, several per article (§2.4, §2.6).
 *
 * To an agent, one tool and it only looks (§13). A rubric is navigation, and deciding the site
 * has a ninth section is not a thing to do while writing an article — the scope is
 * `rubrics:read` and there is no `rubrics:write` to hold.
 */
final class RubricsModule extends BlogModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly RubricTools $tools) {}

    public function id(): string
    {
        return 'rubrics';
    }

    public function title(): string
    {
        return (string) __('webx-blog::module.rubrics');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 310;
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
