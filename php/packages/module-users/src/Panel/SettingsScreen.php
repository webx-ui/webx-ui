<?php

declare(strict_types=1);

namespace WebxUi\Users\Panel;

use Illuminate\Contracts\Container\Container;
use WebxUi\Settings\Settings;
use WebxUi\Users\Fields\Placement;
use WebxUi\Users\Fields\Requirement;
use WebxUi\Users\Fields\Requirements;
use WebxUi\Users\Fields\UserFields;

/**
 * The screen `users.settings` (spec §16.3) as the site has it: the registration open or closed,
 * and a row «hidden / optional / required» for every field the visitor fills in — the built-in
 * ones and the site's in the registration or the profile (§10.5). Made of the registry when the
 * tree is built, so a field declared in `boot()` gets its row without a patch.
 *
 * Without `module-settings` there is nowhere to keep these; the cards give way to a line that
 * says the site's config decides.
 */
final readonly class SettingsScreen
{
    public const string NAME = 'users.settings';

    public function __construct(
        private Container $container,
        private UserFields $fields,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function patch(): array
    {
        if (! $this->container->bound(Settings::class)) {
            return [
                ['op' => 'remove', 'target' => 'fields-card'],
                ['op' => 'replace', 'target' => 'registration', 'node' => [
                    'id' => 'registration',
                    'type' => 'wx-alert',
                    'props' => ['type' => 'info', 'description' => 'trans::webx-users::screen.no-settings'],
                ]],
            ];
        }

        $patch = [];
        $options = array_map(static fn (Requirement $requirement): array => [
            'value' => $requirement->value,
            'label' => 'trans::webx-users::screen.requirement-'.$requirement->value,
        ], Requirement::cases());

        foreach (Requirements::BUILT_IN as $name) {
            $patch[] = ['op' => 'add', 'target' => 'fields-card', 'node' => [
                'id' => 'require-'.str_replace('_', '-', $name),
                'type' => 'wx-segmented',
                'name' => Requirements::PREFIX.$name,
                'label' => 'trans::webx-users::screen.'.str_replace('_', '-', $name),
                'default' => Requirement::Optional->value,
                'props' => ['options' => $options],
            ]];
        }

        foreach ($this->fields->all() as $field) {
            if (! $field->in(Placement::Registration) && ! $field->in(Placement::Profile)) {
                continue;
            }

            $patch[] = ['op' => 'add', 'target' => 'fields-card', 'node' => [
                'id' => 'require-field-'.$field->name,
                'type' => 'wx-segmented',
                'name' => Requirements::PREFIX.$field->name,
                'label' => $field->node()['label'],
                'default' => ($field->required ? Requirement::Required : Requirement::Optional)->value,
                'props' => ['options' => $options],
            ]];
        }

        return $patch;
    }
}
