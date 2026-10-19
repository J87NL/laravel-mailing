<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Controllers\Public;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Actions\ApplyDeliveryEvent;
use J87NL\Mailing\Drivers\DriverRegistry;
use J87NL\Mailing\Models\WebhookEvent;
use J87NL\Mailing\Support\ConsentSource;

class WebhookController extends Controller
{
    public function __invoke(Request $request, string $driver, DriverRegistry $drivers, ApplyDeliveryEvent $applyDeliveryEvent): JsonResponse
    {
        $mailingDriver = $drivers->find($driver);

        if (is_null($mailingDriver) || ! $mailingDriver->hasWebhooks()) {
            abort(404);
        }

        if (! $mailingDriver->verifyWebhook($request)) {
            return response()->json([
                'message' => 'Invalid signature.',
            ], 401);
        }

        $applied = 0;

        foreach ($mailingDriver->parseWebhook($request) as $event) {
            try {
                DB::transaction(function () use ($event, $driver, $applyDeliveryEvent): void {
                    WebhookEvent::query()
                        ->create([
                            'driver' => $driver,
                            'event_id' => $event->eventId,
                            'event' => $event->type->value,
                        ]);

                    $applyDeliveryEvent->handle($event, ConsentSource::make(ConsentSource::WEBHOOK, $driver));
                });
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            $applied++;
        }

        return response()->json([
            'applied' => $applied,
        ]);
    }
}
