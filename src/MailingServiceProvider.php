<?php

declare(strict_types=1);

namespace J87NL\Mailing;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use J87NL\Cms\Blocks\BlockRegistry;
use J87NL\Cms\Cache\FlushPageCache;
use J87NL\Mailing\Cms\SignupBlock;
use J87NL\Mailing\Console\PruneMailingData;
use J87NL\Mailing\Contracts\MailboxReader;
use J87NL\Mailing\Contracts\SenderIdentityProvider;
use J87NL\Mailing\Models\MailingList;

class MailingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mailing.php', 'mailing');

        $this->app->singleton(SenderIdentityProvider::class, fn (Application $app): SenderIdentityProvider => $app->make((string) config('mailing.sender_identity_provider')));
        $this->app->singleton(MailboxReader::class, fn (Application $app): MailboxReader => $app->make((string) config('mailing.mailbox_reader')));

        $this->registerPermissions();
        $this->registerNavigation();
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'mailing');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mailing');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        RateLimiter::for('mailing-unsubscribe', fn (Request $request): Limit => Limit::perMinute(10)
            ->by(sprintf('mailing-unsubscribe:%s', sha1((string) $request->route('token')))));

        $this->loadRoutesFrom(__DIR__.'/../routes/public.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');

        if (class_exists(BlockRegistry::class)) {
            $this->callAfterResolving(BlockRegistry::class, function (BlockRegistry $registry): void {
                $registry->register(SignupBlock::class);
            });
        }

        if (class_exists(FlushPageCache::class)) {
            MailingList::observe(FlushPageCache::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneMailingData::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (! config('mailing.scheduling.enabled', true)) {
                return;
            }

            $schedule->command(PruneMailingData::class)
                ->dailyAt('03:15')
                ->withoutOverlapping();
        });

        $this->publishes([
            __DIR__.'/../config/mailing.php' => config_path('mailing.php'),
        ], 'mailing-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/mailing'),
        ], 'mailing-views');
    }

    private function registerPermissions(): void
    {
        $key = (string) config('mailing.permissions_config_key');
        $registered = config($key, []);

        foreach (MailingPermission::names() as $permission) {
            if (is_array($registered) && in_array($permission, $registered, true)) {
                continue;
            }

            config()->push($key, $permission);
        }
    }

    private function registerNavigation(): void
    {
        $key = (string) config('mailing.navigation_config_key');

        $items = [
            [
                'key' => 'mailing.lists',
                'label' => 'mailing::navigation.lists',
                'route' => 'mailing.lists.index',
                'icon' => 'Mail',
                'permission' => MailingPermission::Lists->value,
                'order' => 200,
            ],
            [
                'key' => 'mailing.subscribers',
                'label' => 'mailing::navigation.subscribers',
                'route' => 'mailing.subscribers.index',
                'icon' => 'Contact',
                'permission' => MailingPermission::Subscribers->value,
                'order' => 210,
            ],
            [
                'key' => 'mailing.suppressions',
                'label' => 'mailing::navigation.suppressions',
                'route' => 'mailing.suppressions.index',
                'icon' => 'Ban',
                'permission' => MailingPermission::Subscribers->value,
                'order' => 220,
            ],
            [
                'key' => 'mailing.imports',
                'label' => 'mailing::navigation.imports',
                'route' => 'mailing.imports.index',
                'icon' => 'Upload',
                'permission' => MailingPermission::Subscribers->value,
                'order' => 230,
            ],
        ];

        $registered = config($key, []);
        $registeredKeys = is_array($registered) ? array_column($registered, 'key') : [];

        foreach ($items as $item) {
            if (in_array($item['key'], $registeredKeys, true)) {
                continue;
            }

            config()->push($key, $item);
        }
    }
}
