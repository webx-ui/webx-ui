<?php

declare(strict_types=1);

namespace WebxUi\Services\Panel;

use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Seo\Fields;
use WebxUi\Services\Http\Resources\ServiceResource;
use WebxUi\Services\Models\Service;

/**
 * The editor's screen on the server side: what its fields hold, and what a save writes (§4.6).
 *
 * The screen is `services.form`, keyed by field name, so what a service is made of is decided by
 * the description: the SEO card arrives as a patch from `module-seo`, a project's price as a
 * patch from the site, and both are saved here by being on the screen at all. What this class
 * knows is which names are the service's own; {@see ServiceWriter} knows which of those wait in
 * the draft and which take effect at once; everything else is `extra`.
 *
 * One field is named differently on the screen and in the table: `cover` holds the library key
 * the media field edits, the column the id of the row. The mapping lives here, both ways.
 */
final class ServiceForm
{
    /**
     * The service's own text, in this order.
     *
     * @var list<string>
     */
    private const OWN = ['title', 'slug', 'lead', 'blocks', 'cover'];

    /**
     * The screen's fields that are not the text: the categories, stored beside the service, and
     * the SEO card, stored by the module that put it there.
     *
     * @var list<string>
     */
    private const TAKEN = ['categories', Fields::SCREEN];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly ServiceWriter $writer,
    ) {}

    /**
     * A service and everything its editor needs around it: the record, the values of the screen,
     * the revision those values are, the prefix of its address and a link to the draft.
     *
     * The preview link is minted per response: it is signed and short-lived, and a form open all
     * morning would otherwise hand the editor a link that expired before lunch.
     *
     * @return array<string, mixed>
     */
    public function describe(Service $service, ?int $adminId = null): array
    {
        return [
            'service' => new ServiceResource($service),
            'values' => $this->values($service),
            'revision' => Revision::of($service),
            // The whole address is the prefix and the slug, and the prefix is the same in every
            // language: the catalogue is one flat space (§4.3).
            'prefix' => (string) config('webx-services.prefix', 'services'),
            'preview_url' => Preview::url($service, $adminId),
        ];
    }

    /**
     * What the form opens with: the draft laid over the columns — what the editor was last
     * working on, not what the site is showing. The categories and the SEO card are never
     * drafted, so they are read off the service itself.
     *
     * @return array<string, mixed>
     */
    public function values(Service $service): array
    {
        $shown = $service->hasDraft() ? $service->withDraft() : $service;

        return [
            // The project's fields first, so that none of them can stand in for one of the
            // service's own. From the draft like the text: they are published with it.
            ...($shown->extraRaw() ?? []),
            'title' => $shown->getTranslations('title'),
            'slug' => $shown->getTranslations('slug'),
            'lead' => $shown->getTranslations('lead'),
            'blocks' => $shown->blocksTree(),
            'cover' => $this->cover($shown->cover_id),
            'categories' => $this->categories($service),
            Fields::SCREEN => $service->seoValue(),
        ];
    }

    /**
     * Check what came in against the screen and write it — the panel's door and an agent's alike.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Service $service, array $input, ?callable $can = null, ?int $authorId = null): Service
    {
        $split = $this->record->split(Service::SCREEN, $input, self::OWN, self::TAKEN, $can);
        $stored = [...$split->own, ...$split->taken];

        $columns = [];

        // A field a project patched onto the screen goes into `extra`, and into the draft with the
        // text around it: a price is published with the service, not before it. Laid over what
        // the editor is looking at, so a tab nobody opened keeps its fields.
        if ($split->extra !== []) {
            $columns['extra'] = $this->record->merge(Service::SCREEN, $this->currentExtra($service), $split->extra);
        }

        foreach (self::OWN as $field) {
            if (! array_key_exists($field, $stored)) {
                continue;
            }

            if ($field === 'cover') {
                $columns['cover_id'] = Service::coverIdOf($stored['cover']);

                continue;
            }

            $columns[$field] = $stored[$field];
        }

        $categories = null;

        if (array_key_exists('categories', $stored)) {
            /** @var list<int> $categories */
            $categories = is_array($stored['categories']) ? array_values(array_map(intval(...), $stored['categories'])) : [];
        }

        $service = $this->writer->save($service, $columns, $categories, $authorId);

        // Only when it travelled — a save of the content tab alone must not empty a card nobody
        // opened.
        if (array_key_exists(Fields::SCREEN, $stored)) {
            $value = $stored[Fields::SCREEN];

            $service->saveSeo(is_array($value) ? $value : null);
        }

        return $service;
    }

    /**
     * The project's fields as the editor last left them: the draft's when it has them, the site's
     * otherwise — a draft saved before the service had any is a draft without the key.
     *
     * @return array<string, mixed>|null
     */
    private function currentExtra(Service $service): ?array
    {
        $draft = $service->draftValues();

        if (array_key_exists('extra', $draft)) {
            return is_array($draft['extra']) ? $draft['extra'] : null;
        }

        return $service->extraRaw();
    }

    /**
     * The key alone — the field asks the library where that key lives now, which is the only
     * answer that survives a move to another disk. By id rather than through the relation: the
     * draft may name a different picture from the one the column holds.
     *
     * @return array{path: string}|null
     */
    private function cover(mixed $id): ?array
    {
        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return null;
        }

        $file = MediaFile::query()->find((int) $id);

        return $file instanceof MediaFile ? ['path' => $file->path] : null;
    }

    /**
     * @return list<int>
     */
    private function categories(Service $service): array
    {
        /** @var list<int> $ids */
        $ids = $service->categories()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        return $ids;
    }
}
