<?php

declare(strict_types=1);

use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Signup\SignupGuard;
use J87NL\Mailing\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * @param  array<string, mixed>  $attributes
 */
function makeList(array $attributes = []): MailingList
{
    return MailingList::query()->create([
        'key' => 'nieuws',
        'name' => 'Nieuwsbrief',
        'mailer' => 'array',
        'sender_identity' => 'rommelmarkt',
        'is_public' => true,
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function signupPayload(MailingList $list, array $overrides = []): array
{
    test()->travel(-10)->seconds();
    $token = app(SignupGuard::class)->token($list->id);
    test()->travelBack();

    return [
        'email' => 'bezoeker@example.org',
        'name' => 'Bezoeker',
        SignupGuard::TIMESTAMP => $token,
        ...$overrides,
    ];
}

function makeSubscription(MailingList $list, string $email = 'lid@example.org', SubscriptionStatus $status = SubscriptionStatus::Subscribed): Subscription
{
    $subscriber = Subscriber::query()->firstOrCreate([
        'email' => $email,
    ]);

    return Subscription::query()->create([
        'subscriber_id' => $subscriber->id,
        'list_id' => $list->id,
        'status' => $status,
        'confirmed_at' => $status === SubscriptionStatus::Subscribed ? now() : null,
    ]);
}
