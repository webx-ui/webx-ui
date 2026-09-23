<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Blog\Demo\BlogDemo;
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
final class ArticlesModule extends BlogModule implements ProvidesDemo, ProvidesMcpTools
{
    public function __construct(
        private readonly ArticleTools $tools,
        private readonly BlogResources $resources,
        private readonly BlogPrompts $prompts,
        private readonly BlogDemo $demo,
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
     * The block types an article is written in, and the library its cover comes out of (§9).
     *
     * The whole blog is seeded from here — one rubric, two tags, two articles — rather than a
     * third of it from each of the three sections: it is one thing to look at, and taking it
     * out again has to happen in one order.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return ['blocks', 'media'];
    }

    public function seed(DemoLedger $ledger): void
    {
        $this->demo->seed($ledger);
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
