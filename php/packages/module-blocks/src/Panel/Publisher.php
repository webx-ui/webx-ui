<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use WebxUi\Blocks\BlockComponents;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\Exceptions\BlockNotPublishable;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Rendering\Renderer;

/**
 * The gate before a version goes live (§15): the template renders on the sample values and
 * on the values of every page the block already stands on. `Block::publish()` does the
 * sample; this does the pages first, because the sample is the author's own and the pages
 * are everybody else's.
 *
 * A type other types call has more to survive (§3.6 of the components spec): no circle of
 * calls; every type that prints it, directly or through others, still renders — on its own
 * sample and on its pages — with this draft in place of the published version; and when a module
 * declared the slug, the module's place on the module's data, since calls from view files are
 * invisible to the graph and this is the only check they get.
 */
final class Publisher
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly Usage $usage,
        private readonly BlockComponents $components,
    ) {}

    /**
     * @throws BlocksException when there is no draft
     * @throws PublishFailed when a render fails, saying on which page
     */
    public function publish(Block $block): BlockVersion
    {
        $draft = $this->draftOf($block);

        $this->check($block);

        try {
            return $block->publish($draft);
        } catch (BlockNotPublishable $failure) {
            throw PublishFailed::onSample($failure);
        }
    }

    /**
     * The checks alone, without moving the pointer — what a dry run of publishing is. Returns
     * how many instances on pages were rendered, so the caller can say what was checked.
     *
     * @throws BlocksException when there is no draft
     * @throws PublishFailed
     */
    public function check(Block $block): int
    {
        $version = $this->draftOf($block);
        $type = BlockType::fromModels($block, $version);
        $graph = new Graph;
        $checked = 0;

        $cycle = $graph->cycle($block->slug, $version->calls());

        if ($cycle !== null) {
            throw PublishFailed::onCycle($block->slug, $version->number, $cycle);
        }

        foreach ($this->usage->values($block->slug) as $instance) {
            try {
                $this->renderer->check($type, $instance['values']);
                $checked++;
            } catch (BlockNotPublishable $failure) {
                throw PublishFailed::onEntity($failure, $instance['model'], $instance['id'], $instance['title']);
            }
        }

        $checked += $this->checkParents($block, $type, $graph);

        $declared = $this->components->get($block->slug);

        if ($declared !== null) {
            try {
                $this->renderer->check($type, $this->components->sample($block->slug) + $type->sample);
            } catch (BlockNotPublishable $failure) {
                throw PublishFailed::onDeclared($failure, $declared['module']);
            }
        }

        try {
            $this->renderer->check($type);
        } catch (BlockNotPublishable $failure) {
            throw PublishFailed::onSample($failure);
        }

        return $checked;
    }

    /**
     * Every type that prints this one, on its sample and on each page it stands on, with the
     * draft called in place of the published version.
     *
     * @throws PublishFailed
     */
    private function checkParents(Block $block, BlockType $draft, Graph $graph): int
    {
        $checked = 0;
        $substitutes = [$block->slug => $draft];

        foreach ($graph->ancestors($block->slug) as $ancestor) {
            $version = $ancestor->publishedVersion;

            if (! $version instanceof BlockVersion) {
                continue;
            }

            $parent = BlockType::fromModels($ancestor, $version);
            $named = ['id' => $ancestor->id, 'slug' => $ancestor->slug, 'title' => $ancestor->title];

            foreach ($this->usage->values($ancestor->slug) as $instance) {
                try {
                    $this->renderer->check($parent, $instance['values'], $substitutes);
                    $checked++;
                } catch (BlockNotPublishable $failure) {
                    throw PublishFailed::onParent($failure, $named, [
                        'model' => $instance['model'],
                        'id' => $instance['id'],
                        'title' => $instance['title'],
                    ]);
                }
            }

            try {
                $this->renderer->check($parent, null, $substitutes);
            } catch (BlockNotPublishable $failure) {
                throw PublishFailed::onParent($failure, $named, null);
            }
        }

        return $checked;
    }

    private function draftOf(Block $block): BlockVersion
    {
        return $block->draftVersion ?? throw new BlocksException("Block '{$block->slug}' has no draft to publish.");
    }
}
