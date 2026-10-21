<?php

declare(strict_types=1);

use J87NL\Mailing\Actions\ApplyDeliveryEvent;
use J87NL\Mailing\Delivery\DeliveryEvent;
use J87NL\Mailing\Enums\DeliveryEventType;
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
