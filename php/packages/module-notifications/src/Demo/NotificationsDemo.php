<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Localization\Locales;
use WebxUi\Notifications\Channels\ChannelForm;
use WebxUi\Notifications\Models\Channel;

/**
 * One channel to show what the section holds (§16.2): an e-mail channel for any event, through
 * the site's mail, to a made-up address at example.com — and switched off.
 *
 * Off and without a secret, because a demo must not write to anybody; an administrator who wants
 * it puts an address of their own in first. Its template shows the syntax an empty form would
 * hide: a section that is there or not, a list with its rows, a comment.
 *
 * It is saved through the channel's form, so a demo template is held to the rules a typed one is.
 */
final class NotificationsDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly ChannelForm $form,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        // A site with any channel at all, in the bin too, has set up its own.
        if (Channel::withTrashed()->exists()) {
            return;
        }

        foreach ((array) ($this->read()['channels'] ?? []) as $input) {
            if (! is_array($input) || ! is_string($input['key'] ?? null) || ! is_string($input['provider'] ?? null)) {
                continue;
            }

            $saved = $this->form->save(new Channel, [
                'name' => $this->name($input['name'] ?? null, $input['key']),
                'provider' => $input['provider'],
                'event' => null,
                'options' => is_array($input['options'] ?? null) ? $input['options'] : [],
                'recipients' => is_array($input['recipients'] ?? null) ? $input['recipients'] : [],
                'subject' => $this->texts($input['subject'] ?? null),
                'template' => $this->texts($input['template'] ?? null),
                'enabled' => false,
            ]);

            $ledger->created($saved['channel'], $input['key']);
        }
    }

    /**
     * The name in the site's default language when the demo has it, else the English one: a
     * channel's name is one string, for the administrators.
     */
    private function name(mixed $name, string $key): string
    {
        if (! is_array($name)) {
            return $key;
        }

        $default = $name[$this->locales->defaultCode()] ?? $name['en'] ?? null;

        return is_string($default) && trim($default) !== '' ? trim($default) : $key;
    }

    /**
     * The texts in the languages the site has; the English one under the main language when the
     * site has none of the demo's.
     *
     * @return array<string, string>
     */
    private function texts(mixed $texts): array
    {
        if (! is_array($texts)) {
            return [];
        }

        $kept = [];

        foreach ($texts as $locale => $text) {
            if (is_string($locale) && is_string($text) && $this->locales->has($locale)) {
                $kept[$locale] = $text;
            }
        }

        if ($kept === [] && is_string($texts['en'] ?? null)) {
            $kept[$this->locales->defaultCode()] = $texts['en'];
        }

        return $kept;
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/notifications.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('notifications.json is not a list of channels.');
        }

        return $document;
    }
}
