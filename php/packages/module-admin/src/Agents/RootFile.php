<?php

declare(strict_types=1);

namespace WebxUi\Admin\Agents;

/**
 * The site's root AGENTS.md: one block the installer owns, everything else the project's.
 *
 * The block says what an agent cannot learn from the site's own code — that the modules live
 * in `vendor`, that they are not to be edited there, and where each one explains itself. It is
 * rewritten whole on every run, so it follows what is installed; the text around it is read by
 * nobody and rewritten by nothing, like the entry file outside its markers.
 */
final class RootFile
{
    public const START = '<!-- webx:agents -->';

    public const END = '<!-- /webx:agents -->';

    /**
     * Where the site's own look lives and what is safe to change in it.
     *
     * A page of the documentation rather than a file in `vendor`: it is about the site's files,
     * which no package version changes, and it is written for the person beside the agent too.
     */
    public const STYLES_GUIDE = 'https://webx-ui.github.io/webx-ui/guide/styles';

    /** What a new file carries below the block, for the project to fill in. */
    public const PROJECT = <<<'MD'
        ## This project

        <!-- Yours. Notes about this site only: its languages, its tone, what not to touch.
             `php artisan webx:panel --sync` rewrites the block above and never anything here. -->
        MD;

    /**
     * The block itself, markers included.
     *
     * @param  list<AgentDoc>  $packages
     */
    public static function block(array $packages, string $panelPath): string
    {
        $documented = array_values(array_filter($packages, static fn (AgentDoc $doc): bool => $doc->documented));
        $undocumented = array_values(array_filter($packages, static fn (AgentDoc $doc): bool => ! $doc->documented));
        $panel = '/'.trim($panelPath, '/');

        $lines = [
            self::START,
            '<!-- Written by `php artisan webx:panel --sync`; everything up to the closing marker is replaced on the next run. -->',
            '',
            '# This site',
            '',
            'A Laravel site built on [WebX UI](https://github.com/webx-ui/webx-ui). The content, the admin panel at',
            "`{$panel}` and the public pages of every section come from the Composer packages `webx-ui/*` in",
            '`vendor/`; the site keeps its own layout, views, styles and configuration.',
            '',
            '## The first rule',
            '',
            '**Never fork a module and never edit `vendor/`.** `composer update` overwrites it, and a fork stops',
            'getting fixes. Change behaviour from the site, in this order:',
            '',
            '1. Configuration — `php artisan vendor:publish --tag=webx-<module>-config`, then `config/webx-<module>.php`.',
            '2. Views — `--tag=webx-<module>-views`, then keep only the files you actually change.',
            '3. Panel screens — a patch, `Screens::extend(\'<screen>\', [...])` in `AppServiceProvider::boot()`.',
            '4. Registries and services — register your own beside the package\'s, or bind a replacement in the container.',
            '',
            'If none of these reaches what is needed, the module is missing a seam: say so and propose it at',
            'https://github.com/webx-ui/webx-ui/issues rather than working around it.',
            '',
            '## The site\'s look',
            '',
            'The public pages are the site\'s own `resources/css/app.css` and `resources/views/`; block types are made',
            'in the panel; the panel itself is rebranded through `--wx-*` variables, never edited. Which file holds',
            'what, what is safe to change and how to see a change: '.self::STYLES_GUIDE,
            '',
            '## Installed packages',
            '',
        ];

        if ($documented === []) {
            $lines[] = 'None of the installed packages carries an AGENTS.md yet; read their README.md in `vendor/`.';
        } else {
            $lines[] = 'Each one explains itself in the version installed here. Read the guide of the area you touch first.';
            $lines[] = '';

            foreach ($documented as $doc) {
                $lines[] = "- [{$doc->name}]({$doc->link()})".($doc->description === '' ? '' : " — {$doc->description}");
            }
        }

        if ($undocumented !== [] && $documented !== []) {
            $lines[] = '';
            $lines[] = 'Without a guide yet, read their README.md: '
                .implode(', ', array_map(static fn (AgentDoc $doc): string => "`{$doc->name}`", $undocumented)).'.';
        }

        array_push(
            $lines,
            '',
            '## After changing packages',
            '',
            '- `composer update "webx-ui/*" -W`, then `php artisan webx:panel --sync`, then `npm install && npm run build`.',
            '- `php artisan migrate` when a release brings migrations.',
            '- `php artisan webx:doctor` says what is misconfigured, and how to fix it.',
            self::END,
        );

        return implode("\n", $lines);
    }

    public function __construct(private readonly ?string $contents) {}

    /**
     * The file with the block in place.
     *
     * A file written by hand before the installer ever ran keeps every word: the block goes on
     * top, because the first rule is the one an agent has to read first.
     */
    public function with(string $block): string
    {
        if ($this->contents === null || trim($this->contents) === '') {
            return $block."\n\n".self::PROJECT."\n";
        }

        $start = strpos($this->contents, self::START);
        $end = $start === false ? false : strpos($this->contents, self::END, $start);

        if ($start === false || $end === false) {
            return $block."\n\n".ltrim($this->contents);
        }

        return substr($this->contents, 0, $start)
            .$block
            .substr($this->contents, $end + strlen(self::END));
    }

    public function hasBlock(): bool
    {
        return $this->contents !== null
            && str_contains($this->contents, self::START)
            && str_contains($this->contents, self::END);
    }
}
