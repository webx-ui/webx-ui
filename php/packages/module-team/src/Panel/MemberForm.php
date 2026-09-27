<?php

declare(strict_types=1);

namespace WebxUi\Team\Panel;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Localization\Locales;
use WebxUi\Team\Models\Member;

/**
 * The editor's screen on the server side: what its fields hold, and what a save writes (§5.7).
 *
 * The screen is `team.form`, keyed by field name, so what a person is made of is decided by the
 * description: a project's field arrives as a patch and is saved here by being on the screen at
 * all. What this class knows is which names are the person's own; `services` is a `wx-relations`
 * field and sorted out by {@see ScreenRecord} itself — and absent altogether from a site without
 * services, where a value sent under that name is dropped like any field the screen does not have.
 *
 * No draft (decision 10): a save is what the site shows, at once.
 */
final class MemberForm
{
    /**
     * The person's own fields.
     *
     * @var list<string>
     */
    private const OWN = ['name', 'job_title', 'text', 'photo', 'socials', 'published'];

    /** The fields that are a map of languages rather than a value. */
    private const TRANSLATED = ['name', 'job_title', 'text'];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly Locales $locales,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * A person and the values of their screen — what `GET`, `POST` and `PUT` answer.
     *
     * @return array{member: array<string, mixed>, values: array<string, mixed>}
     */
    public function describe(Member $member): array
    {
        return [
            'member' => [
                'id' => (int) $member->getKey(),
                'name' => MemberNames::of($member, $this->locales),
                'published' => $member->published,
                'deleted_at' => $member->deleted_at?->toAtomString(),
            ],
            'values' => $this->values($member),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(Member $member): array
    {
        return [
            // The project's fields first, so that none of them can stand in for one of the
            // person's own.
            ...($member->extraRaw() ?? []),
            'name' => $member->getTranslations('name'),
            'job_title' => $member->getTranslations('job_title'),
            'text' => $member->getTranslations('text'),
            'photo' => $member->photo,
            'socials' => $member->socialLinks(),
            'published' => $member->published,
            // Only when the screen draws it: on a site without services there is no such field.
            ...$this->record->relationValues(Member::SCREEN, $member),
        ];
    }

    /**
     * Check what came in against the screen and write it — a new person or an existing one, the
     * panel's door and an agent's alike.
     *
     * In one transaction: a person whose services were refused must not be left behind half
     * written, and a new one must not be left behind at all.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Member $member, array $input, ?callable $can = null): Member
    {
        if (array_key_exists('socials', $input)) {
            $input['socials'] = $this->socials($input['socials']);
        }

        $split = $this->record->split(Member::SCREEN, $input, self::OWN, [], $can);

        return $this->db->transaction(function () use ($member, $split): Member {
            foreach ($split->own as $field => $value) {
                $this->write($member, $field, $value);
            }

            $this->checkName($member);

            if ($split->extra !== []) {
                $member->setAttribute('extra', $this->record->merge(Member::SCREEN, $member->extraRaw(), $split->extra));
            }

            $member->save();

            $this->record->saveRelations($member, $split);

            return $member->refresh();
        });
    }

    private function write(Member $member, string $field, mixed $value): void
    {
        switch ($field) {
            case 'published':
                $member->published = (bool) $value;
                break;
            case 'photo':
                $member->photo = is_array($value) ? $value : null;
                break;
            case 'socials':
                $member->socials = $this->storedSocials($value);
                break;
            default:
                $this->translate($member, $field, $value);
        }
    }

    /**
     * The links as the repeater sent them, tidied before the screen checks them (§5.4): a row with
     * neither a network nor an address is an empty row, not a mistake, and is dropped; a row with
     * one of the two, or an address a browser would not open as a page, is a 422 under the field
     * of that row. Whether the network is one the site has is the screen's own check — the options
     * of the select come from the config (`TeamServiceProvider::registerScreens()`).
     *
     * @throws ValidationException
     */
    private function socials(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $rows = [];
        $errors = [];

        foreach (array_values($value) as $row) {
            if (! is_array($row)) {
                $rows[] = $row;

                continue;
            }

            $network = is_string($row['network'] ?? null) ? trim($row['network']) : '';
            $url = is_string($row['url'] ?? null) ? trim($row['url']) : '';

            if ($network === '' && $url === '') {
                continue;
            }

            $at = 'socials.'.count($rows);

            if ($network === '') {
                $errors["{$at}.network"] = [(string) __('webx-team::errors.network-missing')];
            }

            if ($url === '') {
                $errors["{$at}.url"] = [(string) __('webx-team::errors.url-missing')];
            } elseif (! self::isWebAddress($url)) {
                $errors["{$at}.url"] = [(string) __('webx-team::errors.url')];
            }

            $rows[] = [...$row, 'network' => $network, 'url' => $url];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    /**
     * @return list<array{network: string, url: string}>|null
     */
    private function storedSocials(mixed $value): ?array
    {
        $links = [];

        foreach (is_array($value) ? $value : [] as $row) {
            if (is_array($row) && is_string($row['network'] ?? null) && is_string($row['url'] ?? null)) {
                $links[] = ['network' => $row['network'], 'url' => $row['url']];
            }
        }

        return $links === [] ? null : $links;
    }

    /** `http://` or `https://` with a host — nothing a reader's browser would run instead. */
    public static function isWebAddress(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * The one required field (decision 9): a name in the default language — what the person is
     * called on a page nobody translated, and in the panel's list. Under the language, so the form
     * shows it under the field and on the chip of that language.
     *
     * @throws ValidationException
     */
    private function checkName(Member $member): void
    {
        $default = $this->locales->defaultCode();
        $name = $member->getTranslation('name', $default, fallback: false);

        if (is_string($name) && trim($name) !== '') {
            return;
        }

        throw ValidationException::withMessages([
            "name.{$default}" => (string) __('webx-team::errors.name-required'),
        ]);
    }

    /**
     * A translated field travels as its whole map from the form and as one string from an agent
     * speaking one language; neither is ever written over the whole field. A language the editor
     * emptied comes back as `''` or null and is taken away; a language nobody mentioned is a
     * language nobody meant to delete.
     */
    private function translate(Member $member, string $field, mixed $value): void
    {
        if (! in_array($field, self::TRANSLATED, true)) {
            return;
        }

        $map = is_array($value) ? $value : [$this->locales->current() => $value];
        $translations = [...$member->getTranslations($field), ...$map];

        $translations = array_filter(
            $translations,
            static fn (mixed $text): bool => is_string($text) && trim($text) !== '',
        );

        $member->setTranslations($field, $translations);
    }
}
