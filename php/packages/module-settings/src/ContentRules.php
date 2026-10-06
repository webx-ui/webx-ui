<?php

declare(strict_types=1);

namespace WebxUi\Settings;

use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Models\Locale;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The site's house rules for whoever writes its content through MCP: the languages, the tone,
 * what not to say.
 *
 * The rules are ordinary settings under `content.*` — the screen `settings.content`, shown
 * where agents are connected — so they are stored, cached, validated and patched like any
 * other. The languages are not among them: the site already lists its languages, and a second
 * list would drift from the first. A field a project patches into that screen under
 * `content.` comes out in `more`, so a site's own rule needs no code here.
 */
final class ContentRules
{
    public const URI = WebxServer::CONTENT_RULES;

    /** The keys the resource names on its own; any other `content.*` key goes to `more`. */
    private const KNOWN = ['content.tone', 'content.donts', 'content.notes'];

    public function __construct(
        private readonly Settings $settings,
        private readonly ScreenRegistry $screens,
        private readonly Locales $locales,
    ) {}

    /**
     * Built when the registry is read, resolved when the resource is: the module is made while
     * the site boots, and taking the settings service then would pin it to that moment.
     */
    public static function resource(): McpResource
    {
        return new McpResource(
            self::URI,
            'Content rules',
            'The house rules of this site for writing content: its languages and which one is primary, the tone of voice, what never to say, notes. Read before writing or editing anything a visitor will read.',
            static fn (): array => app(self::class)->toArray(),
        );
    }

    /**
     * @return array{languages: list<array{code: string, name: string, native_name: string, primary: bool}>, primary: string, tone: string|null, donts: list<string>, notes: string|null, more: list<array{key: string, label: string, value: mixed}>, empty: bool}
     */
    public function toArray(): array
    {
        $primary = $this->locales->defaultCode();

        $languages = array_values($this->locales->all()->map(static fn (Locale $locale): array => [
            'code' => $locale->code,
            'name' => (string) $locale->name,
            'native_name' => (string) $locale->native_name,
            'primary' => $locale->code === $primary,
        ])->all());

        $tone = $this->text('content.tone');
        $notes = $this->text('content.notes');
        $donts = array_values(array_filter(
            array_map(trim(...), preg_split('/\R/', (string) $this->text('content.donts')) ?: []),
            static fn (string $line): bool => $line !== '',
        ));

        $more = [];

        foreach ($this->screens->fields(Settings::CONTENT_SCREEN) as $node) {
            $key = (string) $node['name'];
            $value = $this->settings->get($key);

            if (! str_starts_with($key, 'content.') || in_array($key, self::KNOWN, true) || $value === null || $value === '') {
                continue;
            }

            $more[] = [
                'key' => $key,
                'label' => (string) Tree::translate($node['label'] ?? $key, static fn (string $label): string => (string) __($label)),
                'value' => $value,
            ];
        }

        return [
            'languages' => $languages,
            'primary' => $primary,
            'tone' => $tone,
            'donts' => $donts,
            'notes' => $notes,
            'more' => $more,
            'empty' => $tone === null && $notes === null && $donts === [] && $more === [],
        ];
    }

    private function text(string $key): ?string
    {
        $value = $this->settings->get($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
