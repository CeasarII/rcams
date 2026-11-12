<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // "Page Expired" (419) means the form's security token no longer matches the session:
        // the session timed out while the page sat open, or it was cleared in another tab.
        // Don't show a dead-end error screen.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            // Logging out with an expired page: they wanted to leave anyway, so finish the job.
            if ($request->routeIs('logout')) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('status', 'You have been signed out.');
            }

            // Session already gone (e.g. login page left open): back to a fresh login form.
            if (! $request->user()) {
                return redirect()->route('login')
                    ->withErrors(['session' => 'Your session expired. Please sign in again.']);
            }

            // Still signed in, but the page was stale: return to it so they can resubmit.
            return back()
                ->withInput($request->except('_token', 'password', 'password_confirmation'))
                ->withErrors(['session' => 'That page expired. Please review it and submit again.']);
        });

        // A signed-in user who opens a page their role can't access (typed URL,
        // bookmark, or the "intended" URL kept from before login) is sent back
        // to the main page with a message, instead of a bare 403 screen.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 403
                || ! $request->user()
                || ! $request->isMethod('GET')
                || $request->expectsJson()
                || $request->routeIs('fund-requests.index')) {
                return null;
            }

            return redirect()
                ->route('fund-requests.index')
                ->withErrors(['access' => "You don't have access to that page."]);
        });
    })->create();
