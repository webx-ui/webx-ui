<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Delivery\Contracts\MethodType;

/**
 * What every type shares: the price of the method row, and the check of what the buyer entered
 * (§6.1). A satellite extends it and adds its own (a locker that takes no cash on delivery).
 */
abstract class AbstractMethodType implements MethodType
{
    public const int STREET_MAX = 191;

    public const int HOUSE_MAX = 32;

    public const int NOTE_MAX = 500;

    public function carrier(): string
    {
        return explode('.', $this->key(), 2)[0];
    }

    public function pointKinds(): array
    {
        return [];
    }

    public function countries(): ?array
    {
        return null;
    }

    public function settings(): array
    {
        return [];
    }

    public function available(Method $method, DeliveryContext $context): bool
    {
        return true;
    }

    /** The method's own price: none or zero is «Free», anything else the buyer pays the shop. */
    public function quote(Method $method, DeliveryContext $context): Quote
    {
        return Quote::fixed($method->price ?? 0);
    }

    public function validate(Method $method, DeliveryContext $context, SelectionInput $input): Selection|array
    {
        return match ($this->asks()) {
            Asks::Point => $this->validatePoint($method, $context, $input),
            Asks::Address => $this->validateAddress($method, $context, $input),
            Asks::Nothing => new Selection($method, $context->locality),
        };
    }

    /** @return Selection|array<string, string> */
    protected function validatePoint(Method $method, DeliveryContext $context, SelectionInput $input): Selection|array
    {
        if ($input->point === null) {
            return ['point' => $this->word('choose_point', $context)];
        }

        $point = app(Points::class)->ref($input->point);
        $kind = $method->pointKind ?? ($this->pointKinds()[0] ?? null);

        if ($point === null
            || ! $point->active
            || $point->removed
            || $point->carrier !== $this->carrier()
            || $point->kind !== $kind
            || $point->localityId !== $context->locality->id) {
            return ['point' => $this->word('point_gone', $context)];
        }

        return new Selection($method, $context->locality, point: $point);
    }

    /** @return Selection|array<string, string> */
    protected function validateAddress(Method $method, DeliveryContext $context, SelectionInput $input): Selection|array
    {
        $errors = [];
        $store = $this->carrier() === Carriers::STORE;
        $street = null;

        // A carrier's courier takes a street of its directory only (decision 31): the id says
        // which, and the name is the directory's, not what came with it. The shop's courier takes
        // any text and never an id — what the buyer typed goes nowhere but the selection (§8.3).
        if (! $store) {
            $streets = app(Streets::class);
            $street = $input->streetId === null ? null : $streets->find($this->carrier(), $context->locality->id, $input->streetId, $context->locale);

            if ($street === null && $streets->count($this->carrier(), $context->locality->id) === 0) {
                // No street of the carrier here: its courier cannot come, whatever is typed (§8.2).
                return ['method' => (string) trans('webx-delivery::delivery.hints.no_streets', [], $context->locale)];
            }

            if ($street === null) {
                $errors['street'] = $this->word('choose_street', $context);
            }
        } elseif ($input->street === null) {
            $errors['street'] = $this->word('street_required', $context);
        } elseif (mb_strlen($input->street) > self::STREET_MAX) {
            $errors['street'] = $this->word('too_long', $context, ['max' => self::STREET_MAX]);
        }

        if ($input->house === null) {
            $errors['house'] = $this->word('house_required', $context);
        } elseif (mb_strlen($input->house) > self::HOUSE_MAX) {
            $errors['house'] = $this->word('too_long', $context, ['max' => self::HOUSE_MAX]);
        }

        if ($input->apartment !== null && mb_strlen($input->apartment) > self::HOUSE_MAX) {
            $errors['apartment'] = $this->word('too_long', $context, ['max' => self::HOUSE_MAX]);
        }

        if ($input->postcode !== null && mb_strlen($input->postcode) > 16) {
            $errors['postcode'] = $this->word('too_long', $context, ['max' => 16]);
        }

        if ($input->note !== null && mb_strlen($input->note) > self::NOTE_MAX) {
            $errors['note'] = $this->word('too_long', $context, ['max' => self::NOTE_MAX]);
        }

        if ($errors !== []) {
            return $errors;
        }

        return new Selection($method, $context->locality, address: new Address(
            street: $street->name ?? (string) $input->street,
            streetId: $street?->streetId,
            house: (string) $input->house,
            apartment: $input->apartment,
            postcode: $input->postcode,
            note: $input->note,
        ));
    }

    /** @param  array<string, int|string>  $replace */
    protected function word(string $key, DeliveryContext $context, array $replace = []): string
    {
        return (string) trans("webx-delivery::delivery.errors.{$key}", $replace, $context->locale);
    }
}
