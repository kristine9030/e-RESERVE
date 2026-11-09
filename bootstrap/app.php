<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureStudent;
use App\Http\Middleware\EnsureVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin'    => EnsureAdmin::class,
            'staff'    => App\Http\Middleware\EnsureStaff::class,
            'student'  => EnsureStudent::class,
            'verified' => EnsureVerified::class,
        ]);

        // A disabled account is signed out on its next request.
        $middleware->appendToGroup('web', App\Http\Middleware\EnsureActive::class);
        // An SSC-made account must set its own password before using anything else.
        $middleware->appendToGroup('web', App\Http\Middleware\EnsurePasswordChanged::class);

        // Guests are sent to the sign-in page.
        // Staff areas send a signed-out visitor to the SSC door, everything else to the student sign-in.
        $middleware->redirectGuestsTo(fn ($request) => $request->is('admin', 'admin/*', 'reports', 'reports/*') ? route('ssc.login') : route('login'));
        // Signed-in users who open /login or /register go to their own home.
        $middleware->redirectUsersTo(fn () => auth()->user()?->homeRoute() ?? route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An expired session / stale form token: go back with a clear message instead of a bare "Page Expired".
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }
            $target = $request->isMethod('GET') ? url('/') : (url()->previous() ?: route('login'));

            return redirect($target)->withInput($request->except('password', 'password_confirmation', '_token'))
                ->with('flash', flash('info', 'Your page timed out', 'For your security the form expired. Please try again.'));
        });
    })->create();
