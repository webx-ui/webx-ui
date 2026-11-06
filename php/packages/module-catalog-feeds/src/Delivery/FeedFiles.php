<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Delivery;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use WebxUi\Admin\Support\PrivateDisk;

/**
 * Where the files of the feeds lie (§7.4 of the feeds spec): each build's own on the feed's disk,
 * and what a build is still writing on this machine.
 */
final class FeedFiles
{
    public const DIRECTORY = 'catalog-feeds';

    public function __construct(private readonly Config $config) {}

    public function disk(): Filesystem
    {
        $name = (string) ($this->config->get('webx-catalog-feeds.disk') ?: 'local');

        // What guards a feed is its key: on a public disk the file is there for anyone.
        PrivateDisk::fromConfig()->assert($name, 'webx-catalog-feeds.disk');

        return Storage::disk($name);
    }

    public function directory(int $feed): string
    {
        return self::DIRECTORY.'/'.$feed;
    }

    public function path(int $feed, int $build, string $extension): string
    {
        return $this->directory($feed).'/'.$build.'.'.$extension;
    }

    /** Always on this machine, whatever the feed's disk: a disk in the cloud cannot be appended to. */
    public function temporaryDirectory(): string
    {
        return storage_path('app/'.self::DIRECTORY);
    }

    /** A feed deleted takes its files along: the address is gone and nobody reads them again. */
    public function forget(int $feed): void
    {
        $this->disk()->deleteDirectory($this->directory($feed));
    }
}
