<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use App\Contracts\Services\FraudDetectionInterface;
use App\Services\FraudDetectionService;
use App\Contracts\Repositories\TransactionRepositoryInterface;
use App\Repositories\TransactionRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Contracts\Repositories\TransactionRepositoryInterface::class,
            \App\Repositories\TransactionRepository::class
        );

        $this->app->bind 
        (
            FraudDetectionInterface::class,
            FraudDetectionService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        RateLimiter::for('transaction-api', function (Request $request) {
            return [
                // Strict per-user limit
                Limit::perMinute(60)->by($request->user()?->id ?? $request->ip()),
                // Also limit per IP (catches bots before they authenticate)
                Limit::perMinute(100)->by($request->ip()),
            ];
        });

        RateLimiter::for('sync-endpoint', function (Request $request) {
            return [
                // Max 3 sync requests per minute per user
                // Prevents the "Denial of Wallet" attack where someone floods
                // the sync endpoint to trigger thousands of AI API calls
                Limit::perMinute(3)->by('sync:' . ($request->user()?->id ?? $request->ip())),
            ];
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    
}
