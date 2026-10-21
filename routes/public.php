<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use J87NL\Mailing\Http\Controllers\Public\AttachmentDownloadController;
use J87NL\Mailing\Http\Controllers\Public\ConfirmationController;
use J87NL\Mailing\Http\Controllers\Public\ConfirmationScriptController;
use J87NL\Mailing\Http\Controllers\Public\SignupController;
use J87NL\Mailing\Http\Controllers\Public\UnsubscribeController;
use J87NL\Mailing\Http\Controllers\Public\WebhookController;
use J87NL\Mailing\Http\Middleware\AllowSameOriginScripts;

Route::middleware([
    AllowSameOriginScripts::class,
    ...config('mailing.routes.public_middleware', []),
    SubstituteBindings::class,
])
    ->prefix((string) config('mailing.routes.public_prefix', 'mailing'))
    ->name('mailing.')
    ->group(function (): void {
        Route::get('confirm/{subscription}', [ConfirmationController::class, 'show'])
            ->middleware('throttle:30,1')
            ->name('confirm.show');
    });

Route::middleware([
    ...config('mailing.routes.public_middleware', []),
    SubstituteBindings::class,
])
    ->prefix((string) config('mailing.routes.public_prefix', 'mailing'))
    ->name('mailing.')
    ->group(function (): void {
        Route::get('assets/confirm.js', ConfirmationScriptController::class)
            ->middleware('throttle:60,1')
            ->name('confirm.script');
        Route::post('confirm/{subscription}', [ConfirmationController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('confirm.store');

        Route::get('unsubscribe/{token}', [UnsubscribeController::class, 'show'])
            ->middleware('throttle:mailing-unsubscribe')
            ->name('unsubscribe.show');
        Route::post('unsubscribe/{token}', [UnsubscribeController::class, 'store'])
            ->middleware('throttle:mailing-unsubscribe')
            ->name('unsubscribe.store');

        Route::get('attachments/{token}', AttachmentDownloadController::class)
            ->middleware([
                ValidateSignature::class,
                'throttle:60,1',
            ])
            ->withoutMiddleware([
                AddQueuedCookiesToResponse::class,
                EncryptCookies::class,
                PreventRequestForgery::class,
                ShareErrorsFromSession::class,
                StartSession::class,
            ])
            ->where('token', '[A-Za-z0-9]{40}')
            ->name('attachments.download');

        Route::post('webhooks/{driver}', WebhookController::class)
            ->middleware('throttle:600,1')
            ->name('webhooks');

        Route::get('{list:key}/subscribe', [SignupController::class, 'show'])
            ->name('subscribe.show');
        Route::post('{list:key}/subscribe', [SignupController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('subscribe.store');
    });
