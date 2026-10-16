<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Contracts;

use WebxUi\Notifications\Refusal;
use WebxUi\Notifications\Sending\CheckResult;
use WebxUi\Notifications\Sending\Credentials;
use WebxUi\Notifications\Sending\DeliveryFailed;
use WebxUi\Notifications\Sending\Field;
use WebxUi\Notifications\Sending\Message;
use WebxUi\Notifications\Sending\Recipient;

/**
 * A kind of channel: how to reach somebody and in what markup (§4.1 of the notifications spec).
 *
 * The module ships Telegram and e-mail; a package or a site adds its own with
 * `Providers::register()` — no fork. Everything a provider shows in the panel is screen nodes
 * ({@see screen()}), so a provider without an npm half works in the panel at once.
 *
 * Optional abilities are interfaces of their own ({@see FindsRecipients}), so this one does not
 * grow with every provider's speciality.
 */
interface Provider
{
    /** The key a channel stores: `telegram`. */
    public function key(): string;

    /** A dictionary key or plain words. */
    public function label(): string;

    /** An icon of the core set. */
    public function icon(): string;

    /**
     * The settings of a channel of this kind: secret ones go to `credentials`, encrypted and never
     * given back; the others to `options`, open.
     *
     * @return list<Field>
     */
    public function fields(): array;

    /** Whether a message has a subject (e-mail) or is the body alone (Telegram). */
    public function hasSubject(): bool;

    /**
     * The kind of address an event's addressee needs to be a recipient here: `email`, `telegram`.
     */
    public function address(): string;

    /** The longest message the other side takes, in characters of plain text; null for none. */
    public function limit(): ?int;

    /**
     * The fixed recipients as they are stored, checked and brought to their shape. An event's
     * addressee (`{variable: …}`) is checked by the caller and passed through.
     *
     * @param  list<mixed>  $recipients
     * @return list<array<string, mixed>>
     *
     * @throws Refusal
     */
    public function recipients(array $recipients): array;

    /**
     * Who the stored recipients are right now: an administrator's address, an addressee's value.
     * A recipient that cannot be written to is returned with the reason, to be logged as skipped.
     *
     * @param  list<array<string, mixed>>  $recipients
     * @param  array<string, string>  $variables
     * @return list<Recipient>
     */
    public function resolve(array $recipients, array $variables): array;

    /**
     * What is missing for a channel to be switched on — a dictionary key — or null.
     *
     * @param  array<string, mixed>  $options
     * @param  list<string>  $credentialsSet
     */
    public function missing(array $options, array $credentialsSet): ?string;

    /**
     * Problems with the markup of a template, in words to show under it.
     *
     * @return list<string>
     */
    public function checkTemplate(string $template): array;

    /** A variable's value made safe for the markup of the message. */
    public function escape(string $value): string;

    /** The message as plain text: what its length is measured by. */
    public function plain(string $body): string;

    /**
     * Send to one recipient.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws DeliveryFailed
     */
    public function send(Message $message, Credentials $credentials, array $options, Recipient $to): void;

    /**
     * Check the settings without sending anything.
     *
     * @param  array<string, mixed>  $options
     */
    public function check(Credentials $credentials, array $options): CheckResult;

    /**
     * Screen nodes of the channel's form for this provider, each shown only when the channel is of
     * this kind.
     *
     * @return list<array<string, mixed>>
     */
    public function screen(): array;
}
