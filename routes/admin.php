<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use J87NL\Mailing\Http\Controllers\Admin\ImportController;
use J87NL\Mailing\Http\Controllers\Admin\ListController;
use J87NL\Mailing\Http\Controllers\Admin\SubscriberController;
use J87NL\Mailing\Http\Controllers\Admin\SuppressionController;
use J87NL\Mailing\MailingPermission;

Route::middleware(config('mailing.routes.admin_middleware', []))
    ->prefix((string) config('mailing.routes.admin_prefix', 'admin/mailing'))
    ->name('mailing.')
    ->group(function (): void {
        Route::middleware(sprintf('can:%s', MailingPermission::Lists->value))->group(function (): void {
            Route::get('lists', [ListController::class, 'index'])->name('lists.index');
            Route::get('lists/create', [ListController::class, 'create'])->name('lists.create');
            Route::post('lists', [ListController::class, 'store'])->name('lists.store');
            Route::get('lists/{list}/edit', [ListController::class, 'edit'])->name('lists.edit');
            Route::put('lists/{list}', [ListController::class, 'update'])->name('lists.update');
            Route::delete('lists/{list}', [ListController::class, 'destroy'])->name('lists.destroy');

            Route::middleware(sprintf('can:%s', MailingPermission::Settings->value))->group(function (): void {
                Route::get('lists/{list}/transport', [ListController::class, 'editTransport'])->name('lists.transport.edit');
                Route::put('lists/{list}/transport', [ListController::class, 'updateTransport'])->name('lists.transport.update');
            });
        });

        Route::middleware(sprintf('can:%s', MailingPermission::Subscribers->value))->group(function (): void {
            Route::get('subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
            Route::get('subscribers/{subscriber}', [SubscriberController::class, 'show'])->name('subscribers.show');
            Route::post('subscriptions/{subscription}/unsubscribe', [SubscriberController::class, 'unsubscribe'])->name('subscriptions.unsubscribe');

            Route::get('suppressions', [SuppressionController::class, 'index'])->name('suppressions.index');
            Route::get('suppressions/create', [SuppressionController::class, 'create'])->name('suppressions.create');
            Route::post('suppressions', [SuppressionController::class, 'store'])->name('suppressions.store');
            Route::delete('suppressions/{suppression}', [SuppressionController::class, 'destroy'])->name('suppressions.destroy');

            Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
            Route::get('imports/create', [ImportController::class, 'create'])->name('imports.create');
            Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
        });
    });
