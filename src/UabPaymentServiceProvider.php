<?php

namespace Gmbfgp\Uabpayment;

use Illuminate\Support\ServiceProvider;

class UabPaymentServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish config file to Laravel app config directory
        $this->publishes([
            __DIR__.'/../config/uabpayment.php' => config_path('uabpayment.php'),
        ], 'uabpayment-config');
    }

    public function register()
    {
        // Merge your config file so default values are available
        $this->mergeConfigFrom(
            __DIR__.'/../config/uabpayment.php', 'uabpayment'
        );

        // Bind your main service class to the service container
        $this->app->singleton(UabPaymentService::class, function ($app) {
            return new UabPaymentService();
        });
    }
}
