<?php

declare(strict_types=1);

namespace WebxUi\Events\Panel;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Support\Moment;
use WebxUi\Localization\Locales;

/**
 * Where a saved event goes: all of it into the draft.
 *
 * The categories wait in the draft with the text — `category_ids` — and publishing writes them
 * into the link table. Put back the way the site has them, they leave the draft, so a save that
 * changes nothing does not mark the event "changed". The draft is always built from the one there
 * is ({@see Event::draftValues()}): the relations wait in it under their own key, and a draft
 * built from scratch would drop them.
 *
 * What a single field cannot check is checked here, against the event as it will be after the
 * save — the dates as a pair, and the two links as links.
 */
final class EventWriter
{
    /** The columns that are a link a reader follows: only the web's own schemes. */
    private const LINKS = ['map_url', 'booking_url'];

    public function __construct(private readonly Locales $locales) {}

    /**
     * @param  array<string, mixed>  $columns  The event's own fields, only the ones that were sent.
     * @param  list<int>|null  $categories  Null leaves them alone; an empty list clears them.
     *
     * @throws ValidationException
     */
    public function save(Event $event, array $columns, ?array $categories = null, ?int $authorId = null): Event
    {
        if ($columns === [] && $categories === null) {
            return $event;
        }

        $values = $this->draft($event, $columns);

        $this->check($values);

        if ($categories !== null) {
            if ($categories === $event->categoryIds()) {
                unset($values[Event::DRAFT_CATEGORIES]);
            } else {
                $values[Event::DRAFT_CATEGORIES] = $categories;
            }
        }

        $event->saveDraft($values, $authorId);

        return $event;
    }

    /**
     * The dates as a pair and the links as links (§3): an end before the start, or an end with no
     * start at all, is a 422 under the end — the field the editor has to change.
     *
     * @param  array<string, mixed>  $values
     *
     * @throws ValidationException
     */
    public function check(array $values): void
    {
        $errors = [];

        $start = Moment::tryFrom($values['starts_at'] ?? null);
        $end = Moment::tryFrom($values['ends_at'] ?? null);
        $allDay = (bool) ($values['all_day'] ?? false);

        if ($end !== null && $start === null) {
            $errors['ends_at'] = (string) __('webx-events::errors.ends-without-start');
        } elseif ($end !== null && $start !== null && $this->before($end, $start, $allDay)) {
            $errors['ends_at'] = (string) __('webx-events::errors.ends-before-start');
        }

        foreach (self::LINKS as $field) {
            $link = $values[$field] ?? null;

            if (is_string($link) && $link !== '' && ! self::isWebLink($link)) {
                $errors[$field] = (string) __('webx-events::errors.link');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** `http://` or `https://` with a host — nothing a reader's browser would run instead. */
    public static function isWebLink(string $link): bool
    {
        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && (string) parse_url($link, PHP_URL_HOST) !== '';
    }

    /**
     * The whole draft, with what was sent laid over what the editor is looking at.
     *
     * A translated field travels as its whole map from the form and as one string from an agent
     * speaking one language; neither is ever written over the whole field. A moment is kept as the
     * moment in the application's timezone, whatever offset it came with.
     *
     * @param  array<string, mixed>  $columns
     * @return array<string, mixed>
     */
    public function draft(Event $event, array $columns): array
    {
        $locale = $this->locales->current();
        $values = $event->hasDraft() ? $event->draftValues() : $this->published($event);

        foreach ($columns as $field => $value) {
            $values[$field] = match (true) {
                in_array($field, Event::TRANSLATED, true) => $this->translated($values[$field] ?? null, $value, $locale),
                $field === 'starts_at', $field === 'ends_at' => Moment::tryFrom($value)?->toAtomString(),
                $field === 'all_day' => (bool) $value,
                $field === 'attendance' => is_string($value) && in_array($value, Event::ATTENDANCE, true) ? $value : Event::OFFLINE,
                $field === 'price_amount' => is_numeric($value) ? round((float) $value, 2) : null,
                $field === 'gallery', $field === 'highlights' => is_array($value) ? array_values($value) : [],
                in_array($field, self::LINKS, true) => is_string($value) && trim($value) !== '' ? trim($value) : null,
                default => $value,
            };
        }

        // An event of days is about calendar dates, and those are the dates in the offset the
        // editor sent them in — not in the application's zone (see Moment::day()).
        if ((bool) ($values['all_day'] ?? false)) {
            if (array_key_exists('starts_at', $columns)) {
                $values['starts_at'] = Moment::day($columns['starts_at'])?->toAtomString();
            }

            if (array_key_exists('ends_at', $columns)) {
                $values['ends_at'] = Moment::day($columns['ends_at'])?->endOfDay()->startOfSecond()->toAtomString();
            }
        }

        return $values;
    }

    private function translated(mixed $current, mixed $value, string $locale): mixed
    {
        if (is_array($value)) {
            return is_array($current) ? [...$current, ...$value] : $value;
        }

        $map = is_array($current) ? $current : ($current === null ? [] : [$locale => $current]);
        $map[$locale] = $value;

        return $map;
    }

    /**
     * The event as the site has it — the starting point for a draft that does not exist yet.
     *
     * @return array<string, mixed>
     */
    private function published(Event $event): array
    {
        $values = [
            'gallery' => $event->gallery,
            'starts_at' => $event->starts_at?->toAtomString(),
            'ends_at' => $event->ends_at?->toAtomString(),
            'all_day' => (bool) $event->all_day,
            'attendance' => $event->attendance,
            'map_url' => $event->map_url,
            'highlights' => $event->highlights,
            'price_amount' => $event->price_amount,
            'booking_url' => $event->booking_url,
            'extra' => $event->extraRaw(),
        ];

        foreach (Event::TRANSLATED as $field) {
            $values[$field] = $event->getTranslations($field);
        }

        return $values;
    }

    private function before(Carbon $end, Carbon $start, bool $allDay): bool
    {
        return $allDay
            ? $end->copy()->startOfDay()->lessThan($start->copy()->startOfDay())
            : $end->lessThan($start);
    }
}
