<?php

namespace MoHiTech\MoHiPay;

use Illuminate\Support\ServiceProvider;

class PayServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register()
    {
    }
}
