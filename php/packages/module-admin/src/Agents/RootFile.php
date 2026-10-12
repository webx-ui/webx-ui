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

    /** How every form of the site is built: the fields, the shell, the answer, the checks (WIDGETS §17.7). */
    public const FORMS_GUIDE = 'https://webx-ui.github.io/webx-ui/guide/forms';

    /** What a new file carries below the block, for the project to fill in. */
    public const PROJECT = <<<'MD'
        ## This project

        <!-- Yours. Notes about this site only: its languages, its tone, what not to touch.
             `php artisan webx:panel --sync` rewrites the block above and never anything here. -->
        MD;

    /** Where a theme is made, checked and moved — the engine's commands and its rules (THEMES §16). */
    public const THEMES_GUIDE = 'https://webx-ui.github.io/webx-ui/guide/themes';

    /**
     * The block itself, markers included.
     *
     * @param  list<AgentDoc>  $packages
     * @param  list<array{name: string, local: bool}>  $chain  The site's theme chain, top first; a local layer by its directory.
     */
    public static function block(array $packages, string $panelPath, array $chain = []): string
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
            '**Any form of the site is `<x-webx-form>` with `<x-webx-field>` inside**, and its controller answers with',
            '`WebxUi\Widgets\Forms\FormResponse` — a sign-in, a checkout step, a request of the Inbox alike. No markup',
            'of a field, no `fetch` and no check rules in JavaScript of your own: '.self::FORMS_GUIDE,
            '',
            'If none of these reaches what is needed, the module is missing a seam: say so and propose it at',
            'https://github.com/webx-ui/webx-ui/issues rather than working around it.',
            '',
            '## The site\'s look',
            '',
            ...self::look($chain),
            'Block types are made in the panel; the panel itself is rebranded through `--wx-*` variables, never edited.',
            'Which file holds what, what is safe to change and how to see a change: '.self::STYLES_GUIDE,
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

    /**
     * The look of this site: its chain, top first, and where a change goes — the local theme, or
     * the site's own `resources/` without one. Written from the chain, so the agent reads this
     * site's directories rather than the usual ones.
     *
     * @param  list<array{name: string, local: bool}>  $chain
     * @return list<string>
     */
    private static function look(array $chain): array
    {
        if ($chain === []) {
            return [
                'This site has no theme (`WEBX_THEME` is empty): it keeps its own `resources/css/app.css` and',
                '`resources/views/`.',
            ];
        }

        $layers = implode(' → ', array_map(
            static fn (array $layer): string => $layer['local'] ? "`{$layer['name']}/` (this site's own)" : "`{$layer['name']}`",
            $chain,
        ));
        $local = array_values(array_filter($chain, static fn (array $layer): bool => $layer['local']))[0]['name'] ?? null;

        $lines = ["The public pages are drawn by the theme chain, top first: {$layers}."];

        if ($local !== null) {
            array_push(
                $lines,
                "Change the look only in `{$local}/`, by the cheapest door: a value in `{$local}/tokens.json`, then CSS in",
                "`{$local}/src/css` on `var(--site-…)` only, then a copy of a view in `{$local}/views`. Never edit a theme in `vendor/`.",
            );
        } else {
            $lines[] = 'The site has no local theme of its own: `php artisan webx:theme:make theme --local --uses='.$chain[0]['name'].'` makes one. Never edit a theme in `vendor/`.';
        }

        array_push(
            $lines,
            'Before a change, `php artisan webx:theme:show` says which layer owns each token, view, block and icon; before you',
            'say it is done, `php artisan webx:theme:check` and the theme\'s test are clean. The rules of a theme:',
            '`vendor/webx-ui/themes/RULES.md`; the commands: '.self::THEMES_GUIDE,
        );

        return $lines;
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
