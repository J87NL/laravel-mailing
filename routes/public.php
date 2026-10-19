<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use J87NL\Mailing\Http\Controllers\Public\ConfirmationController;
use J87NL\Mailing\Http\Controllers\Public\SignupController;
use J87NL\Mailing\Http\Controllers\Public\UnsubscribeController;

Route::middleware([
    ...config('mailing.routes.public_middleware', []),
    SubstituteBindings::class,
])
    ->prefix((string) config('mailing.routes.public_prefix', 'mailing'))
    ->name('mailing.')
    ->group(function (): void {
        Route::get('confirm/{subscription}', [ConfirmationController::class, 'show'])
            ->middleware('throttle:30,1')
            ->name('confirm.show');
        Route::post('confirm/{subscription}', [ConfirmationController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('confirm.store');

        Route::get('unsubscribe/{token}', [UnsubscribeController::class, 'show'])
            ->middleware('throttle:mailing-unsubscribe')
            ->name('unsubscribe.show');
        Route::post('unsubscribe/{token}', [UnsubscribeController::class, 'store'])
            ->middleware('throttle:mailing-unsubscribe')
            ->name('unsubscribe.store');

        Route::get('{list:key}/subscribe', [SignupController::class, 'show'])
            ->name('subscribe.show');
        Route::post('{list:key}/subscribe', [SignupController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('subscribe.store');
    });
