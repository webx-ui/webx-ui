<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

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
 */
final class Publisher
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly Usage $usage,
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
        $type = BlockType::fromModels($block, $this->draftOf($block));
        $checked = 0;

        foreach ($this->usage->values($block->slug) as $instance) {
            try {
                $this->renderer->check($type, $instance['values']);
                $checked++;
            } catch (BlockNotPublishable $failure) {
                throw PublishFailed::onEntity($failure, $instance['model'], $instance['id'], $instance['title']);
            }
        }

        try {
            $this->renderer->check($type);
        } catch (BlockNotPublishable $failure) {
            throw PublishFailed::onSample($failure);
        }

        return $checked;
    }

    private function draftOf(Block $block): BlockVersion
    {
        return $block->draftVersion ?? throw new BlocksException("Block '{$block->slug}' has no draft to publish.");
    }
}
