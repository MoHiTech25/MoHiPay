<?php

namespace MoHiTech\MoHiPay;

use Illuminate\Support\ServiceProvider;
use MoHiTech\MoHiPay\Models\Payment;

class PayServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $target = app_path('Models/Payment.php');
        if (!file_exists($target)) {
            $this->publishes([
                __DIR__ . '/../Models/Payment.php' => $target,
            ], 'mohipay-models');

            $this->callAfterResolving(\Illuminate\Foundation\Application::class, function () {
                $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->call('vendor:publish', [
                    '--tag' => 'mohipay-models',
                    '--force' => true,
                ]);
            });
        }
        
    }


    public function register()
    {
    }
}
