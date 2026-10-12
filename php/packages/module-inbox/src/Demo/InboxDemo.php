<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Demo\DemoWords;
use WebxUi\Admin\Demo\ThemeDemo;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Panel\FieldOptions;
use WebxUi\Inbox\Panel\FormOptions;
use WebxUi\Localization\Locales;

/**
 * One feedback form, the one every site turns out to need (§9 of the new-site spec), and the
 * forms the site's theme keeps in `demo/inbox/*.json` (THEMES §15.1) — `theme-default` shows
 * every kind of field there, on its showcase. A theme's form by the slug `contact` replaces
 * the module's.
 *
 * No recipients: a demo that mailed somebody the first time a visitor pressed Send would be a
 * demo that sent mail from an address nobody has set up yet. Submissions land in the panel,
 * which is the half worth looking at, and the site fills in who is told about them.
 */
final class InboxDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        foreach ($this->documents() as $document) {
            $this->form($ledger, $document);
        }
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function form(DemoLedger $ledger, array $document): void
    {
        $slug = (string) ($document['slug'] ?? 'contact');

        // A form by this name is somebody's own, answers and all.
        if (Form::query()->where('slug', $slug)->exists()) {
            return;
        }

        $form = Form::query()->create([
            'slug' => $slug,
            'title' => $this->words($document['title'] ?? $slug),
            'is_enabled' => true,
            'options' => $this->options(is_array($document['options'] ?? null) ? $document['options'] : []),
            'position' => 0,
        ]);

        $ledger->created($form, $slug);

        $position = 0;

        foreach (is_array($document['fields'] ?? null) ? $document['fields'] : [] as $field) {
            if (! is_array($field)) {
                continue;
            }

            $position += 10;

            // The fields go in the journal too: they are rows of their own, and deleting the
            // form is refused once an answer has arrived through it.
            $type = FieldType::tryFrom((string) ($field['type'] ?? 'text')) ?? FieldType::Text;

            $ledger->created(Field::query()->create([
                'form_id' => $form->getKey(),
                'name' => $field['name'] ?? null,
                'type' => $type->value,
                'title' => $this->words($field['title'] ?? ''),
                'placeholder' => $this->words($field['placeholder'] ?? ''),
                'help' => $this->words($field['help'] ?? ''),
                'is_enabled' => true,
                'is_required' => (bool) ($field['is_required'] ?? false),
                'is_fullsize' => (bool) ($field['is_fullsize'] ?? true),
                'in_table' => (bool) ($field['in_table'] ?? false),
                'position' => $position,
                // By the gate the panel's save goes through; choices and a consent's words keep
                // their language maps, which the field reads in the language of the page.
                'options' => FieldOptions::clean($type, is_array($field['options'] ?? null) ? $field['options'] : []),
            ]), (string) ($field['name'] ?? 'field'));
        }
    }

    /**
     * The settings, with the words in them turned into the site's default language — the
     * fixture writes a string, and the column holds a map.
     *
     * Through `FormOptions::clean()` so that the demo goes in by the same gate the panel and
     * an agent do; a key it does not know is dropped here as it would be there.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function options(array $options): array
    {
        foreach (FormOptions::TRANSLATED as $key) {
            if (isset($options[$key]) && (is_string($options[$key]) || is_array($options[$key]))) {
                $options[$key] = $this->words($options[$key]);
            }
        }

        return FormOptions::clean($options);
    }

    /**
     * @return array<string, string>|null
     */
    private function words(mixed $text): ?array
    {
        return (new DemoWords($this->locales))->of($text);
    }

    /**
     * The theme's forms first, then the module's `contact` unless the theme brought one.
     *
     * @return list<array<string, mixed>>
     */
    private function documents(): array
    {
        $files = [];
        $theme = ThemeDemo::directory('inbox');

        if ($theme !== null) {
            $files = $this->files->glob($theme.'/*.json');
            sort($files);
        }

        $documents = [];

        foreach ([...$files, __DIR__.'/../../resources/demo/contact.json'] as $file) {
            $document = json_decode((string) $this->files->get($file), true);

            if (! is_array($document)) {
                throw new RuntimeException(basename($file).' is not a form document.');
            }

            $documents[(string) ($document['slug'] ?? basename($file, '.json'))] ??= $document;
        }

        return array_values($documents);
    }
}
