<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Types;

use WebxUi\Delivery\AbstractMethodType;
use WebxUi\Delivery\Contracts\LabelledMethodType;
use WebxUi\Delivery\DeliveryContext;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\Quote;

/**
 * What the three types of the carrier share (§7): named by the dictionary, Ukraine only, and the
 * price «by the carrier's rates» without asking its API (decision 3) — unless the site gave the
 * method a price of its own, which the shop takes from the buyer and pays the carrier itself.
 */
abstract class NovaPoshtaType extends AbstractMethodType implements LabelledMethodType
{
    /** 'branch', 'locker', 'courier': the kind after the dot and the key of its words. */
    abstract protected function kind(): string;

    public function key(): string
    {
        return NovaPoshtaCarrier::KEY.'.'.$this->kind();
    }

    public function carrier(): string
    {
        return NovaPoshtaCarrier::KEY;
    }

    public function countries(): array
    {
        return ['UA'];
    }

    public function quote(Method $method, DeliveryContext $context): Quote
    {
        return $method->price === null ? Quote::carrierRates() : Quote::fixed($method->price);
    }

    public function title(string $locale): string
    {
        return NovaPoshtaCarrier::word('types.'.$this->kind().'.title', $locale);
    }

    public function description(string $locale): ?string
    {
        return NovaPoshtaCarrier::word('types.'.$this->kind().'.description', $locale);
    }
}
