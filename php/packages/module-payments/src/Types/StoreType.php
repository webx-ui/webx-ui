<?php

declare(strict_types=1);

namespace WebxUi\Payments\Types;

use WebxUi\Payments\Availability;
use WebxUi\Payments\Contracts\MethodType;
use WebxUi\Payments\Kind;
use WebxUi\Payments\Method;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\PaymentSnapshot;
use WebxUi\Payments\Presentation;
use WebxUi\Payments\Words;

/**
 * What the shop's own two types share (spec §12): offline, any currency, no limits of their own,
 * the shop's title or the dictionary's, and a block with the title in every language of the site.
 */
abstract class StoreType implements MethodType
{
    /** The provider of the built-in types. */
    public const string PROVIDER = 'store';

    /** The method's key in `payment_methods` the migration creates for the type. */
    abstract public function methodKey(): string;

    /** The dictionary's title of the type, under `webx-payments::methods.<kind>.title`. */
    abstract protected function words(): string;

    public function provider(): string
    {
        return self::PROVIDER;
    }

    public function kind(): Kind
    {
        return Kind::Offline;
    }

    public function currencies(): ?array
    {
        return null;
    }

    public function available(Method $method, PaymentContext $context): Availability
    {
        return Availability::yes();
    }

    public function present(Method $method, PaymentContext $context): Presentation
    {
        return new Presentation(
            $method->title($context->locale) ?? (string) trans($this->words().'.title', [], $context->locale),
            $method->description($context->locale),
        );
    }

    public function snapshot(Method $method, PaymentContext $context): PaymentSnapshot
    {
        return new PaymentSnapshot($method->key, $this->key(), $this->kind(), $this->titles($method), $this->details($method, $context));
    }

    /**
     * The type's own part of the block.
     *
     * @return array<string, mixed>
     */
    protected function details(Method $method, PaymentContext $context): array
    {
        return [];
    }

    /**
     * The method's title in every language of the site, as the order keeps it.
     *
     * @return array<string, string>
     */
    protected function titles(Method $method): array
    {
        return Words::everywhere($method->titles, $this->words().'.title');
    }

    /**
     * A field of the method's form that is a text in the languages of the site.
     *
     * @return array<string, mixed>
     */
    protected static function localizedField(string $name, string $label, bool $multiline = false): array
    {
        return ['name' => $name, 'type' => $multiline ? 'textarea' : 'text', 'label' => $label, 'localized' => true];
    }
}
