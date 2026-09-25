<?php

declare(strict_types=1);

namespace WebxUi\Events\Panel;

use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Events\Http\Resources\EventResource;
use WebxUi\Events\Models\Event;
use WebxUi\Seo\Fields;

/**
 * The editor's screen on the server side: what its fields hold, and what a save writes (§4.9,
 * §4.10).
 *
 * The screen is `events.form`, keyed by field name. The services are `wx-relations`, sorted out
 * of the values by {@see ScreenRecord} itself; the SEO card is `module-seo`'s; everything nobody
 * here names is the project's and goes into `extra`.
 */
final class EventForm
{
    /**
     * The event's own fields.
     *
     * @var list<string>
     */
    public const OWN = [
        'title', 'slug', 'lead', 'gallery', 'starts_at', 'ends_at', 'all_day', 'date_note', 'attendance',
        'venue', 'address', 'map_url', 'description', 'highlights', 'price', 'price_amount', 'booking_url',
    ];

    /**
     * The screen's fields that are not the event's text: the categories, and the SEO card.
     *
     * @var list<string>
     */
    private const TAKEN = ['categories', Fields::SCREEN];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly EventWriter $writer,
    ) {}

    /**
     * An event and everything its editor needs around it (§4.10): the record, the values of the
     * screen, the revision those values are, the prefix of its address and a link to the draft —
     * minted per response, because it is signed and short-lived. No link without `module-blocks`,
     * which draws previews.
     *
     * @return array<string, mixed>
     */
    public function describe(Event $event, ?int $adminId = null): array
    {
        return [
            'event' => new EventResource($event),
            'values' => $this->values($event),
            'revision' => Revision::of($event),
            'prefix' => (string) config('webx-events.prefix', 'events'),
            'preview_url' => class_exists(Preview::class) ? Preview::url($event, $adminId) : null,
        ];
    }

    /**
     * What the form opens with: the draft laid over the columns — what the editor was last
     * working on. The categories and the relations wait in the draft too.
     *
     * @return array<string, mixed>
     */
    public function values(Event $event): array
    {
        $shown = $event->hasDraft() ? $event->withDraft() : $event;

        return [
            // The project's fields first, so that none of them can stand in for one of the
            // event's own.
            ...($shown->extraRaw() ?? []),
            'title' => $shown->getTranslations('title'),
            'slug' => $shown->getTranslations('slug'),
            'lead' => $shown->getTranslations('lead'),
            'gallery' => is_array($shown->gallery) ? $shown->gallery : [],
            'starts_at' => $shown->starts_at?->toAtomString(),
            'ends_at' => $shown->ends_at?->toAtomString(),
            'all_day' => (bool) $shown->all_day,
            'date_note' => $shown->getTranslations('date_note'),
            'attendance' => $shown->attendance,
            'venue' => $shown->getTranslations('venue'),
            'address' => $shown->getTranslations('address'),
            'map_url' => $shown->map_url,
            'description' => $shown->getTranslations('description'),
            'highlights' => is_array($shown->highlights) ? array_values($shown->highlights) : [],
            'price' => $shown->getTranslations('price'),
            'price_amount' => $shown->price_amount,
            'booking_url' => $shown->booking_url,
            'categories' => $event->draftedCategoryIds(),
            ...$this->record->relationValues(Event::SCREEN, $event),
            Fields::SCREEN => $event->seoValue(),
        ];
    }

    /**
     * Check what came in against the screen and write it — the panel's door and an agent's alike.
     * The caller holds the transaction: a refusal half way must not leave half a save.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Event $event, array $input, ?callable $can = null, ?int $authorId = null): Event
    {
        $split = $this->record->split(Event::SCREEN, $input, self::OWN, self::TAKEN, $can);
        $stored = [...$split->own, ...$split->taken];

        $columns = $split->own;

        // A field a project patched onto the screen goes into `extra`, and into the draft with the
        // text around it. Laid over what the editor is looking at, so a tab nobody opened keeps
        // its fields.
        if ($split->extra !== []) {
            $columns['extra'] = $this->record->merge(Event::SCREEN, $this->currentExtra($event), $split->extra);
        }

        $categories = null;

        if (array_key_exists('categories', $stored)) {
            $categories = is_array($stored['categories']) ? array_values(array_map(intval(...), $stored['categories'])) : [];
        }

        $this->writer->save($event, $columns, $categories, $authorId);

        $this->record->saveRelations($event, $split);

        // Only when it travelled — a save of another tab must not empty a card nobody opened.
        if (array_key_exists(Fields::SCREEN, $stored)) {
            $value = $stored[Fields::SCREEN];

            $event->saveSeo(is_array($value) ? $value : null);
        }

        return $event->refresh();
    }

    /**
     * The project's fields as the editor last left them.
     *
     * @return array<string, mixed>|null
     */
    private function currentExtra(Event $event): ?array
    {
        $draft = $event->draftValues();

        if (array_key_exists('extra', $draft)) {
            return is_array($draft['extra']) ? $draft['extra'] : null;
        }

        return $event->extraRaw();
    }
}
