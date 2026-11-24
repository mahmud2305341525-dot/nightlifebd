<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\SmsChannel;
use App\Services\HttpSmsChannel;
use App\Services\MockPaymentGateway;
use App\Services\NonePaymentGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsChannel::class, HttpSmsChannel::class);
        $this->app->bind(PaymentGateway::class, function ($app) {
            $driver = config('nightlife.payments_driver', 'none');

            return $driver === 'mock' && config('nightlife.demo_enabled')
                ? new MockPaymentGateway
                : new NonePaymentGateway;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
    }
}
