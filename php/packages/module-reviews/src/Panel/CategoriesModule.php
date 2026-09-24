<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Panel;

use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\Mcp\CategoryTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Reviews\Models\ReviewCategory;

/**
 * The categories of reviews: flat, ordered by hand, several per review — the panel's shared
 * category screens with this module's words (`ReviewCategory::categoryKind()`), and no address.
 *
 * Its own permission, for the reason every module's categories have one: the buttons of every
 * filter of reviews on the site are a different job from writing a review down.
 *
 * To an agent, the tools every module's categories have (§3.7 of the services spec): the id is
 * `review-categories`, so they are `review_categories_list`, and behind `review-categories:write`
 * create, update, delete and reorder — without a slug, since these categories have no address.
 * Filing a review into a category is `reviews_update`, and its place inside one is
 * `reviews_reorder`.
 */
final class CategoriesModule extends ReviewsGroup implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly CategoryForm $form) {}

    public function id(): string
    {
        return 'review-categories';
    }

    public function title(): string
    {
        return (string) __('webx-reviews::module.categories');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 610;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [ReviewCategory::MANAGE];
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return (new CategoryTools(ReviewCategory::class, $this->form))->all();
    }
}
