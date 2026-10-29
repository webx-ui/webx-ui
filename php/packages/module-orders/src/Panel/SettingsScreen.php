<?php

declare(strict_types=1);

namespace WebxUi\Orders\Panel;

use Illuminate\Contracts\Container\Container;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Settings\Settings;

/**
 * The screen `orders.settings` (spec §11.4) as the site has it: the choice of the initial
 * statuses made of the active ones, grouped by what the buyer sees — a deleted or switched-off
 * status is not offered, and the server holds the same list. Built when the tree is built, so a
 * status added a moment ago is there.
 *
 * Without `module-settings` there is nowhere to keep these; the cards give way to a line that
 * says the config `webx-orders` decides.
 */
final readonly class SettingsScreen
{
    public const string NAME = 'orders.settings';

    public function __construct(private Container $container) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function patch(): array
    {
        if (! $this->container->bound(Settings::class)) {
            return [
                ['op' => 'remove', 'target' => 'initial-card'],
                ['op' => 'replace', 'target' => 'number-card', 'node' => [
                    'id' => 'number-card',
                    'type' => 'wx-alert',
                    'props' => ['type' => 'info', 'description' => 'trans::webx-orders::screen.no-settings'],
                ]],
            ];
        }

        $locale = app()->getLocale();
        $options = OrderStatus::query()
            ->select('order_statuses.*')
            ->join('order_public_statuses as groups', 'groups.id', '=', 'order_statuses.public_status_id')
            ->whereNull('groups.deleted_at')
            ->where('order_statuses.is_active', true)
            ->with('publicStatus')
            ->orderBy('groups.position')
            ->orderBy('order_statuses.position')
            ->orderBy('order_statuses.id')
            ->get()
            ->map(static fn (OrderStatus $status): array => [
                'value' => $status->id,
                'label' => $status->publicStatus->getTranslation('name', $locale).' → '.$status->getTranslation('name', $locale),
            ])
            ->values()
            ->all();

        return [
            ['op' => 'set', 'target' => 'initial-status', 'props' => ['options' => $options]],
            ['op' => 'set', 'target' => 'initial-awaiting', 'props' => ['options' => $options]],
        ];
    }
}
