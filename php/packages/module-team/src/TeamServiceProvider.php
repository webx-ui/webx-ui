<?php

declare(strict_types=1);

namespace WebxUi\Team;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Team\Collections\TeamSource;
use WebxUi\Team\Models\Member;
use WebxUi\Team\Panel\TeamModule;
use WebxUi\Team\Relations\MemberTarget;

/**
 * The people of the team and the screen they are edited on — and not one public route
 * (decision 2). A person reaches the site in a block, or through `team()` in a template of the
 * site: the module offers the block type and a source for it, and the page it stands on brings
 * the address, the SEO and the menu entry.
 */
class TeamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-team.php', 'webx-team');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-team');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerScreens();
        $this->registerCollection();

        $this->app->make(ModuleRegistry::class)->register($this->app->make(TeamModule::class));

        // A person is something other modules can point at (decision 12).
        $this->app->make(RelationTargets::class)->register(new MemberTarget);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-team.php' => config_path('webx-team.php'),
        ], 'webx-team-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-team'),
        ], 'webx-team-lang');
    }

    /**
     * The editor of a person, described, so a project adds a field with a patch — and the list of
     * networks laid over it from the config (§5.4). The select has no options in the JSON: this
     * patch is where they come from, so a site adds a network with one line of config, and the
     * check of the value is the one every `wx-select` already has on the server.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(Member::SCREEN, __DIR__.'/../resources/screens/form.json');
        $screens->extend(Member::SCREEN, [[
            'op' => 'set',
            'target' => 'social-network',
            'props' => ['options' => $this->app->make(Networks::class)->options()],
        ]]);
    }

    /**
     * What a block may show, and the block that shows it (§5.3, §5.5). The type is offered, not
     * installed: `webx:blocks:offered --install` puts it on the site once, and a type the site
     * already has by that name is never touched.
     */
    private function registerCollection(): void
    {
        $this->app->singleton(TeamSource::class);
        $this->app->make(CollectionSources::class)->register($this->app->make(TeamSource::class));

        $this->app->make(BlockOffers::class)->offer(TeamModule::ID, __DIR__.'/../resources/blocks');
    }
}
