<?php 

namespace ClickPay\Providers;

use Illuminate\Support\ServiceProvider;
use ClickPay\ClickPayService;

class ClickPayServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/clickpay.php', 'clickpay');

        $this->app->singleton(ClickPayService::class, function($app) {
            return new ClickPayService($app['config']['clickpay']);
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/clickpay.php' => config_path('clickpay.php'),
        ], 'config');
    }
}
