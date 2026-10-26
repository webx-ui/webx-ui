<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Panel;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Pricing\Texts;
use WebxUi\Configurator\Schema\Problem;
use WebxUi\Localization\Locales;

/**
 * What the panel's API answers about a configurator (§13.3): a line of the list, and the editor's
 * whole record — the values it edits, the revision it saves against, the problems of the draft.
 *
 * The key travels inside the values though it is not drafted: the settings tab edits it beside the
 * title, and a save that names it changes it at once while the configurator has never been on the
 * site (decision 11).
 */
final class ConfiguratorForm
{
    public function __construct(
        private readonly ConfiguratorWriter $writer,
        private readonly Locales $locales,
    ) {}

    /**
     * The list with whether each was ever published, asked once rather than once a line: a
     * configurator off the site is «never published» or «taken off», and only the versions know.
     *
     * @param  Builder<Configurator>  $query
     * @return Builder<Configurator>
     */
    public static function withHistory(Builder $query): Builder
    {
        return $query->withExists(['versions as was_published' => static function (Builder $versions): void {
            $versions->where('kind', EntityVersion::KIND_PUBLISHED);
        }]);
    }

    /**
     * @return array<string, mixed>
     */
    public function row(Configurator $configurator): array
    {
        $editing = $configurator->editing();
        $schema = $editing['schema'] ?? [];
        $published = $this->wasPublished($configurator);

        $row = [
            'id' => (int) $configurator->getKey(),
            'key' => $configurator->key,
            'title' => Texts::pick($editing['title'], $this->locales->content(), $this->locales->defaultCode()),
            'status' => $this->status($configurator, $published),
            'form' => $editing['form'],
            'steps' => is_array($schema['steps'] ?? null) ? count($schema['steps']) : 0,
            'currency' => is_string($schema['currency'] ?? null) ? $schema['currency'] : null,
            // After the first publication the key is what blocks of the site name it by.
            'key_locked' => $published,
            'published_at' => $configurator->published_at?->toAtomString(),
            'updated_at' => $configurator->updated_at?->toAtomString(),
        ];

        if ($configurator->trashed()) {
            $row['deleted_at'] = $configurator->deleted_at?->toAtomString();
        }

        return $row;
    }

    /**
     * The editor's record. `errors` are the problems of the draft — what a publication would
     * refuse; a draft is saved with them (§9).
     *
     * @return array<string, mixed>
     */
    public function describe(Configurator $configurator): array
    {
        $editing = $configurator->editing();

        return [
            'configurator' => $this->row($configurator),
            'values' => [
                'key' => $configurator->key,
                'title' => $editing['title'],
                'schema' => $editing['schema'] ?? $this->writer->skeleton(),
                'form' => $editing['form'],
            ],
            'revision' => Revision::of($configurator),
            'errors' => array_map(static fn (Problem $problem): array => $problem->toArray(), $this->writer->problems($configurator)),
            // Without the inbox a configurator sends no requests, and the editor has no form field.
            'has_inbox' => $this->writer->hasInbox(),
        ];
    }

    private function wasPublished(Configurator $configurator): bool
    {
        if ($configurator->isPublished()) {
            return true;
        }

        $known = $configurator->getAttribute('was_published');

        return $known !== null ? (bool) $known : $configurator->versions()->published()->exists();
    }

    private function status(Configurator $configurator, bool $published): string
    {
        if (! $configurator->isPublished()) {
            return $published ? Configurator::STATUS_UNPUBLISHED : Configurator::STATUS_DRAFT;
        }

        return $configurator->hasDraft() ? Configurator::STATUS_MODIFIED : Configurator::STATUS_PUBLISHED;
    }
}
