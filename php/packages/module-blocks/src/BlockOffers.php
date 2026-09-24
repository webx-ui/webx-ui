<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Filesystem\Filesystem;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\BlockInput;

/**
 * The block types a module brings along, for a site to take once (§3.4 of the FAQ spec).
 *
 * Types are made in the panel and live in the database (§8), and that stays: a module does not
 * register a type, it *offers* one — the file `webx:blocks:export` writes, kept in the module's
 * `resources/blocks`. A FAQ without a ready block would be an instruction to build an accordion
 * by hand; a FAQ that put its block on the site every boot would overwrite whatever the site made
 * of it. So the site installs what is offered once, and a type by that slug is never touched
 * again — it may have been rebuilt from top to bottom, exactly as {@see Demo\BlocksDemo} assumes.
 *
 * Offers are registered from providers by the module's id in the panel — the name `webx:setup`
 * asks about — so the installer can be pointed at the modules somebody just chose.
 */
final class BlockOffers
{
    public const INSTALLED = 'installed';

    public const PRESENT = 'present';

    public const DRAFT = 'draft';

    /** @var array<string, list<string>> module → files and directories of documents */
    private array $offers = [];

    public function __construct(
        private readonly Filesystem $files,
        private readonly ValidatorFactory $validator,
    ) {}

    /**
     * @param  string  $module  The module's id in the panel: `faq`.
     * @param  string  $path  One document, or a directory of `*.json` documents.
     */
    public function offer(string $module, string $path): void
    {
        $this->offers[$module][] = $path;
    }

    /**
     * @return list<string>
     */
    public function modules(): array
    {
        $modules = array_keys($this->offers);
        sort($modules);

        return $modules;
    }

    /**
     * Every offered document of these modules — all of them when none are named — as its slug
     * and what it holds, in module then slug order.
     *
     * @param  list<string>|null  $modules
     * @return list<array{module: string, slug: string, file: string, document: array<string, mixed>}>
     *
     * @throws BlocksException when a file is not a block document
     */
    public function documents(?array $modules = null): array
    {
        $offered = [];

        foreach ($this->modules() as $module) {
            if ($modules !== null && ! in_array($module, $modules, true)) {
                continue;
            }

            foreach ($this->files($module) as $file) {
                $document = json_decode((string) $this->files->get($file), true);

                if (! is_array($document)) {
                    throw new BlocksException("{$file} is not a block document.");
                }

                $slug = is_string($document['slug'] ?? null) ? $document['slug'] : basename($file, '.json');
                $document['slug'] = $slug;

                $offered[] = ['module' => $module, 'slug' => $slug, 'file' => $file, 'document' => $document];
            }
        }

        return $offered;
    }

    /** Whether the site already has a type by this slug — its own or one taken from an offer. */
    public function present(string $slug): bool
    {
        return Block::query()->where('slug', $slug)->exists();
    }

    /**
     * Puts one offered type on the site and publishes it, unless a type by its slug is there.
     *
     * The document is checked by the rules the panel checks a save with, as an import is: an
     * offer that would not survive the panel's save is the module's bug, and it is said as one
     * rather than written. A document that saves but will not publish — its template fails on
     * its own sample — is left as a draft to be fixed in the panel.
     *
     * @param  array{module: string, slug: string, file: string, document: array<string, mixed>}  $offer
     * @return self::PRESENT|self::INSTALLED|self::DRAFT
     *
     * @throws BlocksException when the document is refused
     */
    public function install(array $offer): string
    {
        if ($this->present($offer['slug'])) {
            return self::PRESENT;
        }

        $document = $offer['document'];

        $check = $this->validator->make(
            $document,
            BlockInput::rowRules(true) + BlockInput::contentRules(),
            BlockInput::messages(),
        );

        if ($check->fails()) {
            throw new BlocksException(basename($offer['file']).': '.implode(' ', $check->errors()->all()));
        }

        $block = new Block;
        $block->fill(BlockInput::values($document));
        $block->save();

        $block->saveVersion(BlockInput::content($document), BlockVersion::SOURCE_IMPORT, null, "Offered by {$offer['module']}");

        try {
            $block->publish();
        } catch (BlocksException) {
            return self::DRAFT;
        }

        return self::INSTALLED;
    }

    public function forget(): void
    {
        $this->offers = [];
    }

    /**
     * @return list<string>
     */
    private function files(string $module): array
    {
        $files = [];

        foreach ($this->offers[$module] ?? [] as $path) {
            if ($this->files->isDirectory($path)) {
                $found = $this->files->glob(rtrim($path, '/\\').'/*.json');
                sort($found);
                array_push($files, ...$found);
            } elseif ($this->files->exists($path)) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
