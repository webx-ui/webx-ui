<?php

declare(strict_types=1);

namespace WebxUi\Settings\Console;

use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use WebxUi\Settings\Contacts\Contacts;
use WebxUi\Settings\Contacts\ContactsCheck;
use WebxUi\Settings\Contacts\PhoneNumber;
use WebxUi\Settings\Settings;

/**
 * Moves contacts a site kept under keys of its own — a project's patch that added
 * `contacts.phone` before the "Contacts" tab existed — into the tab's lists (WIDGETS §12.1).
 *
 *     php artisan webx:settings:contacts --from=contacts.phone --from=contacts.email
 *
 * What a value is, is read from the value: an e-mail by its @, a phone number by parsing, an
 * address otherwise — `--as=phones|emails|addresses` says it outright. A value already on the
 * list is not added twice, so running it again changes nothing. The old key stays where it is:
 * it belongs to the project's patch, which the project removes when nothing reads it.
 */
final class MoveContactsCommand extends Command
{
    protected $signature = 'webx:settings:contacts
        {--from=* : The key the contact is stored under now}
        {--as= : phones, emails or addresses; read from the value when left out}
        {--dry-run : Say what would move and move nothing}';

    protected $description = 'Move phone numbers, e-mails and addresses kept under keys of your own into the Contacts tab of the settings';

    private const array FIELDS = ['phones' => 'number', 'emails' => 'email', 'addresses' => 'address'];

    public function handle(Settings $settings): int
    {
        $from = array_values(array_filter((array) $this->option('from'), is_string(...)));
        $as = $this->option('as');

        if ($from === []) {
            $this->components->error('Name the key to move with --from=<key>.');

            return self::FAILURE;
        }

        if ($as !== null && ! isset(self::FIELDS[$as])) {
            $this->components->error('--as is phones, emails or addresses.');

            return self::FAILURE;
        }

        $raw = $settings->raw();
        $lists = [];

        foreach (array_keys(self::FIELDS) as $list) {
            $stored = $raw[Contacts::KEYS[$list]] ?? null;
            $lists[$list] = is_array($stored) ? array_values($stored) : [];
        }

        $moved = 0;

        foreach ($from as $key) {
            if (! array_key_exists($key, $raw)) {
                $this->components->warn("Nothing is stored under {$key}.");

                continue;
            }

            foreach (self::values($raw[$key]) as $value) {
                $list = $as ?? self::kind($value);
                $field = self::FIELDS[$list];
                // The address is the one per-language field of the three: a map stays a map.
                $plain = is_array($value) ? (string) (reset($value) ?: '') : $value;

                if (in_array($plain, array_map(static fn (mixed $row): string => is_array($row) ? self::plain($row[$field] ?? null) : '', $lists[$list]), true)) {
                    continue;
                }

                $lists[$list][] = [$field => $list === 'addresses' ? $value : $plain];
                $this->components->twoColumnDetail($key, Contacts::KEYS[$list].' ← '.$plain);
                $moved++;
            }
        }

        if ($moved === 0) {
            $this->components->info('Nothing to move.');

            return self::SUCCESS;
        }

        $changes = [];

        foreach ($lists as $list => $rows) {
            $changes[Contacts::KEYS[$list]] = $rows;
        }

        try {
            ContactsCheck::check($changes);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->components->error("{$field}: {$message}");
                }
            }

            return self::FAILURE;
        }

        if ((bool) $this->option('dry-run')) {
            $this->components->info("{$moved} would move; nothing was written.");

            return self::SUCCESS;
        }

        $settings->save($changes);
        $this->components->info("{$moved} moved. Check the Contacts tab, then remove the old key from your screen patch.");

        return self::SUCCESS;
    }

    /**
     * A value, a list of them, or a map of languages (one value in several).
     *
     * @return list<string|array<string, string>>
     */
    private static function values(mixed $stored): array
    {
        if (is_string($stored)) {
            return array_values(array_filter(array_map(trim(...), preg_split('/\R/', $stored) ?: []), static fn (string $one): bool => $one !== ''));
        }

        if (! is_array($stored)) {
            return [];
        }

        if (! array_is_list($stored)) {
            $map = array_filter($stored, static fn (mixed $one): bool => is_string($one) && trim($one) !== '');

            return $map === [] ? [] : [array_map(trim(...), $map)];
        }

        $values = [];

        foreach ($stored as $one) {
            array_push($values, ...self::values(is_array($one) ? ($one['value'] ?? $one['number'] ?? $one['email'] ?? null) : $one));
        }

        return $values;
    }

    /** @param  string|array<string, string>  $value */
    private static function kind(string|array $value): string
    {
        $plain = is_array($value) ? (string) reset($value) : $value;

        return match (true) {
            filter_var($plain, FILTER_VALIDATE_EMAIL) !== false => 'emails',
            PhoneNumber::problem($plain) !== PhoneNumber::INVALID && preg_match('/^[\d\s()+.\/-]+$/', $plain) === 1 => 'phones',
            default => 'addresses',
        };
    }

    private static function plain(mixed $value): string
    {
        return is_array($value) ? (string) (reset($value) ?: '') : (is_string($value) ? $value : '');
    }
}
