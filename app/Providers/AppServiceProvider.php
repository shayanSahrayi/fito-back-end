<?php

namespace App\Providers;

use App\Domain\Ai\FakeLanguageModel;
use App\Domain\Ai\HttpLanguageModel;
use App\Domain\Ai\LanguageModel;
use App\Domain\Coaching\AthleteWelcomeNotice;
use App\Domain\Coaching\LogAthleteWelcomeNotice;
use App\Domain\Identity\FixedOtpPolicy;
use App\Domain\Identity\OtpCodeGenerator;
use App\Domain\Identity\RandomOtpCodeGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OtpCodeGenerator::class, RandomOtpCodeGenerator::class);
        $this->app->bind(AthleteWelcomeNotice::class, LogAthleteWelcomeNotice::class);
        $this->app->bind(LanguageModel::class, function ($app) {
            if ($app->environment('testing')) {
                return new FakeLanguageModel();
            }

            return $app->make(HttpLanguageModel::class);
        });
        $this->app->singleton(FixedOtpPolicy::class, fn () => FixedOtpPolicy::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(FixedOtpPolicy::class)->assertProductionIsSafe();
    }
}
