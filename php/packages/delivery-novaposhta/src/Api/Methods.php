<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Api;

/**
 * Every method of the carrier's API the satellite may call, split into reading and writing (§3.3).
 *
 * The list is explicit on purpose: a method missing from both is refused, so a new call has to be
 * placed on one side before it can reach the carrier — and a writing one is refused in read-only
 * mode ({@see Client}) before any request is made.
 */
final class Methods
{
    /** Directories, the account's senders and contacts, tracking: nothing changes at the carrier. */
    public const array READ = [
        'Address.getCities',
        'Address.getWarehouses',
        'Address.getWarehouseTypes',
        'Address.getStreet',
        'Counterparty.getCounterparties',
        'Counterparty.getCounterpartyContactPersons',
        'TrackingDocument.getStatusDocuments',
    ];

    /** What creates, changes or deletes something in the carrier's account. */
    public const array WRITE = [
        'Counterparty.save',
        'Counterparty.update',
        'Counterparty.delete',
        'ContactPerson.save',
        'ContactPerson.update',
        'ContactPerson.delete',
        'Address.save',
        'Address.update',
        'Address.delete',
        'InternetDocument.save',
        'InternetDocument.update',
        'InternetDocument.delete',
    ];

    public static function name(string $model, string $method): string
    {
        return $model.'.'.$method;
    }

    public static function reads(string $model, string $method): bool
    {
        return in_array(self::name($model, $method), self::READ, true);
    }

    public static function writes(string $model, string $method): bool
    {
        return in_array(self::name($model, $method), self::WRITE, true);
    }
}
