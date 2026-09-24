<?php

declare(strict_types=1);

namespace WebxUi\Faq\Panel;

use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Faq\Demo\FaqDemo;
use WebxUi\Faq\Mcp\FaqResources;
use WebxUi\Faq\Mcp\FaqTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * Where questions are written, ordered and published (§4.5).
 *
 * The panel's usual pair: `faq.view` opens the list, `faq.manage` writes — including the order,
 * which is what a reader of the FAQ sees first.
 *
 * To an agent it is the same section by other doors (§4.7): six tools behind `faq:read` and
 * `faq:write`, and `faq://catalog` to read first. The demo seeds the categories from here too,
 * because the one question in two categories is its point and it takes both halves to show it.
 */
final class QuestionsModule extends FaqGroup implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    /** The id in the panel — and the name `webx:setup` and `webx:blocks:offered --module` know it by. */
    public const ID = 'faq';

    public function __construct(
        private readonly FaqTools $tools,
        private readonly FaqResources $resources,
        private readonly FaqDemo $demo,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-faq::module.questions');
    }

    public function icon(): string
    {
        return 'list';
    }

    public function order(): int
    {
        return 500;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['faq.view', 'faq.manage'];
    }

    /**
     * The block types, and — when they are installed — the pages and services the FAQ block is
     * put into.
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
