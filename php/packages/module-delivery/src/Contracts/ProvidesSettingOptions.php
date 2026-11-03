<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Contracts;

use WebxUi\Delivery\Settings\SettingOption;
use WebxUi\Delivery\Sync\CarrierUnavailable;

/**
 * Optional on top of Carrier: the options of some settings fields come from the carrier's own
 * account — senders, their contact persons, their points — so the panel draws a select instead
 * of a text field (§13.1). A carrier without it keeps plain fields typed by hand.
 *
 * A field asks for it with three keys of its own in `settings()`:
 *
 *     ['name' => 'contact', 'type' => 'select', 'options' => 'carrier', 'depends' => ['sender']],
 *     ['name' => 'sender_point', 'type' => 'select', 'options' => 'carrier', 'depends' => ['sender_city'], 'search' => true],
 *
 * The panel asks while a person waits: answer within five seconds, or throw.
 */
interface ProvidesSettingOptions
{
    /**
     * The options of one field for the values chosen so far.
     *
     * @param  string  $field  a name from settings() whose 'options' is 'carrier'
     * @param  array<string, mixed>  $values  the form's current values: what this field depends on
     * @param  array<string, mixed>  $credentials  the stored keys; never shown, never logged
     * @param  string|null  $query  what the admin typed, for a field with 'search' => true; the
     *                              base checks a stored value by asking with the value itself
     * @return list<SettingOption> at most 50; for a search field — the best matches
     *
     * @throws CarrierUnavailable the carrier did not answer or refused the key
     */
    public function settingOptions(string $field, array $values, array $credentials, ?string $query = null): array;
}
