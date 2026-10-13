<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Exceptions\AiException;
use Illuminate\Http\Request;

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
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Handle all AI exceptions gracefully
        $exceptions->render(function (InsufficientCreditsException|RateLimitedException|AiException $e, Request $request) {
            $message = match (true) {
                $e instanceof InsufficientCreditsException => 'AI service has insufficient credits. Please check your provider account.',
                $e instanceof RateLimitedException => 'AI service is rate limited. Please try again in a moment.',
                default => 'AI service is temporarily unavailable. Please try again later.',
            };

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $message,
                    'type' => class_basename($e),
                    'provider' => $e->getMessage(),
                ], 503);
            }

            return back()->with('error', $message);
        });
    })->create();
