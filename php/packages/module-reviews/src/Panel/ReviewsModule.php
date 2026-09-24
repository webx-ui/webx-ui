<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Panel;

use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Reviews\Demo\ReviewsDemo;
use WebxUi\Reviews\Mcp\ReviewsResources;
use WebxUi\Reviews\Mcp\ReviewsTools;

/**
 * Where reviews are written, ordered and published (§4.6).
 *
 * The panel's usual pair: `reviews.view` opens the list, `reviews.manage` writes — including the
 * order, which is what a reader of a block of reviews sees first.
 *
 * To an agent it is the same section by other doors (§4.8): six tools behind `reviews:read` and
 * `reviews:write`, and `reviews://catalog` to read first. The demo seeds the categories from here
 * too, because the one review in two categories is its point and it takes both halves to show it.
 */
final class ReviewsModule extends ReviewsGroup implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    /** The id in the panel — and the name `webx:setup` and `webx:blocks:offered --module` know it by. */
    public const ID = 'reviews';

    public function __construct(
        private readonly ReviewsTools $tools,
        private readonly ReviewsResources $resources,
        private readonly ReviewsDemo $demo,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-reviews::module.reviews');
    }

    public function icon(): string
    {
        return 'list';
    }

    public function order(): int
    {
        return 600;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['reviews.view', 'reviews.manage'];
    }

    /**
     * The block types, and — when they are installed — the pages and services the reviews block
     * is put into.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return $this->demo->requires();
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
}
