<?php

declare(strict_types=1);

namespace WebxUi\Banners\Panel;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Places;
use WebxUi\Banners\Variants;
use WebxUi\Localization\Locales;
use WebxUi\Media\Screens\MediaValues;

/**
 * The editor's screen on the server side: what its fields hold, and what a save writes (§5.6).
 *
 * The screen is `banners.form`, keyed by field name, so what a banner is made of is decided by the
 * description: a project's field arrives as a patch and is saved here by being on the screen at
 * all. The place is not on the screen (decision 9) — the list of places is alive, and the options
 * of a described select are laid on at boot — so it travels beside the values.
 *
 * What the screen cannot say of a button — which row, which field — is checked here before the
 * screen is, so that a refusal lands under `buttons.<n>.link` or `buttons.<n>.variant`, where the
 * form looks for it (`rowErrors` of `WxRepeater`).
 *
 * No draft (decision 13): a save is what the site shows, at once. In one transaction: a refusal
 * leaves neither a banner nor the row of a declared place behind.
 */
final class BannerForm
{
    /**
     * The banner's own fields.
     *
     * @var list<string>
     */
    private const OWN = ['image', 'image_mobile', 'video', 'title', 'text', 'buttons', 'enabled'];

    /** The most buttons a banner has. */
    public const MAX_BUTTONS = 3;

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly Locales $locales,
        private readonly ConnectionInterface $db,
        private readonly Places $places,
        private readonly Variants $variants,
        private readonly MediaValues $media,
        private readonly FieldTypes $types,
        private readonly ValidationFactory $validator,
    ) {}

    /**
     * A banner and the values of its screen — what `GET`, `POST` and `PUT` answer.
     *
     * @return array{banner: array<string, mixed>, values: array<string, mixed>}
     */
    public function describe(Banner $banner): array
    {
        return [
            'banner' => [
                'id' => (int) $banner->getKey(),
                'place' => $banner->place?->key,
                'title' => BannerNames::of($banner, $this->locales),
                'enabled' => $banner->enabled,
                'deleted_at' => $banner->deleted_at?->toAtomString(),
            ],
            'values' => $this->values($banner),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(Banner $banner): array
    {
        return [
            // The project's fields first, so that none of them can stand in for one of the
            // banner's own.
            ...($banner->extraRaw() ?? []),
            'image' => $banner->image,
            'image_mobile' => $banner->image_mobile,
            'video' => $banner->video,
            'title' => $banner->getTranslations('title'),
            'text' => $banner->getTranslations('text'),
            'buttons' => $banner->buttonRows(),
            'enabled' => $banner->enabled,
        ];
    }

    /**
     * Check what came in against the screen and write it — a new banner or an existing one, the
     * panel's door and an agent's alike.
     *
     * `$place` is a key: the place a new banner goes into, or the one an existing banner moves
     * to — at the end of it (decision 14). Null keeps an existing banner where it is.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Banner $banner, array $input, ?string $place = null, ?callable $can = null): Banner
    {
        $errors = [];

        if ($place === null && ! $banner->exists) {
            $errors['place'] = [(string) __('webx-banners::errors.place-unknown')];
        } elseif ($place !== null && ! $this->places->exists($place)) {
            $errors['place'] = [(string) __('webx-banners::errors.place-unknown')];
        }

        $kept = [];

        if (array_key_exists('buttons', $input)) {
            [$input['buttons'], $kept, $buttonErrors] = $this->buttons($input['buttons'], $banner);
            $errors = [...$errors, ...$buttonErrors];
        }

        $errors = [...$errors, ...$this->pictureErrors($banner, $input, $place ?? $banner->place?->key)];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $split = $this->record->split(Banner::SCREEN, $input, self::OWN, [], $can);

        return $this->db->transaction(function () use ($banner, $split, $kept, $place): Banner {
            foreach ($split->own as $field => $value) {
                $this->write($banner, $field, $field === 'buttons' ? $this->storedButtons($value, $kept) : $value);
            }

            if ($split->extra !== []) {
                $banner->setAttribute('extra', $this->record->merge(Banner::SCREEN, $banner->extraRaw(), $split->extra));
            }

            if ($place !== null) {
                // The row of a declared place appears here, with its first banner — and goes
                // away with the transaction when the save is refused after all.
                $row = $this->places->row($place);

                if ($row !== null && (! $banner->exists || $banner->place_id !== (int) $row->getKey())) {
                    $banner->moveToEndOf((int) $row->getKey());
                }
            }

            $banner->save();

            return $banner->refresh()->load('place');
        });
    }

    private function write(Banner $banner, string $field, mixed $value): void
    {
        switch ($field) {
            case 'enabled':
                $banner->enabled = (bool) $value;
                break;
            case 'image':
            case 'image_mobile':
            case 'video':
            case 'buttons':
                $banner->setAttribute($field, is_array($value) && $value !== [] ? $value : null);
                break;
            default:
                $this->translate($banner, $field, $value);
        }
    }

    /**
     * The picture is the one required field (decision 8): the three layouts stand on it, and a
     * video without one has no poster and nothing to fall back to. Checked against what the
     * banner will hold after the save — what came in, else what it has. A place of words only
     * (`'image' => false` in the config, {@see Places::needsPicture()}) takes a banner without
     * one, though not a video without its poster.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, list<string>>
     */
    private function pictureErrors(Banner $banner, array $input, ?string $place): array
    {
        $image = array_key_exists('image', $input) ? $this->media->store($input['image']) : $banner->image;

        if ($image !== null) {
            return [];
        }

        $video = array_key_exists('video', $input) ? $this->media->store($input['video']) : $banner->video;

        if ($video === null && ! $this->places->needsPicture($place)) {
            return [];
        }

        return ['image' => [(string) __($video !== null ? 'webx-banners::errors.video-without-image' : 'webx-banners::errors.image-required')]];
    }

    /**
     * The buttons as the repeater sent them, tidied before the screen checks them (§5.6).
     *
     * - A row with neither a label nor a link is an empty row, not a mistake: dropped.
     * - A row without a link, with a link the link field refuses, or with a variant the site does
     *   not have is a 422 under the field of that row — numbered as the editor sees the rows,
     *   empty ones included, so the panel puts it under the right one.
     * - A variant a button already has, taken out of the config since, is kept as it is and never
     *   shown to the screen's check (decision 11): a form that sends back what it opened with
     *   must not be refused for it.
     *
     * Answers the rows for the screen, the kept variants by the row's place among them, and the
     * errors.
     *
     * @return array{0: mixed, 1: array<int, string>, 2: array<string, list<string>>}
     */
    private function buttons(mixed $value, Banner $banner): array
    {
        if (! is_array($value)) {
            return [$value, [], []];
        }

        $stored = array_values(array_filter(array_map(
            static fn (array $row): mixed => $row['variant'] ?? null,
            $banner->buttonRows(),
        ), is_string(...)));

        $rows = [];
        $kept = [];
        $errors = [];

        foreach (array_values($value) as $index => $row) {
            if (! is_array($row)) {
                $rows[] = $row;

                continue;
            }

            $link = is_array($row['link'] ?? null) ? $row['link'] : null;
            $empty = $link === null || Link::fromArray($link)->isEmpty();

            if ($empty && ! self::hasWords($row['label'] ?? null)) {
                continue;
            }

            $at = "buttons.{$index}";

            if ($empty) {
                $errors["{$at}.link"] = [(string) __('webx-banners::errors.button-link')];
            } else {
                $problem = $this->linkProblem($link);

                if ($problem !== null) {
                    $errors["{$at}.link"] = [$problem];
                }
            }

            $variant = $row['variant'] ?? null;

            if (is_string($variant) && $variant !== '' && ! $this->variants->has($variant)) {
                if (in_array($variant, $stored, true)) {
                    $kept[count($rows)] = $variant;
                    $row['variant'] = null;
                } else {
                    $errors["{$at}.variant"] = [(string) __('webx-banners::errors.variant', ['variants' => implode(', ', $this->variants->keys())])];
                }
            }

            $rows[] = $row;
        }

        if (count($rows) > self::MAX_BUTTONS) {
            $errors['buttons'] = [(string) __('webx-banners::errors.buttons-max', ['max' => self::MAX_BUTTONS])];
        }

        return [$rows, $kept, $errors];
    }

    /**
     * What the screen let through, with the kept variants put back on their rows.
     *
     * @param  array<int, string>  $kept
     * @return list<array<string, mixed>>
     */
    private function storedButtons(mixed $value, array $kept): array
    {
        $buttons = [];

        foreach (array_values(is_array($value) ? $value : []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            if (isset($kept[$index])) {
                $row['variant'] = $kept[$index];
            }

            $buttons[] = $row;
        }

        return $buttons;
    }

    /**
     * What the link field says of a link, in its own words — null when it takes it.
     *
     * @param  array<string, mixed>  $link
     */
    private function linkProblem(array $link): ?string
    {
        $rules = $this->types->get('wx-link')?->rules([]) ?? [];
        $label = (string) __('webx-banners::screen.link');
        $validator = $this->validator->make(['value' => $link], ['value' => $rules], [], ['value' => $label]);

        return $validator->fails() ? (string) $validator->errors()->first('value') : null;
    }

    private static function hasWords(mixed $label): bool
    {
        foreach (is_array($label) ? $label : [$label] as $words) {
            if (is_string($words) && trim($words) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * A translated field travels as its whole map from the form and as one string from an agent
     * speaking one language; neither is ever written over the whole field. A language the editor
     * emptied comes back as `''` or null and is taken away; a language nobody mentioned is a
     * language nobody meant to delete.
     */
    private function translate(Banner $banner, string $field, mixed $value): void
    {
        if (! in_array($field, Banner::WORDS, true)) {
            return;
        }

        $map = is_array($value) ? $value : [$this->locales->content() => $value];
        $translations = [...$banner->getTranslations($field), ...$map];

        $translations = array_map(
            static fn (mixed $text): mixed => is_string($text) ? trim($text) : $text,
            array_filter($translations, static fn (mixed $text): bool => is_string($text) && trim($text) !== ''),
        );

        $banner->setTranslations($field, $translations);
    }
}
