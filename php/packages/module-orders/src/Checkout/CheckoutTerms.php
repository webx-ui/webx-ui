<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Orders;
use WebxUi\Routing\RouteTypes;

/**
 * The agreement with the terms (spec §23.4): which version of them the buyer agreed with, and
 * the line of it in the order's history — the record, there is no column (decision 67).
 *
 * A page of the site has the number of its published version — its text is in the history of the
 * page, the checkout keeps no copy. An address outside has no version: the address is what the
 * buyer agreed with. A new version unticks the box (§21.1).
 */
final readonly class CheckoutTerms
{
    public function __construct(
        private CheckoutSettings $settings,
        private Container $container,
    ) {}

    /** Whether the buyer has to agree at all. */
    public function asked(): bool
    {
        return $this->settings->terms() !== CheckoutSettings::TERMS_NONE;
    }

    /** The box stays ticked while the terms are the version the buyer agreed with. */
    public function accepted(?CheckoutDraft $draft, string $locale): bool
    {
        return $draft?->terms_accepted_at !== null && $draft->terms_version === $this->version($locale);
    }

    /** The buyer agrees now — with the box, or by pressing the button under the notice. */
    public function accept(CheckoutDraft $draft, string $locale): void
    {
        if (! $this->asked() || $this->accepted($draft, $locale)) {
            return;
        }

        $draft->terms_accepted_at = Carbon::now();
        $draft->terms_version = $this->version($locale);
    }

    /**
     * The version of the terms as the draft keeps it: `<entity>:<id>:<number>` for a page of the
     * site, the address for one outside; null — no terms page set.
     */
    public function version(string $locale): ?string
    {
        foreach ($this->documents($locale) as $document) {
            if ($document['key'] === 'terms') {
                return Str::limit($document['number'] !== null
                    ? $document['entity_type'].':'.$document['entity_id'].':'.$document['number']
                    : $document['url'], 64, '');
            }
        }

        return null;
    }

    /**
     * A line in the order's history a document, in the order's language — inside the transaction
     * of the order (§21.2): an order without its record does not come to be.
     */
    public function record(Order $order, ?CheckoutDraft $draft, Orders $orders, CarbonImmutable $at): void
    {
        if (! $this->asked()) {
            return;
        }

        $time = ($draft->terms_accepted_at ?? $at)->toImmutable()->utc()->format('Y-m-d H:i:s').' UTC';

        foreach ($this->documents($order->locale) as $document) {
            $orders->note($order, (string) __('webx-orders::checkout.history.terms', [
                'title' => $document['title'],
                'url' => $document['url'],
                'version' => $document['number'] !== null
                    ? (string) $document['number']
                    : (string) __('webx-orders::checkout.history.version-unknown', [], $order->locale),
                'time' => $time,
            ], $order->locale), 'checkout');
        }
    }

    /** @return list<array{key: string, title: string, url: string, entity_type: string|null, entity_id: int|null, number: int|null}> */
    private function documents(string $locale): array
    {
        return array_map(fn (array $document): array => [
            ...$document,
            'number' => $document['entity_type'] !== null && $document['entity_id'] !== null
                ? $this->published($document['entity_type'], $document['entity_id'])
                : null,
        ], $this->settings->documents($locale));
    }

    /** The number of the entity's published version, when it is one that keeps versions. */
    private function published(string $type, int $id): ?int
    {
        if (! class_exists(RouteTypes::class) || ! $this->container->bound(RouteTypes::class)) {
            return null;
        }

        $model = $this->container->make(RouteTypes::class)->find($type)?->model;

        if ($model === null || ! class_exists($model) || ! in_array(HasVersions::class, class_uses_recursive($model), true)) {
            return null;
        }

        $number = EntityVersion::query()->published()
            ->where('versionable_type', (new $model)->getMorphClass())
            ->where('versionable_id', $id)
            ->max('number');

        return is_numeric($number) ? (int) $number : null;
    }
}
