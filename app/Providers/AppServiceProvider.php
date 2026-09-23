<?php

namespace App\Providers;

use App\Domain\Organisasi\Models\Afdeling;
use App\Domain\Organisasi\Models\Blok;
use App\Domain\Organisasi\Models\Kebun;
use App\Policies\AfdelingPolicy;
use App\Policies\BlokPolicy;
use App\Policies\KebunPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Kebun::class, KebunPolicy::class);
        Gate::policy(Afdeling::class, AfdelingPolicy::class);
        Gate::policy(Blok::class, BlokPolicy::class);
    }
}
