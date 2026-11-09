<?php

namespace App\Providers;

use App\Services\Stats;
use App\Services\TicketService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request so the document/ticket cache is shared.
        $this->app->singleton(TicketService::class);
        $this->app->singleton(Stats::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Flash notice for the SweetAlert dispatcher in the layout.
        View::composer('layouts.base', function ($view) {
            $view->with('flash', session('flash'));
        });
    }
}
