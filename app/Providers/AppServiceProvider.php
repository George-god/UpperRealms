<?php

namespace App\Providers;

use App\Services\BattleService;
use App\Services\CombatService;
use App\Services\DaoCommandService;
use App\Services\DaoPathService;
use App\Services\DaoPetitionService;
use App\Services\DaoService;
use App\Services\DaoTechniqueService;
use App\Services\PvEBattleService;
use App\Services\Attributes\AttributeCalculator;
use App\Services\Attributes\CharacterBuildService;
use App\Services\ArtifactService;
use App\Services\BloodlineService;
use App\Services\CultivationService;
use App\Services\RewardService;
use App\Services\SectService;
use App\Services\StatCalculator;
use App\Services\StatService;
use App\Services\Stats\PlayerStatCache;
use App\Services\Stats\StatBonusComposer;
use App\Services\Stats\StatInvalidator;
use App\Services\Stats\StatPipeline;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AttributeCalculator::class);
        $this->app->singleton(CharacterBuildService::class);
        $this->app->singleton(StatBonusComposer::class);
        $this->app->singleton(StatCalculator::class);
        $this->app->singleton(PlayerStatCache::class);
        $this->app->singleton(StatInvalidator::class);
        $this->app->singleton(StatPipeline::class);
        $this->app->singleton(StatService::class);

        $this->app->singleton(RewardService::class);
        $this->app->singleton(CultivationService::class);
        $this->app->singleton(BloodlineService::class);
        $this->app->singleton(ArtifactService::class);
        $this->app->singleton(SectService::class);

        $this->app->singleton(BattleService::class);
        $this->app->singleton(PvEBattleService::class);
        $this->app->singleton(CombatService::class);

        $this->app->singleton(DaoService::class, fn ($app) => new DaoService(
            $app->make(DaoPathService::class),
            $app->make(DaoTechniqueService::class),
            $app->make(DaoPetitionService::class),
            $app->make(DaoCommandService::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.partials.topbar', function () {
            if ($user = auth()->user()) {
                $user->loadMissing('realm');
            }
        });
    }
}
