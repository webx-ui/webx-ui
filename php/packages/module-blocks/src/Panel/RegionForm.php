<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Preview\Preview;
use WebxUi\Blocks\Regions;

/**
 * A region as the panel reads it — the line in the list and the editor's whole state — for the
 * controller and the agent's tools alike (§7 of the regions spec).
 *
 * A declared region nobody has saved is described all the same, with `id: null`: the panel
 * opens it empty and the first save makes the row, like a declared menu.
 */
final class RegionForm
{
    public const SCREEN = 'regions.form';

    public function __construct(
        private readonly Regions $regions,
        private readonly Preview $preview,
        private readonly Config $config,
    ) {}

    /**
     * The line of the list.
     *
     * @return array{name: string, id: int|null, title: string, description: string|null, allow: list<string>|null, max: int|null, published: bool, published_at: string|null, has_draft: bool, count: int, fallback: string|null, updated_at: string|null}
     */
    public function row(string $name, ?Region $region = null): array
    {
        $region ??= $this->regions->find($name);
        $declared = $this->regions->declared()[$name] ?? ['allow' => null, 'max' => null];

        return [
            'name' => $name,
            'id' => $region?->id,
            'title' => $this->regions->title($name),
            'description' => $this->regions->description($name),
            'allow' => $declared['allow'],
            'max' => $declared['max'],
            'published' => $region instanceof Region && $region->exists && $region->isPublished(),
            'published_at' => $region?->published_at?->toAtomString(),
            'has_draft' => $region instanceof Region && $region->hasDraft(),
            'count' => Regions::visible($region?->editingTree() ?? []),
            'fallback' => $this->regions->fallbackOf($name),
            'updated_at' => $region?->updated_at?->toAtomString(),
        ];
    }

    /**
     * The editor's state: the line, the tree being edited with its revision, a signed link to
     * the preview, and whether the markup from code can be moved into a block type.
     *
     * @return array<string, mixed>
     */
    public function describe(string $name, ?Authenticatable $user = null, ?Region $region = null): array
    {
        $region ??= $this->regions->find($name);
        $tree = $region?->editingTree() ?? [];
        $id = $user?->getAuthIdentifier();

        return $this->row($name, $region) + [
            'blocks' => $tree,
            'revision' => Content::revision($tree),
            'preview_url' => $this->preview->regionUrl($name, is_int($id) ? $id : null),
            'can_adopt' => $tree === [] && $this->canAdopt($name, $user),
        ];
    }

    /**
     * "Move the markup into a block": the fallback is known, whoever asks may write Blade, the
     * site lets anyone write it, and the type it would make does not exist yet. The editor offers
     * it over an empty region only — `describe()` adds that — since it is how a region starts.
     */
    public function canAdopt(string $name, ?Authenticatable $user): bool
    {
        return $this->regions->fallbackOf($name) !== null
            && $user instanceof HasPermissions
            && $user->hasPermission('blocks.manage')
            && (bool) $this->config->get('webx-blocks.editing', true)
            && ! Block::query()->where('slug', self::adoptedSlug($name))->exists();
    }

    /** The type the markup of a region's fallback is moved into. */
    public static function adoptedSlug(string $name): string
    {
        return 'site-'.$name;
    }
}
