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
use App\Services\StatCalculator;
use App\Services\StatService;
use App\Services\Stats\StatPipeline;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StatCalculator::class);
        $this->app->singleton(StatPipeline::class, fn ($app) => new StatPipeline($app->make(StatCalculator::class)));
        $this->app->singleton(StatService::class);

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
        //
    }
}
