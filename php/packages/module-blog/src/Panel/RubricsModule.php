<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\Mcp\CategoryTools;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * The sections of the blog: flat, ordered by hand, several per article (§2.4, §2.6).
 *
 * To an agent, the tools every module's categories have (§3.7 of the services spec): the list,
 * and — behind `rubrics:write` and the taxonomy permission — create, update, delete and reorder.
 * Filing an article into a rubric is still `articles_update`.
 */
final class RubricsModule extends BlogModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly CategoryForm $form) {}

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
        return (new CategoryTools(Rubric::class, $this->form))->all();
    }
}
