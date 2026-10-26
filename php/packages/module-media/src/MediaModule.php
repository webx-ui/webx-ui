<?php

declare(strict_types=1);

namespace WebxUi\Media;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasNavSection;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Panel\SystemSections;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Mcp\Tool;
use WebxUi\Media\Demo\MediaDemo;
use WebxUi\Media\Mcp\MediaTools;
use WebxUi\Media\Screens\MediaValues;

/**
 * The panel section for the file library.
 *
 * What it owns is the library: folders, the files in them, and what can be done to a file. What
 * it deliberately does not own is an entity's attachments — those are copied into the entity's
 * own media when they are attached, so a product's ten thousand photographs never land in a
 * tree an editor has to browse.
 */
final class MediaModule extends AbstractModule implements HasNavSection, ProvidesDemo, ProvidesMcpTools
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

    public function navSection(): string
    {
        return SystemSections::SITE;
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

    /**
     * How a file is written into a field of any module: the one thing about the library every
     * other module's tools need, and none of them owns.
     *
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        return [
            new McpResource(
                WebxServer::FILE_FIELDS,
                'Fields that hold library files',
                'How a cover, a photo, a logo or a gallery of any module takes its value: the forms a file '
                .'may be given in, what is kept, and what is refused. Read it before writing such a field.',
                static fn (): array => [
                    'forms' => MediaValues::FORMS,
                    'examples' => [62, ['id' => 62], ['path' => 'media/ab/cd/name.webp', 'alt' => ['en' => 'A caption']]],
                    'kept' => '{ path, alt, title } — the key and the captions. The address is worked out when the site '
                        .'prints the file, so url, width, height and the rest are never stored.',
                    'refused' => 'An id or a key the library does not have, and a file of the wrong kind where the field '
                        .'takes images (or documents) only. media_search_files finds a file by name; '
                        .'media_upload_from_url puts one in the library first.',
                    'lists' => 'A gallery or a list of files takes a list of these, in the order the site shows them.',
                    'alt' => 'A field without an alt of its own prints the file\'s alt from the library (media_get_file '
                        .'shows it, media_set_alt writes it). Describe a picture there once rather than in every field; '
                        .'give a field its own alt only where this place should say something else.',
                ],
            ),
        ];
    }
}
