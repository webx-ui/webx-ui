<?php

declare(strict_types=1);

namespace WebxUi\Media;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Media\Demo\MediaDemo;
use WebxUi\Media\Mcp\MediaTools;

/**
 * The panel section for the file library.
 *
 * What it owns is the library: folders, the files in them, and what can be done to a file. What
 * it deliberately does not own is an entity's attachments — those are copied into the entity's
 * own media when they are attached, so a product's ten thousand photographs never land in a
 * tree an editor has to browse.
 */
final class MediaModule extends AbstractModule implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly MediaDemo $demo) {}

    public function id(): string
    {
        return 'media';
    }

    public function title(): string
    {
        return (string) __('webx-media::module.title');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 500;
    }

    /**
     * The library sits in `System` rather than at the top level: the menu's first level is for
     * what a site is made of, and a file store is a tool the sections share. It leads the group
     * because it is the one an editor opens while writing, not while configuring.
     */
    public function group(): string
    {
        return 'system';
    }

    /**
     * Uploading is separated from managing on purpose: an editor who may add a picture to an
     * article is not necessarily someone who may delete a folder full of them.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['media.view', 'media.upload', 'media.manage'];
    }

    /**
     * Nothing: a file needs no other section to exist, and half of them need it (§9).
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return [];
    }

    public function seed(DemoLedger $ledger): void
    {
        $this->demo->seed($ledger);
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return MediaTools::all();
    }
}
