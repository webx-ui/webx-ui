<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Sending;

use WebxUi\Localization\Locales;
use WebxUi\Notifications\Contracts\Provider;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Templates\Template;

/**
 * A channel's template and the values of an event made into one message (§7).
 *
 * The language is the event's (decision 13) — the one the submission or the order was made in —
 * or the site's main language; a channel without that language falls back to the main one, then
 * to the first it has. Values go in through the provider's escaping; the template's own markup is
 * left as the administrator wrote it.
 */
final class Renderer
{
    public function __construct(private readonly Locales $locales) {}

    /**
     * @param  array<string, string|list<array<string, string>>>  $variables
     * @return array{message: Message, unknown: list<string>}
     */
    public function render(Channel $channel, Provider $provider, array $variables, ?string $locale, ?EventDefinition $event): array
    {
        $locale = $locale !== null && $locale !== '' ? $locale : $this->locales->defaultCode();
        $known = $event === null ? null : static fn (string $name, array $sections): bool => $event->knows($name, $sections);

        $body = Template::render($channel->templateIn($locale), $variables, $provider->escape(...), $known);
        $subject = null;
        $unknown = $body['unknown'];

        if ($provider->hasSubject()) {
            $rendered = Template::render((string) $channel->subjectIn($locale), $variables, self::oneLine(...), $known);
            $subject = trim(self::oneLine($rendered['text']));
            $unknown = array_values(array_unique([...$unknown, ...$rendered['unknown']]));
        }

        $plain = $provider->plain($body['text']);
        $replyTo = $variables['reply_to'] ?? null;
        $replyTo = is_string($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false ? $replyTo : null;
        $limit = $provider->limit();

        if ($limit !== null && mb_strlen($plain) > $limit) {
            $cut = mb_substr($plain, 0, $limit - 1).'…';

            return ['message' => new Message($subject, $provider->escape($cut), $cut, true, $replyTo), 'unknown' => $unknown];
        }

        return ['message' => new Message($subject, $body['text'], $plain, false, $replyTo), 'unknown' => $unknown];
    }

    /** A subject is one line: a value with line breaks would break the header. */
    public static function oneLine(string $value): string
    {
        return (string) preg_replace('/[\r\n]+/', ' ', $value);
    }
}
