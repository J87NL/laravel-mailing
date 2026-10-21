<?php

declare(strict_types=1);

use J87NL\Mailing\Actions\ApplyDeliveryEvent;
use J87NL\Mailing\Delivery\DeliveryEvent;
use J87NL\Mailing\Enums\BounceType;
use J87NL\Mailing\Enums\DeliveryEventType;
use J87NL\Mailing\Models\BounceEvent;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Support\ConsentSource;

test('a provider lifting its suppression finds the address however the provider spells it', function (string $added, string $removed): void {
    $source = ConsentSource::make(ConsentSource::WEBHOOK, 'lettermint');

    app(ApplyDeliveryEvent::class)->handle(new DeliveryEvent(DeliveryEventType::SuppressionAdded, 'evt_added', recipient: $added), $source);

    expect(Suppression::query()
        ->count())->toBe(1);

    app(ApplyDeliveryEvent::class)->handle(new DeliveryEvent(DeliveryEventType::SuppressionRemoved, 'evt_removed', recipient: $removed), $source);

    expect(Suppression::query()
        ->count())->toBe(0);
})->with([
    'accented capital' => [
        'Élise@Example.org',
        'Élise@Example.org',
    ],
    'surrounding spaces' => [
        'lid@example.org',
        ' LID@example.org ',
    ],
]);

test('rolling back the wider bounce source shortens longer sources first', function (): void {
    $bounce = BounceEvent::query()
        ->create([
            'email' => 'lid@example.org',
            'type' => BounceType::Soft,
            'source' => ConsentSource::make(ConsentSource::WEBHOOK, 'a-very-long-driver-key'),
        ]);

    $migration = include sprintf('%s/../../database/migrations/2026_10_21_120001_widen_mailing_bounce_events_source.php', __DIR__);
    $migration->down();

    expect($bounce->fresh()->source)->toBe(substr(ConsentSource::make(ConsentSource::WEBHOOK, 'a-very-long-driver-key'), 0, 20));
});
