#!/usr/bin/env bash
#
# Install the PHP packages into a real Laravel application and check that the panel behaves.
#
# Shallow on purpose: the tests in php/ cover the logic, and they run under Testbench, where
# the providers are wired by hand, the database is sqlite in memory and CSRF is switched off.
# This covers what that cannot — package discovery, a real database, a real session with a real
# CSRF token, and the config and route caches a production deploy turns on.
#
# Two applications, because there are two ways in. The first is built here by hand, the way a
# site older than the skeleton was. The second is `composer create-project webx-ui/site` and
# `webx:setup`, the way a new one is made — and that half exists to be run against a real
# server, because creating the database is the step sqlite does not have.
#
# Locally:
#   scripts/php-smoke.sh
# Against MySQL, the way CI runs it:
#   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=webx DB_USERNAME=root DB_PASSWORD=secret \
#     scripts/php-smoke.sh

set -euo pipefail

MONOREPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORKDIR="${SMOKE_DIR:-$(mktemp -d)}"
APP="$WORKDIR/app"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

DB_CONNECTION="${DB_CONNECTION:-sqlite}"
PORT="${SMOKE_PORT:-8123}"
BASE="http://127.0.0.1:${PORT}"

ADMIN_EMAIL="smoke@example.test"
ADMIN_PASSWORD="correct-horse-battery-staple"

COOKIES="$WORKDIR/cookies.txt"
SERVER_PID=""

step() { printf '\n\033[1m== %s\033[0m\n' "$1"; }
note() { printf '   %s\n' "$1"; }
fail() { printf '\n\033[31mFAILED: %s\033[0m\n' "$1" >&2; exit 1; }

cleanup() {
    if [ -n "$SERVER_PID" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
}
trap cleanup EXIT

status() {
    curl -s -o /dev/null -w '%{http_code}' -c "$COOKIES" -b "$COOKIES" -H 'Accept: application/json' "$@"
}

expect() {
    local expected="$1" actual="$2" what="$3"
    [ "$actual" = "$expected" ] || fail "$what — expected $expected, got $actual"
    note "$what -> $actual"
}

# --------------------------------------------------------------------------------------------

step "A fresh Laravel application in $APP"
mkdir -p "$WORKDIR"
$COMPOSER_BIN create-project laravel/laravel "$APP" --no-interaction --prefer-dist --quiet
note "$($PHP_BIN "$APP/artisan" --version)"

# Laravel ships a static robots.txt, and a web server — `artisan serve` included — hands it over
# before the application is asked, so module-seo's route would never answer and every check of it
# below would be checking the file. A site with module-seo has no such file; neither has this one.
rm -f "$APP/public/robots.txt"

step "Point it at the packages in this checkout"
# Reusing the development root's repository definition keeps the pinned versions from drifting;
# only the path has to change, because it is relative to the application.
REPOSITORY="$(
    "$PHP_BIN" -r '
        $root = json_decode(file_get_contents($argv[1]), true);
        foreach ($root["repositories"] as $repository) {
            if (($repository["type"] ?? "") === "path") {
                $repository["url"] = $argv[2]."/php/packages/*";
                unset($repository["options"]["//"]);
                echo json_encode($repository);
                exit;
            }
        }
        fwrite(STDERR, "no path repository in php/composer.json\n");
        exit(1);
    ' "$MONOREPO/php/composer.json" "$MONOREPO"
)"

(
    cd "$APP"
    $COMPOSER_BIN config repositories.webx "$REPOSITORY"
    # Otherwise Composer could serve the released version of a package instead of the one in
    # this checkout, and the whole run would prove nothing about the change under test.
    $COMPOSER_BIN config repositories.packagist.org \
        '{"type":"composer","url":"https://repo.packagist.org","exclude":["webx-ui/*"]}'
    $COMPOSER_BIN require webx-ui/module-auth:'*' webx-ui/module-settings:'*' webx-ui/module-seo:'*' webx-ui/module-blocks:'*' webx-ui/module-pages:'*' webx-ui/module-inbox:'*' webx-ui/module-blog:'*' webx-ui/module-services:'*' webx-ui/module-faq:'*' webx-ui/module-reviews:'*' webx-ui/module-recipes:'*' webx-ui/module-events:'*' --no-interaction --no-progress --quiet
)

step "The packages came from the checkout, not from Packagist"
for package in module-admin localization mcp module-auth module-settings module-seo module-blocks module-pages module-inbox module-blog module-services module-faq module-reviews module-recipes module-events module-media nested-set routing; do
    [ -L "$APP/vendor/webx-ui/$package" ] || [ -f "$APP/vendor/webx-ui/$package/.git" ] \
        || fail "vendor/webx-ui/$package is a copy, so a released version was installed instead of this checkout"
    note "webx-ui/$package is linked to the checkout"
done

step "Providers are found by discovery, not by hand"
# Nothing in this script registers them. If extra.laravel.providers is wrong, this is where it
# shows — the tests register providers explicitly and can never notice.
"$PHP_BIN" "$APP/artisan" package:discover --quiet
"$PHP_BIN" -r '
    $manifest = require $argv[1];
    $expected = [
        "webx-ui/module-admin" => "WebxUi\\Admin\\AdminServiceProvider",
        "webx-ui/localization" => "WebxUi\\Localization\\LocalizationServiceProvider",
        "webx-ui/mcp" => "WebxUi\\Mcp\\McpServiceProvider",
        "webx-ui/module-auth" => "WebxUi\\Auth\\AuthServiceProvider",
        "webx-ui/module-settings" => "WebxUi\\Settings\\SettingsServiceProvider",
        "webx-ui/module-seo" => "WebxUi\\Seo\\SeoServiceProvider",
        "webx-ui/routing" => "WebxUi\\Routing\\RoutingServiceProvider",
        "webx-ui/module-blocks" => "WebxUi\\Blocks\\BlocksServiceProvider",
        "webx-ui/module-pages" => "WebxUi\\Pages\\PagesServiceProvider",
        "webx-ui/module-inbox" => "WebxUi\\Inbox\\InboxServiceProvider",
        "webx-ui/module-blog" => "WebxUi\\Blog\\BlogServiceProvider",
        "webx-ui/module-services" => "WebxUi\\Services\\ServicesServiceProvider",
        "webx-ui/module-faq" => "WebxUi\\Faq\\FaqServiceProvider",
        "webx-ui/module-reviews" => "WebxUi\\Reviews\\ReviewsServiceProvider",
        "webx-ui/module-recipes" => "WebxUi\\Recipes\\RecipesServiceProvider",
        "webx-ui/module-events" => "WebxUi\\Events\\EventsServiceProvider",
    ];
    foreach ($expected as $package => $provider) {
        if (! in_array($provider, $manifest[$package]["providers"] ?? [], true)) {
            fwrite(STDERR, "{$package} did not offer {$provider} to discovery\n");
            exit(1);
        }
        echo "   {$provider} discovered\n";
    }
' "$APP/bootstrap/cache/packages.php" || fail 'package discovery did not find the providers'

# Laravel reads .env immutably: the first assignment of a name wins, so appending to the file
# changes nothing. Values have to replace the ones the skeleton shipped with.
set_env() {
    "$PHP_BIN" -r '
        [, $file, $key, $value] = $argv;
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $written = false;
        foreach ($lines as $index => $line) {
            if (str_starts_with($line, $key."=")) {
                $lines[$index] = $key."=".$value;
                $written = true;
            }
        }
        if (! $written) {
            $lines[] = $key."=".$value;
        }
        file_put_contents($file, implode("\n", $lines)."\n");
    ' "$APP/.env" "$1" "$2"
}

step "Hand / to the pages module"
# A site that installs webx-ui/module-pages gives up its own front page: the home page is the
# root of the page tree, and a route here would win over the registry's fallback for good. The
# order matters — the migration that creates the home page asks the registry for `''`, and the
# registry refuses an address the application already answers.
cat > "$APP/routes/web.php" <<'PHP'
<?php

// Nothing: every public address of this site comes from the registry.
PHP
note 'the skeleton welcome route is gone'

step "Migrate on $DB_CONNECTION"
set_env DB_CONNECTION "$DB_CONNECTION"
set_env APP_URL "$BASE"
set_env SESSION_DRIVER database

if [ "$DB_CONNECTION" != "sqlite" ]; then
    set_env DB_HOST "${DB_HOST:-127.0.0.1}"
    set_env DB_PORT "${DB_PORT:-3306}"
    set_env DB_DATABASE "${DB_DATABASE:-webx}"
    set_env DB_USERNAME "${DB_USERNAME:-root}"
    set_env DB_PASSWORD "${DB_PASSWORD:-}"
fi

note "$(grep -E '^DB_CONNECTION=|^DB_DATABASE=' "$APP/.env" | tr '\n' ' ')"

[ "$DB_CONNECTION" = "sqlite" ] && : > "$APP/database/database.sqlite"

# Passport only publishes its migrations; an agent's token lives in those tables.
"$PHP_BIN" "$APP/artisan" vendor:publish --tag=passport-migrations --no-interaction --quiet

"$PHP_BIN" "$APP/artisan" migrate --force --no-interaction
note 'migrations ran'

step "Install the block types modules offer"
# module-faq, module-reviews and module-recipes ship their block types as documents, not as
# migrations: the site takes them with this command. Run it twice, because the second run must
# find nothing left to install.
"$PHP_BIN" "$APP/artisan" webx:blocks:offered --install --no-interaction > "$WORKDIR/offered.log" \
    || { cat "$WORKDIR/offered.log" >&2; fail 'webx:blocks:offered --install failed'; }
"$PHP_BIN" "$APP/artisan" webx:blocks:offered --install --no-interaction > "$WORKDIR/offered-again.log" \
    || { cat "$WORKDIR/offered-again.log" >&2; fail 'a second webx:blocks:offered --install failed'; }
note 'the offered block types are installed, and installing them again is harmless'

step "Seed the languages"
"$PHP_BIN" "$APP/artisan" webx:locales:seed --no-interaction | grep -qi 'english' \
    || fail 'webx:locales:seed did not create the configured languages'
note 'the locales table has the configured languages'

step "Create an administrator"
WEBX_ADMIN_PASSWORD="$ADMIN_PASSWORD" "$PHP_BIN" "$APP/artisan" webx:admin \
    --name=Smoke --email="$ADMIN_EMAIL" --no-interaction
note "$ADMIN_EMAIL created"

step "Generate the OAuth keys"
# A deployment step, and one worth having here: without the keys the `api` guard cannot even be
# built, so a call with no token at all answers 500 where it should answer 401.
"$PHP_BIN" "$APP/artisan" passport:keys --quiet
note 'passport:keys'

step "Give the site something with a public address"
# webx-ui/routing has no module of its own and no consumer yet, so the only way to exercise it
# in a real application is to be that consumer: a model with the trait, a type registered from a
# provider, a handler. Everything below then goes through the fallback route, which is the part
# Testbench cannot show — a fallback only means anything next to the project's own routes, and a
# route registered by a package only survives `route:cache` if it carries no closure.
mkdir -p "$APP/app/Http" "$APP/app/Console/Commands"

cat > "$APP/app/Models/SmokePage.php" <<'PHP'
<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;

class SmokePage extends Model implements Visible
{
    use HasDraft;
    use HasUrl;
    use HasVersions;

    protected $table = 'smoke_pages';

    protected $fillable = ['title', 'slug'];

    // Visible is what puts a type into the sitemap at all, so without it the map has nothing
    // of this type to leave out, and the draft check below would pass on an empty file.
    public function isVisible(?string $locale = null): bool
    {
        return $this->isPublished();
    }

    public function scopeVisible(Builder $query, ?string $locale = null): Builder
    {
        return $query->whereNotNull($this->qualifyColumn($this->publishedAtColumn()));
    }

    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->published_at;
    }
}
PHP

cat > "$APP/app/Http/SmokePageHandler.php" <<'PHP'
<?php

namespace App\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blocks\Preview\PreviewGrant;
use WebxUi\Routing\RouteHandler;

class SmokePageHandler implements RouteHandler
{
    public function handle(Request $request, object $entity, string $tail): BaseResponse
    {
        // Publication is the handler's business: a draft is a 404 to everybody but a preview.
        if (! $entity->isPublished() && PreviewGrant::of($request) === null) {
            throw new NotFoundHttpException;
        }

        return new Response('smoke page: '.$entity->slug.' / '.$entity->title, 200);
    }
}
PHP

cat > "$APP/app/Providers/SmokeRoutingServiceProvider.php" <<'PHP'
<?php

namespace App\Providers;

use App\Http\SmokePageHandler;
use App\Models\SmokePage;
use Illuminate\Support\ServiceProvider;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;

class SmokeRoutingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(RouteTypes::class)->register(new RouteType(
            type: 'smoke-page',
            model: SmokePage::class,
            formatter: Slug::class,
            handler: SmokePageHandler::class,
        ));
    }
}
PHP

cat > "$APP/app/Console/Commands/SmokePageCommand.php" <<'PHP'
<?php

namespace App\Console\Commands;

use App\Models\SmokePage;
use Illuminate\Console\Command;
use WebxUi\Blocks\Facades\Preview;

class SmokePageCommand extends Command
{
    protected $signature = 'smoke:page {slug} {--rename=} {--draft} {--preview}';

    protected $description = 'Create, publish or rename a page so that the registry, the draft and the preview have to follow';

    public function handle(): int
    {
        $page = SmokePage::query()->firstOrCreate(
            ['slug' => $this->argument('slug')],
            ['title' => 'Smoke'],
        );

        if ($this->option('preview')) {
            $this->line(Preview::url($page, adminId: 1));

            return self::SUCCESS;
        }

        if (! $page->isPublished()) {
            // The draft is what the preview shows and what publishing copies into the columns.
            $page->saveDraft(['title' => $this->option('draft') ? 'Draft only' : 'Published']);

            if (! $this->option('draft')) {
                $page->publish(authorId: 1);
            }
        }

        $rename = $this->option('rename');

        if (is_string($rename) && $rename !== '') {
            $page->update(['slug' => $rename]);
        }

        $this->line((string) $page->refresh()->slug);

        return self::SUCCESS;
    }
}
PHP

cat > "$APP/database/migrations/2026_01_01_000100_create_smoke_pages_table.php" <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smoke_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->draft();
            $table->timestamps();
        });
    }
};
PHP

"$PHP_BIN" -r '
    [, $file] = $argv;
    $source = file_get_contents($file);
    if (! str_contains($source, "SmokeRoutingServiceProvider")) {
        $source = preg_replace("/\n\];/", "\n    App\\\\Providers\\\\SmokeRoutingServiceProvider::class,\n];", $source, 1);
        file_put_contents($file, $source);
    }
' "$APP/bootstrap/providers.php"

grep -q 'SmokeRoutingServiceProvider' "$APP/bootstrap/providers.php" \
    || fail 'the smoke provider was not registered'

"$PHP_BIN" "$APP/artisan" migrate --force --no-interaction --quiet
note 'a model with HasUrl, a type, a handler and a table for them'

step "Publish the home page the pages module created"
# The migration leaves the root unpublished, because a fresh site has nothing to show there.
# Publishing it here is what puts the fallback, the `''` address and the page handler on the
# one route the tests can never reach: Testbench has no `/` of its own to compete with.
cat > "$APP/app/Console/Commands/SmokeHomeCommand.php" <<'PHP'
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use WebxUi\Pages\Models\Page;

class SmokeHomeCommand extends Command
{
    protected $signature = 'smoke:home';

    protected $description = 'Put the home page of webx-ui/module-pages on the site';

    public function handle(): int
    {
        $home = Page::home();

        if ($home === null) {
            $this->error('The migration did not create a home page.');

            return self::FAILURE;
        }

        $home->saveDraft(['title' => ['en' => 'The front page']]);
        $home->publish(authorId: 1);

        // A child of the root, to see a tree address built on a real application: `about` and
        // not `home/about`, because the root's slug is empty and drops out of the path.
        $about = Page::query()->firstWhere('parent_id', $home->getKey());

        if ($about === null) {
            $about = new Page(['title' => ['en' => 'About'], 'slug' => ['en' => 'about-pages']]);
            $about->appendTo($home);
            $about->publish(authorId: 1);
        }

        $route = $home->routeCanonical();

        $this->line($route === null ? 'home address: none' : "home address: [{$route->path}]");
        $this->line('child address: ['.($about->routeCanonical()?->path ?? 'none').']');

        return self::SUCCESS;
    }
}
PHP

SMOKE_HOME="$("$PHP_BIN" "$APP/artisan" smoke:home --no-interaction)"

echo "$SMOKE_HOME" | grep -q 'home address: \[\]' \
    || fail "the home page did not take the empty address in the registry: $SMOKE_HOME"
note 'the home page is published on the empty address'

echo "$SMOKE_HOME" | grep -q 'child address: \[about-pages\]' \
    || fail "a child of the home page is not addressed from the root: $SMOKE_HOME"
note 'its child is /about-pages, not /home/about-pages'

step "Give the site a form to receive"
# The intake of module-inbox is the one route in the ecosystem that is deliberately outside
# `VerifyCsrfToken` (§2.8 of the spec), and a POST with no token is exactly what Testbench
# cannot prove: there the middleware is off for every route anyway.
cat > "$APP/app/Console/Commands/SmokeFormCommand.php" <<'PHP'
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\SubmissionFile;

class SmokeFormCommand extends Command
{
    protected $signature = 'smoke:form
        {--count : Print how many submissions the form has}
        {--attachment : Print the ids of the last attachment received}';

    protected $description = 'Build a contact form for webx-ui/module-inbox';

    public function handle(): int
    {
        $form = Form::query()->where('slug', 'smoke-contact')->first();

        if ($this->option('count') && $form !== null) {
            $this->line('submissions: '.$form->submissions()->count());

            return self::SUCCESS;
        }

        // The address the panel would serve the last attachment at, so the script can ask for
        // it as a stranger and then as somebody with `inbox.view`.
        if ($this->option('attachment') && $form !== null) {
            $file = SubmissionFile::query()->latest('id')->first();

            if ($file === null) {
                $this->error('No attachment has been received.');

                return self::FAILURE;
            }

            $this->line("attachment: {$file->submission_id}/files/{$file->getKey()}");

            return self::SUCCESS;
        }

        $form = Form::query()->firstOrCreate(
            ['slug' => 'smoke-contact'],
            ['title' => ['en' => 'Contact us'], 'is_enabled' => true, 'options' => [
                'thank-you.heading' => ['en' => 'Thank you'],
                // The default is five a minute, and this script posts to the form six times
                // in two phases — the antispam doing its job would read here as a broken
                // intake. The limit itself is covered by the tests.
                'antispam.throttle' => 50,
            ]],
        );

        if ($form->fields()->count() === 0) {
            $form->fields()->create([
                'name' => 'name', 'type' => FieldType::Text, 'title' => ['en' => 'Name'],
                'is_required' => true, 'in_table' => true, 'position' => 0,
            ]);
            $form->fields()->create([
                'name' => 'email', 'type' => FieldType::Email, 'title' => ['en' => 'E-mail'],
                'is_required' => true, 'in_table' => true, 'position' => 1,
            ]);
            $form->fields()->create([
                'name' => 'attachment', 'type' => FieldType::File, 'title' => ['en' => 'Attachment'],
                'position' => 2,
            ]);
        }

        $this->line('form: '.$form->slug);

        return self::SUCCESS;
    }
}
PHP

"$PHP_BIN" "$APP/artisan" smoke:form --no-interaction > /dev/null
note 'a form with two fields'

step "The modules answer to artisan"
"$PHP_BIN" "$APP/artisan" webx:mcp-tools | grep -q 'admins_grant_role' \
    || fail 'the auth module offers no MCP tools'
note 'webx:mcp-tools lists the auth tools'

# --------------------------------------------------------------------------------------------

# Read the token out of the cookie jar. Signing in regenerates the session — session fixation
# is exactly what that is for — so the token has to be read again after it, not reused.
xsrf_token() {
    curl -s -o /dev/null -c "$COOKIES" -b "$COOKIES" "$BASE/api/cms/auth/csrf-cookie"

    "$PHP_BIN" -r '
        foreach (explode("\n", (string) file_get_contents($argv[1])) as $line) {
            $parts = preg_split("/\t/", trim($line));
            if (($parts[5] ?? "") === "XSRF-TOKEN") {
                echo urldecode($parts[6]);
            }
        }
    ' "$COOKIES" | tail -c 512
}

sign_in_attempt() {
    local password="$1" token
    token="$(xsrf_token)"

    [ -n "$token" ] || fail 'no XSRF-TOKEN cookie — /sanctum/csrf-cookie did not answer'

    curl -s -o /dev/null -w '%{http_code}' -c "$COOKIES" -b "$COOKIES" \
        -H 'Accept: application/json' \
        -H "X-XSRF-TOKEN: $token" \
        -d "email=$ADMIN_EMAIL" -d "password=$password" \
        "$BASE/api/cms/auth/login"
}

send_json() {
    local method="$1" url="$2" body="$3"

    curl -s -o /dev/null -w '%{http_code}' -c "$COOKIES" -b "$COOKIES" \
        -H 'Accept: application/json' -H 'Content-Type: application/json' \
        -H "X-XSRF-TOKEN: $(xsrf_token)" \
        -X "$method" -d "$body" "$url"
}

# Everything a person does to connect their agent, with curl standing in for the client.
#
# This is the one place the whole flow runs in a real application: real keys, a real session,
# a real CSRF token, and in the second phase the caches a deploy turns on. Testbench can show
# none of that, and `.well-known` routes are closures registered by a package — exactly the
# shape that `route:cache` is worst at.
#
# Sets MCP_TOKEN. Has to run while somebody is signed in: the consent screen is a panel page.
# The second argument is the "read only" box on that screen, 1 to tick it; each call registers
# a client of its own, so two connections on different terms can live side by side.
connect_an_agent() {
    local phase="$1" read_only="${2:-0}" verifier challenge client_id consent auth_token location code

    verifier="$("$PHP_BIN" -r 'echo rtrim(strtr(base64_encode(random_bytes(32)), "+/", "-_"), "=");')"
    # `--` because the verifier is base64url and one in sixty-four of them starts with a dash,
    # which php reads as an option of its own and refuses.
    challenge="$("$PHP_BIN" -r 'echo rtrim(strtr(base64_encode(hash("sha256", $argv[1], true)), "+/", "-_"), "=");' -- "$verifier")"

    curl -s "$BASE/.well-known/oauth-protected-resource/api/cms/mcp" | grep -q '"mcp:use"' \
        || fail "[$phase] the protected resource metadata says nothing"
    curl -s "$BASE/.well-known/oauth-authorization-server" | grep -q '"registration_endpoint"' \
        || fail "[$phase] the authorization server metadata says nothing"
    note "[$phase] a client finds the authorization server from the address alone"

    # An address nobody listed. This is what stops a stranger registering a client called
    # "Site panel" that takes the code to their own server. The status comes along because
    # "not refused" and "refused by the rate limiter instead" look the same otherwise, and
    # the second is what a named limiter that nothing defined looks like.
    local refused
    refused="$(curl -s -w '|%{http_code}' -X POST -H 'Content-Type: application/json' \
        -d '{"client_name":"Not Claude","redirect_uris":["https://collector.example/cb"]}' \
        "$BASE/oauth/register")"
    case "$refused" in
        *invalid_redirect_uri*'|400') ;;
        *) fail "[$phase] an unlisted redirect domain was not refused as one: $refused" ;;
    esac

    client_id="$(curl -s -X POST -H 'Content-Type: application/json' \
        -d '{"client_name":"Smoke client","redirect_uris":["http://localhost:51999/callback"]}' \
        "$BASE/oauth/register" \
        | "$PHP_BIN" -r 'echo json_decode(stream_get_contents(STDIN), true)["client_id"] ?? "";')"
    [ -n "$client_id" ] || fail "[$phase] the client could not register itself"
    note "[$phase] it registers itself, and an unlisted address does not"

    consent="$(curl -s -c "$COOKIES" -b "$COOKIES" \
        "$BASE/oauth/authorize?response_type=code&client_id=$client_id&redirect_uri=http%3A%2F%2Flocalhost%3A51999%2Fcallback&scope=mcp%3Ause&state=smoke-$phase&code_challenge=$challenge&code_challenge_method=S256")"

    # The administrator, not a visitor of the site: Passport asks whichever guard it was given,
    # and its own default is the wrong one.
    printf '%s' "$consent" | grep -q 'Smoke client' \
        || fail "[$phase] the consent page did not draw: $(printf '%s' "$consent" | head -c 400)"
    printf '%s' "$consent" | grep -q 'localhost' \
        || fail "[$phase] the consent page does not say where the code is sent"
    # The panel's own screen and not the plain one: the warning about responsibility is the
    # line a grant on file says the person was shown.
    printf '%s' "$consent" | grep -q 'You are responsible for what the agent does' \
        || fail "[$phase] the consent page is not the panel's own"
    printf '%s' "$consent" | grep -q 'name="read_only"' \
        || fail "[$phase] the consent page offers no read-only box"

    auth_token="$(printf '%s' "$consent" | grep -o 'name="auth_token" value="[^"]*"' | head -1 \
        | sed 's/.*value="//; s/"$//')"
    [ -n "$auth_token" ] || fail "[$phase] the consent page carries no auth_token"

    # Approving is a POST through the `web` group by an administrator — the step whose guard is
    # baked into the route when Passport boots, and which looks fine until somebody presses it.
    # It goes to the panel's own route, which writes the consent down on its way to Passport.
    location="$(curl -s -o /dev/null -w '%{redirect_url}' -c "$COOKIES" -b "$COOKIES" \
        -H "X-XSRF-TOKEN: $(xsrf_token)" \
        -d "auth_token=$auth_token" -d "read_only=$read_only" "$BASE/oauth/consent")"

    code="$(printf '%s' "$location" | sed 's/.*[?&]code=//; s/&.*//')"
    [ -n "$code" ] && [ "$code" != "$location" ] \
        || fail "[$phase] approving brought back no code: $location"
    note "[$phase] the administrator allows it and a code comes back"

    MCP_TOKEN="$(curl -s -H 'Accept: application/json' \
        -d 'grant_type=authorization_code' \
        -d "client_id=$client_id" \
        -d 'redirect_uri=http://localhost:51999/callback' \
        -d "code_verifier=$verifier" \
        -d "code=$code" \
        "$BASE/oauth/token" \
        | "$PHP_BIN" -r 'echo json_decode(stream_get_contents(STDIN), true)["access_token"] ?? "";')"
    [ -n "$MCP_TOKEN" ] || fail "[$phase] the code did not become a token"
    note "[$phase] and the code becomes a token"
}

run_http_checks() {
    local phase="$1"

    : > "$COOKIES"

    expect 200 "$(status "$BASE/cms")" "[$phase] the shell is public"
    expect 401 "$(status "$BASE/api/cms/manifest")" "[$phase] the manifest is closed to a stranger"

    # Installing module-auth replaces the panel's API middleware wholesale, and these two have
    # to survive it: the sign-in screen is drawn before there is anybody to authenticate.
    expect 200 "$(status "$BASE/api/cms/locales")" "[$phase] the languages are readable by a stranger"
    expect 200 "$(status "$BASE/api/cms/translations/ru")" "[$phase] so is the dictionary"

    # The real sign-in dance: the panel's API runs through the `web` group, so a POST needs a
    # CSRF token. Tests never see this — Laravel switches the check off while running them.
    expect 422 "$(sign_in_attempt 'not-the-password')" "[$phase] a wrong password is refused"
    expect 401 "$(status "$BASE/api/cms/manifest")" "[$phase] and leaves the panel closed"

    expect 200 "$(sign_in_attempt "$ADMIN_PASSWORD")" "[$phase] sign in"
    expect 200 "$(status "$BASE/api/cms/manifest")" "[$phase] the manifest opens for an administrator"
    expect 200 "$(status "$BASE/api/cms/auth/me")" "[$phase] me answers"

    # module-blog puts its three sections in a navigation group it writes into `webx-admin.groups`
    # at boot — the one place a cached config could have made that a no-op. The tests cannot see
    # it: Testbench never caches the config.
    curl -s -c "$COOKIES" -b "$COOKIES" -H 'Accept: application/json' "$BASE/api/cms/manifest" \
        | grep -q '"id":"blog"' \
        || fail "[$phase] the blog group is missing from the manifest"
    note "[$phase] the blog's navigation group survived the config cache"

    expect 200 "$(status "$BASE/api/cms/blog/articles")" "[$phase] the blog's panel API answers"

    # module-seo. Both of these are invisible to the tests: a redirect only fires because the
    # middleware reached the real `web` group, and `/robots.txt` only answers because a route
    # registered by a package survived `route:cache`.
    expect 201 "$(send_json POST "$BASE/api/cms/seo/redirects" \
        '{"match_type":"exact","pattern":"/moved-'"$phase"'","target":"/cms"}')" \
        "[$phase] a redirect is written through the panel"
    expect 301 "$(status "$BASE/moved-$phase")" "[$phase] and the public side follows it"

    expect 200 "$(send_json PUT "$BASE/api/cms/settings" \
        '{"values":{"seo.robots-txt":"User-agent: *"}}')" \
        "[$phase] the SEO tab of the settings takes a value"
    expect 200 "$(status "$BASE/robots.txt")" "[$phase] and /robots.txt answers"
    curl -s "$BASE/robots.txt" | grep -q '^User-agent: \*' \
        || fail "[$phase] /robots.txt is not the setting"
    note "[$phase] /robots.txt serves the setting"

    # webx-ui/routing. None of this is visible to the tests: the address answers only because a
    # fallback route registered by a package reached the real router, survived `route:cache`
    # without a closure in it, and lost to nothing else on the way.
    "$PHP_BIN" "$APP/artisan" smoke:page "about-$phase" --no-interaction > /dev/null

    expect 200 "$(status "$BASE/about-$phase")" "[$phase] a page in the registry answers"
    expect 301 "$(status "$BASE/About-$phase")" "[$phase] another spelling of it is a 301"
    expect 404 "$(status "$BASE/about-$phase/nothing")" "[$phase] a type that takes no tail is a 404"

    "$PHP_BIN" "$APP/artisan" smoke:page "about-$phase" --rename="moved-page-$phase" --no-interaction > /dev/null

    expect 301 "$(status "$BASE/about-$phase")" "[$phase] a rename leaves the old address behind"
    expect 200 "$(status "$BASE/moved-page-$phase")" "[$phase] and the new one answers"

    # module-blocks, the preview. The route is registered by a package and carries a signed
    # token, so this is the check that it survives `route:cache` and that the key `config:cache`
    # hands the signer is the one the link was made with.
    "$PHP_BIN" "$APP/artisan" smoke:page "draft-$phase" --draft --no-interaction > /dev/null
    PREVIEW_URL="$("$PHP_BIN" "$APP/artisan" smoke:page "draft-$phase" --preview --no-interaction | tail -n 1)"

    expect 404 "$(status "$BASE/draft-$phase")" "[$phase] an unpublished page is a 404 to a visitor"
    expect 403 "$(status "${PREVIEW_URL%%\?*}")" "[$phase] the preview without a token is a 403"
    expect 200 "$(status "$PREVIEW_URL")" "[$phase] and with the token it answers"

    curl -s -c "$COOKIES" -b "$COOKIES" "$PREVIEW_URL" | grep -q 'Draft only' \
        || fail "[$phase] the preview did not render the draft"
    note "[$phase] the preview shows the draft"

    curl -s -D - -o /dev/null -c "$COOKIES" -b "$COOKIES" "$PREVIEW_URL" | grep -qi '^X-Robots-Tag: noindex' \
        || fail "[$phase] the preview is not marked noindex"
    note "[$phase] the preview is noindex and no-store"

    # The front page through the fallback: the one address no test can exercise, because only a
    # real application has a `/` of its own to have given up.
    expect 200 "$(status "$BASE/")" "[$phase] the home page of the tree answers /"
    expect 200 "$(status "$BASE/about-pages")" "[$phase] and its child answers from the root"

    curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/about-pages" | grep -q '<html lang=' \
        || fail "[$phase] the page view of module-pages did not print the document"
    note "[$phase] the page view printed the document"

    "$PHP_BIN" "$APP/artisan" webx:routes:check --no-interaction > /dev/null \
        || fail "[$phase] webx:routes:check found problems in the registry"
    note "[$phase] webx:routes:check is quiet"

    # module-seo, the sitemap: two routes from a package that have to survive `route:cache`, and
    # a verdict per address from the resolver — the page above is in, its draft sibling is not.
    "$PHP_BIN" "$APP/artisan" webx:seo:sitemap --no-interaction > /dev/null \
        || fail "[$phase] webx:seo:sitemap failed"
    expect 200 "$(status "$BASE/sitemap.xml")" "[$phase] /sitemap.xml answers"

    page_map="$(curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/sitemap-smoke-page.xml")"
    grep -q "/moved-page-$phase<" <<< "$page_map" \
        || fail "[$phase] the published page is not in the sitemap"
    if grep -q "/draft-$phase<" <<< "$page_map"; then
        fail "[$phase] the draft page is in the sitemap"
    fi
    note "[$phase] the sitemap has the page and not the draft"

    curl -s "$BASE/robots.txt" | grep -q '^Sitemap: ' \
        || fail "[$phase] robots.txt does not name the sitemap"
    note "[$phase] robots.txt names the sitemap"

    # module-blog, the two addresses that are routes rather than registry rows. Nothing in the
    # tests can show that they survive `route:cache`, because Testbench never caches routes —
    # and a feed that only answers before a deploy is the shape this would go wrong in.
    expect 200 "$(status "$BASE/blog")" "[$phase] the blog feed answers"
    expect 200 "$(status "$BASE/blog/rss")" "[$phase] and the RSS beside it"

    curl -s -D - -o /dev/null -c "$COOKIES" -b "$COOKIES" "$BASE/blog/rss" \
        | grep -qi '^Content-Type: application/rss' \
        || fail "[$phase] the RSS did not come back as a feed"
    note "[$phase] the RSS is served as a feed"

    # module-services, the index: a route beside the registry for the same reason as the feed.
    expect 200 "$(status "$BASE/services")" "[$phase] the services index answers"

    # The intake of module-inbox: a POST with no CSRF token at all, which is the whole point of
    # the hand-built middleware stack. A page cached whole carries a token minted when the cache
    # was written, and Testbench cannot show this — there the middleware is off for every route.
    local before after answer attachment
    before="$("$PHP_BIN" "$APP/artisan" smoke:form --count --no-interaction | tr -dc '0-9')"

    answer="$(curl -s -H 'Accept: application/json' -H 'Content-Type: application/json' \
        -d "{\"fields\":{\"name\":\"Ada $phase\",\"email\":\"ada-$phase@example.test\"}}" \
        "$BASE/webx/forms/smoke-contact")"

    printf '%s' "$answer" | grep -q '"ok":true' \
        || fail "[$phase] the intake refused a form posted without a CSRF token: $answer"

    after="$("$PHP_BIN" "$APP/artisan" smoke:form --count --no-interaction | tr -dc '0-9')"
    [ "$after" -gt "$before" ] || fail "[$phase] the intake answered but wrote nothing"
    note "[$phase] a form posts without a CSRF token and the submission lands"

    expect 422 "$(
        curl -s -o /dev/null -w '%{http_code}' -H 'Accept: application/json' \
            -H 'Content-Type: application/json' -d '{"fields":{"name":"Ada"}}' \
            "$BASE/webx/forms/smoke-contact"
    )" "[$phase] and a form missing a required field is refused"

    # A submission with an attachment, posted as a browser posts a multipart form. The bytes
    # land on the module's own disk and come back only through the panel (§8).
    printf 'attached in %s\n' "$phase" > "$WORKDIR/attachment.txt"

    answer="$(curl -s -H 'Accept: application/json' \
        -F "fields[name]=Bob $phase" -F "fields[email]=bob-$phase@example.test" \
        -F "fields[attachment]=@$WORKDIR/attachment.txt" \
        "$BASE/webx/forms/smoke-contact")"

    printf '%s' "$answer" | grep -q '"ok":true' \
        || fail "[$phase] the intake refused a submission with a file: $answer"

    attachment="$("$PHP_BIN" "$APP/artisan" smoke:form --attachment --no-interaction | sed 's/.*: //' | tr -d '\r')"
    [ -n "$attachment" ] || fail "[$phase] no attachment was written"
    note "[$phase] an attachment arrives and is stored off the web root"

    curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/api/cms/inbox/submissions/$attachment" \
        | grep -q "attached in $phase" \
        || fail "[$phase] the panel did not serve the attachment back"
    note "[$phase] and the panel serves it back to somebody with inbox.view"

    # Twice, as two clients: one let in to look only, one to do whatever the administrator can.
    connect_an_agent "$phase" 1
    MCP_READ_TOKEN="$MCP_TOKEN"
    connect_an_agent "$phase"

    expect 204 "$(
        curl -s -o /dev/null -w '%{http_code}' -c "$COOKIES" -b "$COOKIES" \
            -H 'Accept: application/json' -H "X-XSRF-TOKEN: $(xsrf_token)" -X POST \
            "$BASE/api/cms/auth/logout"
    )" "[$phase] sign out"

    expect 401 "$(status "$BASE/api/cms/manifest")" "[$phase] and the panel is closed again"

    # A visitor's attachment is served by the panel and by nothing else (§8): no signed link to
    # a bucket, so there is no address that outlives the permission.
    expect 401 "$(status "$BASE/api/cms/inbox/submissions/1/files/1")" \
        "[$phase] an attachment is closed to strangers"

    # The MCP server: outside the `web` group, so no session and no CSRF — a bearer token or
    # nothing. Its routes are registered by a provider, which is exactly what the route cache
    # phase has to prove still works.
    local rpc='{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'

    expect 401 "$(status -X POST -H 'Content-Type: application/json' -d "$rpc" "$BASE/api/cms/mcp")" \
        "[$phase] the MCP server is closed to strangers"

    # The token outlives the session it was granted in — that is the whole point of it.
    local tools
    tools="$(curl -s -H 'Accept: application/json' -H 'Content-Type: application/json' \
        -H "Authorization: Bearer $MCP_TOKEN" -X POST -d "$rpc" "$BASE/api/cms/mcp")"
    printf '%s' "$tools" | grep -q '"blocks_list"' \
        || fail "[$phase] the MCP server did not list the blocks tools to a token: $tools"
    note "[$phase] and lists the blocks tools to the agent's token"

    # A token granted over OAuth carries one scope for the whole server, so a tool that writes
    # has to answer it too. Reading module scopes off such a token refuses everything, and only
    # ever where somebody has connected for real.
    local created
    created="$(curl -s -H 'Accept: application/json' -H 'Content-Type: application/json' \
        -H "Authorization: Bearer $MCP_TOKEN" -X POST \
        -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"blocks_create","arguments":{"slug":"smoke-'"$phase"'","title":"Smoke"}}}' \
        "$BASE/api/cms/mcp")"
    printf '%s' "$created" | grep -q '"slug":"smoke-'"$phase"'"' \
        || fail "[$phase] the agent's token could not write: $created"
    note "[$phase] and writes with it"

    # The connection the administrator made read-only. The choice lives in `mcp_grants`, not in
    # the token, and it is stronger than their permissions: the tools that write are neither
    # listed to it nor answered.
    local read_tools
    read_tools="$(curl -s -H 'Accept: application/json' -H 'Content-Type: application/json' \
        -H "Authorization: Bearer $MCP_READ_TOKEN" -X POST -d "$rpc" "$BASE/api/cms/mcp")"
    printf '%s' "$read_tools" | grep -q '"blocks_list"' \
        || fail "[$phase] a read-only connection was not shown the tools that look: $read_tools"
    printf '%s' "$read_tools" | grep -q '"blocks_create"' \
        && fail "[$phase] a read-only connection was shown a tool that writes"
    note "[$phase] a read-only connection sees the tools that look and not the ones that write"

    local refused_write
    refused_write="$(curl -s -H 'Accept: application/json' -H 'Content-Type: application/json' \
        -H "Authorization: Bearer $MCP_READ_TOKEN" -X POST \
        -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"blocks_create","arguments":{"slug":"read-only-'"$phase"'","title":"Nope"}}}' \
        "$BASE/api/cms/mcp")"
    printf '%s' "$refused_write" | grep -q '"error"' \
        || fail "[$phase] a read-only connection was allowed to write: $refused_write"
    note "[$phase] and is refused when it tries to write anyway"
}

serve() {
    "$PHP_BIN" "$APP/artisan" serve --host=127.0.0.1 --port="$PORT" > "$WORKDIR/serve.log" 2>&1 &
    SERVER_PID=$!

    for _ in $(seq 1 40); do
        if curl -s -o /dev/null "$BASE/cms"; then
            return
        fi
        sleep 0.5
    done

    cat "$WORKDIR/serve.log" >&2
    fail 'the application never came up'
}

step "Serve it"
serve
note "listening on $BASE"

step "Check the panel"
run_http_checks 'plain'

step "Turn on the caches a deploy turns on"
cleanup
SERVER_PID=""
"$PHP_BIN" "$APP/artisan" config:cache --quiet
"$PHP_BIN" "$APP/artisan" route:cache --quiet
note 'config:cache and route:cache'
serve

step "Check the panel again"
# The one that matters: the auth provider closes the panel by rewriting another package's
# config during register(). If that does not survive being cached, the panel is open in
# production and nowhere else.
run_http_checks 'cached'

step "Close the site with a password"
# Global middleware, switched on from `.env` through a cached config — both halves of which
# Testbench never has. What is checked is the split: the site behind the pair, and everything
# the panel, the preview and a connecting agent need in front of it.
cleanup
SERVER_PID=""
set_env WEBX_SITE_GATE true
set_env WEBX_SITE_GATE_USERS 'client:smoke-secret'
"$PHP_BIN" "$APP/artisan" config:cache --quiet
serve

: > "$COOKIES"
expect 401 "$(status "$BASE/")" '[closed] the home page asks for the password'
expect 200 "$(status -u client:smoke-secret "$BASE/")" '[closed] and opens to the pair'
expect 401 "$(status -u client:wrong "$BASE/")" '[closed] but not to a wrong one'
expect 401 "$(status "$BASE/no-such-page-closed")" '[closed] an address with no route asks too'
expect 404 "$(status -u client:smoke-secret "$BASE/no-such-page-closed")" '[closed] and is a 404 behind it'

expect 200 "$(status "$BASE/cms")" '[closed] the panel does not'
expect 200 "$(status "$BASE/api/cms/locales")" '[closed] nor does its JSON'
expect 200 "$(sign_in_attempt "$ADMIN_PASSWORD")" '[closed] an administrator signs in without the pair'
expect 200 "$(status "$BASE/.well-known/oauth-authorization-server")" '[closed] an agent finds the authorization server'
oauth_status="$(status "$BASE/oauth/authorize")"
[ "$oauth_status" != "401" ] || fail '[closed] the OAuth endpoints ask for the site password'
note "[closed] and reaches the OAuth endpoints -> $oauth_status"

"$PHP_BIN" "$APP/artisan" smoke:page "draft-closed" --draft --no-interaction > /dev/null
PREVIEW_URL="$("$PHP_BIN" "$APP/artisan" smoke:page "draft-closed" --preview --no-interaction | tail -n 1)"
expect 200 "$(status "$PREVIEW_URL")" '[closed] the preview opens under its token'
expect 401 "$(status "${PREVIEW_URL%%\?*}")" '[closed] and without one asks for the password'
expect 200 "$(status "$BASE/blocks/runtime.js")" '[closed] the block runtime the preview loads is open'

set_env WEBX_SITE_GATE false
"$PHP_BIN" "$APP/artisan" config:clear --quiet

printf '\n\033[32m== The panel installs, migrates, signs in and stays closed to strangers.\033[0m\n'

# --------------------------------------------------------------------------------------------
#
# The other way in: `composer create-project webx-ui/site`.
#
# Everything above installs the packages into an application by hand, the way a site that
# predates the skeleton does. This is the way a new one is made — one command, and `webx:setup`
# doing the rest — and the reason it needs a real server is §7 of the plan: the step that
# creates the database is the step sqlite does not have.

SITE="$WORKDIR/site"
SITE_PORT="$((PORT + 1))"
SITE_BASE="http://127.0.0.1:${SITE_PORT}"
SITE_PID=""

site_cleanup() {
    if [ -n "$SITE_PID" ] && kill -0 "$SITE_PID" 2>/dev/null; then
        kill "$SITE_PID" 2>/dev/null || true
        wait "$SITE_PID" 2>/dev/null || true
    fi
    cleanup
}
trap site_cleanup EXIT

# The database of the application above is named in this script's own environment, and Laravel
# reads `.env` immutably in both directions: a name already in the environment wins over the
# file. So the site would be told to use its own database in `.env` and then migrate the other
# one — which it does without a word, because both exist. The variables are dropped on the way
# in, and the site is left to read the file it has just been given.
site_artisan() {
    env -u DB_CONNECTION -u DB_HOST -u DB_PORT -u DB_DATABASE -u DB_USERNAME -u DB_PASSWORD \
        "$PHP_BIN" "$SITE/artisan" "$@"
}

step "Create a site from the skeleton"
# A path repository has no tags, so the skeleton would be `dev-<branch>` and `create-project`
# refuses anything below stable. The version it ships on is the shared one, and naming it here
# is the same pin the development root puts on the packages themselves.
SHARED_VERSION="$("$PHP_BIN" -r 'echo json_decode(file_get_contents($argv[1]), true)["version"];' "$MONOREPO/php/package.json")"

# Built by PHP rather than written out here, and for the same reason as the one above: Git Bash
# rewrites an argument that looks like an absolute POSIX path on its way into a child process,
# and a path buried inside a JSON string does not look like one. `/c/Work/...` then reaches
# Composer as it stands and there is no such directory on Windows.
SITE_REPOSITORY="$(
    "$PHP_BIN" -r '
        echo json_encode([
            "type" => "path",
            "url" => $argv[1]."/php/site",
            "options" => ["symlink" => false, "versions" => ["webx-ui/site" => $argv[2]]],
        ]);
    ' "$MONOREPO" "$SHARED_VERSION"
)"

$COMPOSER_BIN create-project webx-ui/site "$SITE" \
    --repository="$SITE_REPOSITORY" \
    --no-install --no-scripts --no-interaction --quiet
note "webx-ui/site $SHARED_VERSION in $SITE"

[ -f "$SITE/resources/views/components/layout.blade.php" ] || fail 'the skeleton brought no layout'
# At the start of a line: the file explains this very trap in prose, and names it while doing so.
grep -qE '^[[:space:]]*(\\?Illuminate|Route::)' "$SITE/routes/web.php" \
    && fail 'the skeleton declares a route, and / belongs to the page tree'
note 'a layout, and no routes taking addresses from the registry'

(
    cd "$SITE"
    $COMPOSER_BIN config repositories.webx "$REPOSITORY"
    $COMPOSER_BIN config repositories.packagist.org \
        '{"type":"composer","url":"https://repo.packagist.org","exclude":["webx-ui/*"]}'
    $COMPOSER_BIN install --no-interaction --no-progress --quiet
)

# What `post-create-project-cmd` does before handing over to the command; `--no-install` above
# means Composer has not run the install-time half of it either.
cp "$SITE/.env.example" "$SITE/.env"
site_artisan key:generate --quiet

step "Run webx:setup"
SETUP_DB="${DB_DATABASE:-webx}_site"

# The command finds Composer on the PATH or as a `composer.phar` beside the application, which
# is every machine CI runs on and not every machine a person has. Naming it is the third way.
SETUP_COMPOSER=()
COMPOSER_PHAR="$(printf '%s' "$COMPOSER_BIN" | grep -o '[^ ]*composer\.phar' || true)"
[ -n "$COMPOSER_PHAR" ] && SETUP_COMPOSER=(--composer="$COMPOSER_PHAR")

if [ "$DB_CONNECTION" = "sqlite" ]; then
    SETUP_DATABASE=(--db-connection=sqlite)
else
    SETUP_DATABASE=(
        --db-connection="$DB_CONNECTION"
        --db="$SETUP_DB"
        --db-host="${DB_HOST:-127.0.0.1}"
        --db-port="${DB_PORT:-3306}"
        --db-username="${DB_USERNAME:-root}"
        --db-password="${DB_PASSWORD:-}"
    )
fi

# `--no-build` because the npm halves come from the registry, and a run that installs them is
# testing what has been released rather than what is in this checkout.
WEBX_ADMIN_PASSWORD="$ADMIN_PASSWORD" site_artisan webx:setup \
    --no-interaction \
    --name='Smoke Site' \
    --domain="127.0.0.1:${SITE_PORT}" \
    --modules=all \
    --locales=en,ru \
    --admin="site@example.test" \
    --admin-name=Smoke \
    --demo \
    --no-build \
    "${SETUP_DATABASE[@]}" "${SETUP_COMPOSER[@]}"

step "What setup left behind"
# One assignment, not two: `.env` is read immutably and the first one wins, so a value appended
# under one already there is a value nobody reads.
[ "$(grep -c '^DB_DATABASE=' "$SITE/.env")" = "1" ] || fail '.env has more than one DB_DATABASE'
grep -q '^APP_URL=http://127.0.0.1' "$SITE/.env" || fail 'APP_URL did not follow the domain'
note '.env replaced rather than appended to'

for call in 'pages()' 'inbox()' 'blog()' 'admins()'; do
    grep -qF "$call" "$SITE/resources/js/admin.ts" || fail "resources/js/admin.ts does not register $call"
done
note 'the entry file registers every module that was installed'

grep -q "'layout' => env('WEBX_PAGES_LAYOUT', 'layout')" "$SITE/config/webx-pages.php" \
    || fail 'the pages module was not pointed at the layout'
note 'the public views stand in the layout of the skeleton'

[ -f "$SITE/storage/app/webx-demo.json" ] || fail 'the demo journal is not there'
note 'the demo journal is there'

step "Run webx:doctor"
# Against the real database, which is the half of this the doctor cannot be tested on anywhere
# else. Exactly one refusal is expected and it is the bundle: this run passed `--no-build`, so
# there is nothing in `public/build` — and a doctor that does not notice that is worth nothing.
site_artisan webx:doctor > "$WORKDIR/doctor.log" 2>&1 || true

grep -q 'npm run build' "$WORKDIR/doctor.log" \
    || { cat "$WORKDIR/doctor.log" >&2; fail 'the doctor did not notice that nothing was built'; }

grep -q '1 of the things this site needs is not in place' "$WORKDIR/doctor.log" \
    || { cat "$WORKDIR/doctor.log" >&2; fail 'the doctor refused something other than the missing bundle'; }

note 'the doctor checks a real site and names the one thing missing from it'

step "Serve the site"
site_artisan serve --host=127.0.0.1 --port="$SITE_PORT" > "$WORKDIR/site-serve.log" 2>&1 &
SITE_PID=$!

for _ in $(seq 1 40); do
    if curl -s -o /dev/null "$SITE_BASE/"; then
        break
    fi
    sleep 0.5
done

HOME_PAGE="$(curl -s "$SITE_BASE/")"

printf '%s' "$HOME_PAGE" | grep -q '<!doctype html>' || fail "the home page is not a document: $HOME_PAGE"
# The layout seam, both halves of it: the skeleton's header around the module's view, and a
# metatag that only reaches the head through @stack.
printf '%s' "$HOME_PAGE" | grep -q 'site-header' || fail 'the home page did not stand in the layout'
printf '%s' "$HOME_PAGE" | grep -q '<title>' || fail 'the SEO card did not reach the head'
note 'the demo home page renders inside the layout, with its head filled in'

expect 200 "$(status "$SITE_BASE/")" 'GET /'

step "Run webx:setup a second time"
# The promise in the plan: the same command on a site that is already up adds what is missing
# and changes nothing else. Anything that publishes on every run — Passport's migrations are
# stamped with the time they were copied — turns the next `migrate` into a duplicate table.
BEFORE="$("$PHP_BIN" -r 'echo md5_file($argv[1]).md5_file($argv[2]).md5_file($argv[3]);' \
    "$SITE/resources/js/admin.ts" "$SITE/package.json" "$SITE/.env")"

site_artisan webx:setup \
    --no-interaction \
    --name='Smoke Site' \
    --domain="127.0.0.1:${SITE_PORT}" \
    --modules=all \
    --locales=en,ru \
    --admin="site@example.test" \
    --demo \
    --no-build \
    "${SETUP_DATABASE[@]}" "${SETUP_COMPOSER[@]}" > "$WORKDIR/setup-again.log" 2>&1 \
    || { cat "$WORKDIR/setup-again.log" >&2; fail 'the second webx:setup did not succeed'; }

grep -q 'Nothing to migrate' "$WORKDIR/setup-again.log" || {
    cat "$WORKDIR/setup-again.log" >&2
    fail 'the second run had migrations to apply, so something published itself twice'
}

AFTER="$("$PHP_BIN" -r 'echo md5_file($argv[1]).md5_file($argv[2]).md5_file($argv[3]);' \
    "$SITE/resources/js/admin.ts" "$SITE/package.json" "$SITE/.env")"

[ "$BEFORE" = "$AFTER" ] || fail 'the second webx:setup changed the files it had already written'
note 'the second run changed nothing'

expect 200 "$(status "$SITE_BASE/")" 'GET / after the second run'

printf '\n\033[32m== And one command turns an empty directory into a site with a panel on it.\033[0m\n'
