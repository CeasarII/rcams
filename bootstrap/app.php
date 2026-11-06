<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
