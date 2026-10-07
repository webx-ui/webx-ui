<?php

declare(strict_types=1);

namespace WebxUi\Inbox;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Notes\NoteTypes;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Inbox\Antispam\Throttle;
use WebxUi\Inbox\Audit\NoRecipients;
use WebxUi\Inbox\Audit\NotificationTrouble;
use WebxUi\Inbox\Console\PruneSubmissionsCommand;
use WebxUi\Inbox\Events\SubmissionStored;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Panel\InboxModule;
use WebxUi\Inbox\Relations\FormTarget;
use WebxUi\Inbox\Rendering\Assets;
use WebxUi\Inbox\Submissions\Handlers;
use WebxUi\Inbox\Support\Forms;

class InboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-inbox.php', 'webx-inbox');

        // Looked up by the rate limiter and then by the controller, within one request.
        $this->app->scoped(Forms::class);

        // What a response has already printed, which is a fact about the response.
        $this->app->scoped(Assets::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-inbox');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-inbox');

        $this->registerRateLimiter();

        // `<x-webx-inbox::form slug="contact" />` — the whole public half of the module, as one
        // tag (§10). A class component rather than an anonymous one, because what it prints is
        // decided by a form, a guard and a captcha that all come out of the container.
        //
        // A namespace rather than an alias: the prefix is the one the views already answer to,
        // so the tag names the package to install, and the next component of this module is a
        // file in the same directory rather than another global name to keep clear of.
        Blade::componentNamespace('WebxUi\\Inbox\\View\\Components', 'webx-inbox');

        $this->loadRoutesFrom(__DIR__.'/../routes/public.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->app->make(ModuleRegistry::class)->register($this->app->make(InboxModule::class));

        // A form is something another record can choose — a vacancy its application form. A
        // form deleted takes the rows pointing at it along; one with submissions is not deleted.
        $this->app->make(RelationTargets::class)->register(new FormTarget);

        // The site audit's check of the forms, when the audit is installed: a switched-on form
        // that would write to nobody saves every enquiry and tells nobody about any of them.
        if (class_exists(AuditChecks::class)) {
            $this->app->make(AuditChecks::class)->register($this->app->make(NoRecipients::class));
        }

        // Notes on a submission are the panel's own feature, not this module's (§2.17): the
        // table, the trait and the endpoint live in `module-admin`, and what is said here is
        // only that submissions are one of the things that carry them — under an alias, so
        // the address reads `entities/inbox_submission/17/notes` and never a class name.
        $this->app->make(NoteTypes::class)->register(Submission::MORPH, Submission::class);

        // The handlers of `webx-inbox.handlers` (§2.19). A site that wants something else listens
        // to the same event itself; this one only reads the config.
        $this->app->make(Dispatcher::class)->listen(SubmissionStored::class, Handlers::class);

        // Letters that failed or wait for a worker nobody runs — only when
        // `webx-ui/module-audit` is installed.
        if (class_exists(AuditChecks::class)) {
            $this->app->make(AuditChecks::class)->register($this->app->make(NotificationTrouble::class));
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        // Not something a schedule is given by default (§15): what it forgets is a visitor's
        // enquiry, and the site says how long it keeps one before anything is forgotten.
        $this->commands([PruneSubmissionsCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-inbox.php' => config_path('webx-inbox.php'),
        ], 'webx-inbox-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-inbox'),
        ], 'webx-inbox-lang');

        // The form on the site and the letter. Both ship as the least markup that works, and
        // both are meant to be published and rewritten: the reference implementation kept the
        // intake and replaced every control on the page (§2.15).
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-inbox'),
        ], 'webx-inbox-views');

        // For a site that would rather have the script in its own bundle than as one more
        // request. It then switches `webx-inbox.script` off, and the form stops linking ours.
        $this->publishes([
            __DIR__.'/../resources/js' => resource_path('js/vendor/webx-inbox'),
        ], 'webx-inbox-assets');
    }

    /**
     * How often one address may knock on one form, accepted or not (§7).
     *
     * A named limiter rather than `throttle:20,1` on the route, because the number belongs to
     * the form: a support form that people send twice in a row is not a subscribe box. The
     * form is already in memory by the time this runs — the limiter and the controller share
     * one lookup — so reading its setting costs nothing. The limit on submissions that got
     * through is the controller's, see {@see Throttle}.
     */
    private function registerRateLimiter(): void
    {
        RateLimiter::for('webx-inbox', function (Request $request): Limit {
            $slug = (string) $request->route('slug');

            return $this->app->make(Throttle::class)->attempts(
                $this->app->make(Forms::class)->enabled($slug),
                $request,
                $slug,
            );
        });
    }
}
