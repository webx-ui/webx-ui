<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Demo;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Demo\DemoWords;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorWriter;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Panel\FormOptions;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Storage\FileStore;

/**
 * A shower enclosure configurator (§15) — the case the module was designed from, with no company
 * in it and prices that are only examples: eight constructions, each with its area formula and
 * sizes, two of them with a lower height; nine glasses (colours, two gradients, a picture, one not
 * offered for bath screens); frosting seen only with the clear glasses; six kinds of hardware with
 * a percent each; and two preview rules.
 *
 * The pictures are neutral drawings, put into the library through {@see FileStore} as anybody's
 * SVG is — sanitised. The document names them `demo:<name>` and gets their keys here. The words
 * are in English, Russian and Ukrainian, and go into the site's languages by {@see DemoWords}.
 *
 * Written through {@see ConfiguratorWriter} and published, so the demo is what a person would
 * have built in the panel, with a version in its history. With `module-inbox`, it gets its own
 * request form; a form by that name already on the site is the site's own and is taken as it is.
 */
final class ConfiguratorsDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ConfiguratorWriter $writer,
        private readonly DemoWords $words,
        private readonly FileStore $store,
    ) {}

    /**
     * The library for the pictures, and the inbox for the form when it is installed.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return $this->withInbox() ? ['media', 'inbox'] : ['media'];
    }

    public function seed(DemoLedger $ledger): void
    {
        // A site with any configurator at all has built its own.
        if (Configurator::withTrashed()->exists()) {
            return;
        }

        $document = $this->read();
        $images = $this->images($ledger);
        $schema = $this->pictures($this->words->in($document['schema'] ?? []), $images);
        $title = $this->words->of($document['title'] ?? null) ?? ['en' => 'Configurator'];
        $form = $this->withInbox() && is_array($document['form'] ?? null) ? $this->form($document['form'], $ledger) : null;

        $configurator = $this->writer->create(
            is_string($document['key'] ?? null) ? $document['key'] : 'shower',
            $title,
            is_array($schema) ? $schema : null,
            $form,
            source: EntityVersion::SOURCE_IMPORT,
        );

        $this->writer->publish($configurator, source: EntityVersion::SOURCE_IMPORT);

        $ledger->created($configurator, $configurator->key);
        $ledger->createdVersionsOf($configurator);

        if ($form === null) {
            $ledger->note('the demo configurator sends no requests: the site has no inbox (webx-ui/module-inbox).');
        }
    }

    /**
     * The drawings into the root of the library, by their names without the extension.
     *
     * @return array<string, string> name → media key
     */
    private function images(DemoLedger $ledger): array
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->orderBy('lft')->first();

        if (! $root instanceof MediaDirectory) {
            throw new RuntimeException('The library has no root folder; run the migrations first.');
        }

        $keys = [];

        foreach ($this->files->glob(__DIR__.'/../../resources/demo/images/*.svg') as $path) {
            $name = pathinfo($path, PATHINFO_FILENAME);

            // Test mode: a file of the package, not an upload PHP received itself.
            $file = $this->store->store(new UploadedFile($path, basename($path), 'image/svg+xml', null, true), $root);
            $keys[$name] = $file->path;

            // The same bytes were in the library already: not the demo's to remove.
            if ($file->wasRecentlyCreated) {
                $ledger->created($file, $file->name);
            }
        }

        return $keys;
    }

    /**
     * Every `demo:<name>` in the document, replaced by the key the library gave the drawing.
     *
     * @param  array<string, string>  $images
     */
    private function pictures(mixed $value, array $images): mixed
    {
        if (is_string($value) && str_starts_with($value, 'demo:')) {
            return $images[substr($value, strlen('demo:'))] ?? throw new RuntimeException("The demo has no picture [{$value}].");
        }

        return is_array($value) ? array_map(fn (mixed $one): mixed => $this->pictures($one, $images), $value) : $value;
    }

    /**
     * The request form: name, phone and a comment, as the inbox's own demo makes its forms.
     *
     * @param  array<string, mixed>  $input
     */
    private function form(array $input, DemoLedger $ledger): ?string
    {
        $slug = is_string($input['slug'] ?? null) ? $input['slug'] : '';

        if ($slug === '') {
            return null;
        }

        if (Form::query()->where('slug', $slug)->exists()) {
            return $slug;
        }

        $options = is_array($input['options'] ?? null) ? $input['options'] : [];

        foreach (FormOptions::TRANSLATED as $key) {
            if (isset($options[$key])) {
                $options[$key] = $this->words->of($options[$key]);
            }
        }

        $form = Form::query()->create([
            'slug' => $slug,
            'title' => $this->words->of($input['title'] ?? null),
            'is_enabled' => true,
            'options' => FormOptions::clean($options),
            'position' => (int) Form::query()->max('position') + 1,
        ]);

        $ledger->created($form, $slug);
        $position = 0;

        foreach (is_array($input['fields'] ?? null) ? $input['fields'] : [] as $field) {
            if (! is_array($field) || FieldType::tryFrom((string) ($field['type'] ?? '')) === null) {
                continue;
            }

            $position += 10;

            $ledger->created(Field::query()->create([
                'form_id' => $form->getKey(),
                'name' => $field['name'] ?? null,
                'type' => $field['type'],
                'title' => $this->words->of($field['title'] ?? null),
                'options' => [],
                'is_enabled' => true,
                'is_required' => (bool) ($field['is_required'] ?? false),
                'is_fullsize' => (bool) ($field['is_fullsize'] ?? true),
                'in_table' => (bool) ($field['in_table'] ?? false),
                'position' => $position,
            ]), (string) ($field['name'] ?? 'field'));
        }

        return $slug;
    }

    private function withInbox(): bool
    {
        return $this->writer->hasInbox();
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode($this->files->get(__DIR__.'/../../resources/demo/shower.json'), true);

        return is_array($document) ? $document : throw new RuntimeException('The configurator demo document is not JSON.');
    }
}
