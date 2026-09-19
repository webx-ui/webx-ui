<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use WebxUi\Blog\Mcp\ArticleTools;
use WebxUi\Blog\Mcp\BlogPrompts;
use WebxUi\Blog\Mcp\BlogResources;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Prompt;
use WebxUi\Mcp\Tool;

/**
 * Where articles are written and published.
 *
 * Two permissions, the panel's usual pair, named after the blog rather than after this section:
 * `blog.articles.view` opens the list, `blog.articles.manage` writes (§14). Rubrics and tags
 * share `blog.taxonomy.manage` between them, because somebody who may rename a rubric may
 * rename a tag — they are the same job.
 *
 * To an agent it is the same section by other doors (§13): seven tools, the feed to read first,
 * and one prompt. The scopes are `articles:read` and `articles:write`. The feed and the prompt
 * hang off this module rather than off a fourth one, because a blog has one front page and the
 * section that writes it is this one.
 */
final class ArticlesModule extends BlogModule implements ProvidesMcpTools
{
    public function __construct(
        private readonly ArticleTools $tools,
        private readonly BlogResources $resources,
        private readonly BlogPrompts $prompts,
    ) {}

    public function id(): string
    {
        return 'articles';
    }

    public function title(): string
    {
        return (string) __('webx-blog::module.articles');
    }

    public function icon(): string
    {
        return 'file-text';
    }

    public function order(): int
    {
        return 300;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['blog.articles.view', 'blog.articles.manage'];
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return $this->tools->all();
    }

    /**
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        return $this->resources->all();
    }

    /**
     * @return list<Prompt>
     */
    public function mcpPrompts(): array
    {
        return $this->prompts->all();
    }
}
