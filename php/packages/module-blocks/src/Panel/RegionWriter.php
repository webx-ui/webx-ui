<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Throwable;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\ContentValues;
use WebxUi\Blocks\Exceptions\RegionRefused;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Regions;

/**
 * Everything that changes a region, for the panel's controller and the agent's tools alike: the
 * draft, the publication, taking it off, the history, and moving the fallback into a block type.
 *
 * A region is written the way a page is — a draft that the site does not see, a publication that
 * writes a version — with one difference at the door out: a block that throws while the draft is
 * rendered refuses the publication. On a page that block would leave a gap; in a region it would
 * make the site print the fallback in place of everything just published, and the editor would
 * never learn why "it did not apply".
 */
final class RegionWriter
{
    public function __construct(
        private readonly Regions $regions,
        private readonly ContentValues $values,
        private readonly Views $views,
        private readonly Filesystem $files,
    ) {}

    /**
     * Keep a tree as the draft. The first write makes the row.
     *
     * @param  iterable<array-key, mixed>  $tree
     *
     * @throws RegionRefused on a stale revision, or a tree the region does not take
     */
    public function save(string $name, iterable $tree, ?string $revision, ?int $authorId, string $source = EntityVersion::SOURCE_PANEL): Region
    {
        $region = $this->regions->findOrNew($name);
        $current = Content::revision($region->editingTree());

        if ($revision !== null && $revision !== '' && $revision !== $current) {
            throw RegionRefused::conflict($current);
        }

        $this->admissible($name, $tree);

        $draft = $region->draftValues();
        $region->saveDraft(array_merge($draft, ['blocks' => $this->values->store($tree, 'blocks', Regions::ALLOWED_IN.$name, $region->editingTree())]), $authorId, $source);

        return $region;
    }

    /**
     * Whether a tree may stand in the region at all: its `allow`, its `max`, the `allowed_in` of
     * the types at its top.
     *
     * @param  iterable<array-key, mixed>  $tree
     *
     * @throws RegionRefused
     */
    public function admissible(string $name, iterable $tree): void
    {
        $refusals = $this->regions->refusals($name, $tree);

        if ($refusals !== []) {
            throw RegionRefused::invalid((string) __('webx-blocks::regions.refused'), $refusals);
        }
    }

    /**
     * Render the draft as the site will, and publish it only when nothing in it throws.
     *
     * @return list<string> the failures, as lines — empty when it would publish
     */
    public function check(Region $region): array
    {
        $tree = $region->editingTree();

        $this->admissible($region->name, $tree);

        $render = $this->regions->draw($region->name, $tree, [], preview: false, report: false);

        return array_map(
            static fn (array $failure): string => (string) __('webx-blocks::regions.failed-block', [
                'type' => $failure['type'],
                'key' => $failure['key'],
                'reason' => $failure['message'].($failure['line'] === null ? '' : ' (line '.$failure['line'].')'),
            ]),
            $render->failures,
        );
    }

    /**
     * @throws RegionRefused when there is nothing to publish or a block fails
     */
    public function publish(string $name, ?int $authorId, string $source = EntityVersion::SOURCE_PANEL): Region
    {
        $region = $this->regions->find($name) ?? throw RegionRefused::status((string) __('webx-blocks::regions.nothing-to-publish'), 422);

        $failures = $this->check($region);

        if ($failures !== []) {
            throw RegionRefused::invalid((string) __('webx-blocks::regions.not-published'), $failures);
        }

        return $region->publish($authorId, $source);
    }

    /** Back to the fallback. The published tree and the draft stay, for the next publication. */
    public function unpublish(string $name): ?Region
    {
        $region = $this->regions->find($name);

        if ($region instanceof Region && $region->isPublished()) {
            $region->unpublish();
        }

        return $region;
    }

    public function discard(string $name): ?Region
    {
        $region = $this->regions->find($name);

        $region?->discardDraft();

        return $region;
    }

    /**
     * An old publication into the draft, to be published the ordinary way.
     *
     * @throws RegionRefused when there is no such version
     */
    public function restore(string $name, int $number): Region
    {
        $region = $this->regions->find($name);
        $version = $region?->publishedVersions()->where('number', $number)->first();

        if (! $region instanceof Region || ! $version instanceof EntityVersion) {
            throw RegionRefused::status((string) __('webx-blocks::regions.no-version', ['number' => $number]), 404);
        }

        $region->restoreVersion($version);

        return $region->refresh();
    }

    /**
     * "Move the markup into a block" (§7.3 of the regions spec): a block type `site-{name}` whose
     * template is the fallback view's source as the site resolves it — the copy it published into
     * `resources/views/vendor/` first, the way "Customise" reads a module's view — offered only in
     * this region, published so that it can be placed and previewed, and one instance of it put
     * into the region's draft. What happens to it next is an editor's work in "Blocks".
     *
     * The type and its publication are one step: a template that does not survive its own check
     * leaves nothing behind, and the refusal says which line.
     *
     * @return array{Region, Block}
     *
     * @throws RegionRefused 422 without a known fallback or with one that fails, 409 when the slug is taken
     */
    public function adopt(string $name, ?int $authorId, string $source = BlockVersion::SOURCE_PANEL): array
    {
        $view = $this->regions->fallbackOf($name) ?? throw RegionRefused::status((string) __('webx-blocks::regions.no-fallback'), 422);
        $slug = RegionForm::adoptedSlug($name);

        if (Block::query()->where('slug', $slug)->exists()) {
            throw RegionRefused::status((string) __('webx-blocks::regions.adopt-taken', ['slug' => $slug]), 409);
        }

        try {
            $template = $this->files->get($this->views->getFinder()->find($view));
        } catch (InvalidArgumentException) {
            throw RegionRefused::status((string) __('webx-blocks::regions.no-fallback'), 422);
        }

        try {
            $block = Block::query()->getConnection()->transaction(function () use ($name, $slug, $view, $template, $authorId, $source): Block {
                $block = Block::query()->create([
                    'slug' => $slug,
                    'kind' => Block::KIND_BLOCK,
                    'title' => $this->regions->title($name),
                    'description' => (string) __('webx-blocks::calls.customised-from', ['view' => $view]),
                    'allowed_in' => [Regions::ALLOWED_IN.$name],
                ]);

                $block->saveVersion(['template' => $template], $source, $authorId, (string) __('webx-blocks::calls.customised-from', ['view' => $view]));
                $block->publish();

                return $block;
            });
        } catch (Throwable $failure) {
            // Most often the view's own variables — `$attributes`, a prop — which a block's
            // template does not have: the sentence says which, and nothing was created.
            throw RegionRefused::invalid((string) __('webx-blocks::regions.adopt-failed'), [$failure->getMessage()]);
        }

        $region = $this->regions->findOrNew($name);
        $tree = $region->editingTree();
        $tree[] = ['key' => substr(bin2hex(random_bytes(8)), 0, 12), 'type' => $slug, 'values' => []];

        $region->saveDraft(array_merge($region->draftValues(), ['blocks' => $tree]), $authorId, $source);

        return [$region, $block];
    }
}
