<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Orders\OrderSettings;

/**
 * The tab «Checkout» as the page reads it (spec §20.3, §23.4, §31.2): what a field asks for, which
 * steps stand, how the buyer agrees with the terms. A value that is not one of the choices reads
 * as the default — a hand-written setting does not break the page.
 */
final readonly class CheckoutSettings
{
    public const string REQUIRED = 'required';

    public const string OPTIONAL = 'optional';

    public const string HIDDEN = 'hidden';

    public const string TERMS_NONE = 'none';

    public const string TERMS_NOTICE = 'notice';

    public const string TERMS_CHECKBOX = 'checkbox';

    public function __construct(
        private OrderSettings $settings,
        private LinkUrls $links,
    ) {}

    /** `required`, `optional` or `hidden` for `last_name`, `phone` and `email`; the first name is always required. */
    public function field(string $name): string
    {
        $key = 'orders.checkout.fields.'.$name;
        $value = $this->settings->get($key);

        return in_array($value, [self::REQUIRED, self::OPTIONAL, self::HIDDEN], true)
            ? $value
            : (string) OrderSettings::CHECKOUT[$key];
    }

    /** The checkbox «The recipient is someone else». */
    public function recipient(): bool
    {
        return $this->on('orders.checkout.recipient');
    }

    /** The step «Comment». */
    public function comment(): bool
    {
        return $this->on('orders.checkout.comment');
    }

    /** `none`, `notice` or `checkbox`. */
    public function terms(): string
    {
        $value = $this->settings->get('orders.checkout.terms');

        return in_array($value, [self::TERMS_NONE, self::TERMS_NOTICE, self::TERMS_CHECKBOX], true) ? $value : self::TERMS_NOTICE;
    }

    /**
     * The documents of the agreement — the terms, then the privacy policy — each with its title
     * and address; a link that leads nowhere is left out.
     *
     * @return list<array{key: string, title: string, url: string}>
     */
    public function documents(?string $locale = null): array
    {
        $documents = [];

        foreach (['terms' => 'orders.checkout.terms_page', 'privacy' => 'orders.checkout.privacy_page'] as $name => $key) {
            $value = $this->settings->get($key);

            if (! is_array($value)) {
                continue;
            }

            $link = Link::fromArray($value);
            $candidate = $this->links->candidate($link, $locale);
            $url = $this->links->hrefWith($link, $candidate, $locale);

            if ($url === null || $url === '') {
                continue;
            }

            $documents[] = [
                'key' => $name,
                'title' => $candidate !== null ? $candidate->title : (string) __('webx-orders::checkout.terms.'.$name),
                'url' => $url,
            ];
        }

        return $documents;
    }

    private function on(string $key): bool
    {
        $value = $this->settings->get($key);

        return ! in_array($value, ['off', false, 0, '0'], true);
    }
}
