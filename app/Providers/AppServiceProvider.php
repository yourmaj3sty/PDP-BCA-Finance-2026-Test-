<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\CreditApplication;
use App\Policies\CreditApplicationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(CreditApplication::class, CreditApplicationPolicy::class);
    }
}
