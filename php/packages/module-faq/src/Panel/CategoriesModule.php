<?php

declare(strict_types=1);

namespace WebxUi\Faq\Panel;

use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\Mcp\CategoryTools;
use WebxUi\Faq\Models\FaqCategory;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * The categories of the FAQ: flat, ordered by hand, several per question — the panel's shared
 * category screens with this module's words (`FaqCategory::categoryKind()`), and no address.
 *
 * Its own permission, for the reason every module's categories have one: the buttons of every
 * FAQ filter on the site are a different job from answering a question.
 *
 * To an agent, the tools every module's categories have (§3.7 of the services spec):
 * `faq_categories_list`, and behind `faq-categories:write` create, update, delete and reorder —
 * without a slug, since these categories have no address. Filing a question into a category is
 * `faq_update`, and its place inside one is `faq_reorder`.
 */
final class CategoriesModule extends FaqGroup implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly CategoryForm $form) {}

    public function id(): string
    {
        return 'faq-categories';
    }

    public function title(): string
    {
        return (string) __('webx-faq::module.categories');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 510;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [FaqCategory::MANAGE];
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return (new CategoryTools(FaqCategory::class, $this->form))->all();
    }
}
