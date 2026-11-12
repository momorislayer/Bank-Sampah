<?php

namespace App\Providers;

use App\Contracts\NotificationServiceInterface;
use App\Services\Notification\LogNotificationService;
use App\Services\Notification\WhatsAppNotificationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NotificationServiceInterface::class, function () {
            if (config('services.whatsapp.api_key')) {
                return new WhatsAppNotificationService();
            }

            return new LogNotificationService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
