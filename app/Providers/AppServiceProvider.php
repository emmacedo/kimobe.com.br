<?php

namespace App\Providers;

use App\Listeners\SyncFullFlowSubscription;
use App\Services\TenantService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Kicol\FullFlow\Events\SubscriptionActivated;
use Kicol\FullFlow\Events\SubscriptionCancellationScheduled;
use Kicol\FullFlow\Events\SubscriptionEnded;
use Kicol\FullFlow\Events\SubscriptionPastDue;
use Kicol\FullFlow\Events\SubscriptionPaymentReceived;
use Kicol\FullFlow\Events\SubscriptionReactivated;
use Kicol\FullFlow\Events\SubscriptionSuspended;
use Kicol\FullFlow\Events\SubscriptionTrialStarted;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantService::class);
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerFullFlowListeners();
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

    /**
     * Conecta o listener de sync à lista de eventos disparados pelo
     * FullFlowWebhookController do package.
     */
    protected function registerFullFlowListeners(): void
    {
        $events = [
            SubscriptionTrialStarted::class,
            SubscriptionActivated::class,
            SubscriptionPaymentReceived::class,
            SubscriptionReactivated::class,
            SubscriptionPastDue::class,
            SubscriptionSuspended::class,
            SubscriptionCancellationScheduled::class,
            SubscriptionEnded::class,
        ];

        foreach ($events as $event) {
            Event::listen($event, [SyncFullFlowSubscription::class, 'handle']);
        }
    }
}
