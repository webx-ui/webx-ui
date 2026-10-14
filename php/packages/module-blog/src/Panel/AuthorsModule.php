<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use WebxUi\Blog\BlogServiceProvider;
use WebxUi\Blog\Mcp\AuthorTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * «Authors» in the blog's group (§19.5): the profiles — the name a reader sees, a photo, a role,
 * a few lines and whether there is a page.
 *
 * Registered only where the site allows author pages ({@see BlogServiceProvider}):
 * without that, a profile is something nobody will ever see, and a section of them would be a
 * form that changes nothing.
 *
 * A permission of its own rather than the rubrics': whoever may rename a rubric may not by that
 * put a colleague's name and face on the site.
 *
 * To an agent, `authors_list` and `authors_update` (§19.7) — not making a profile, which is a
 * person's decision.
 */
final class AuthorsModule extends BlogModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly AuthorTools $tools) {}

    public function id(): string
    {
        return 'authors';
    }

    public function title(): string
    {
        return (string) __('webx-blog::module.authors');
    }

    public function icon(): string
    {
        return 'user';
    }

    public function order(): int
    {
        return 330;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['blog.authors.manage'];
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return $this->tools->all();
    }
}
