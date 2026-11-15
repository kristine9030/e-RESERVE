<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Stats;
use App\Services\TicketService;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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

        // Never keep mailing an address that SES reported as undeliverable (or as spam): it only
        // raises our bounce rate. Returning false cancels just that one message.
        Event::listen(MessageSending::class, function (MessageSending $e) {
            try {
                $to = array_map(static fn ($a) => strtolower($a->getAddress()), $e->message->getTo());
                if ($to !== [] && User::whereIn('email', $to)->whereNotNull('email_bounced_at')->exists()) {
                    Log::info('[e-RESERVE] email skipped, address is flagged as undeliverable: ' . implode(',', $to));

                    return false;
                }
            } catch (\Throwable) {
                // a lookup problem must never stop real mail
            }
        });

        // Flash notice for the SweetAlert dispatcher in the layout.
        View::composer('layouts.base', function ($view) {
            $view->with('flash', session('flash'));
        });
    }
}
